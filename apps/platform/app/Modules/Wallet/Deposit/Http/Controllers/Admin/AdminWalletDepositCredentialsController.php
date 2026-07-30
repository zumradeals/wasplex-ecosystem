<?php

namespace App\Modules\Wallet\Deposit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Wallet\Deposit\Models\ProviderCredential;
use App\Modules\Wallet\Deposit\Services\GeniusPay\GeniusPayCredentialsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuration admin des clés API GeniusPay (véto du dirigeant 2026-07-30 ;
 * TD-0008-A). Gouvernée par `wallet_deposit.manage_credentials` — écrit
 * `ledger.wallet_deposit_provider_credentials`, jamais `.env` ni le dépôt
 * Git (AMD-0017 §6, inchangé : {@see GeniusPayCredentialsResolver}
 * retombe sur `config('services.geniuspay')` tant qu'aucune ligne n'existe).
 *
 * Aucune valeur secrète n'est jamais restituée au client, ni en clair ni
 * masquée partiellement (pas de "sk_***1234") : seul un indicateur booléen
 * « configuré » par champ, plus `base_url` qui n'est pas un secret. Un champ
 * de formulaire laissé vide ne change pas la valeur déjà stockée
 * ({@see update()}) — resaisir une clé existante n'est jamais nécessaire
 * pour changer seulement `base_url`.
 */
class AdminWalletDepositCredentialsController extends Controller
{
    use ResolvesStaffVisibility;

    private const CAPABILITY_KEY = 'wallet_deposit.manage_credentials';

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
    ) {}

    public function edit(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'access' => ['allowed' => false, 'reason' => $reason],
            'credentials' => null,
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/wallet-deposit-credentials', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        $environment = $this->currentEnvironment();
        $authorization = $this->authorizationGate->evaluate(
            $this->authorizationRequestFactory->make(
                subject: $resolved['subject'],
                capabilityKey: self::CAPABILITY_KEY,
                operation: Operation::Write,
                resource: $this->resourceContext($environment),
                environment: $environment,
            ),
        );

        if ($authorization->decision !== AuthorizationDecision::Allowed) {
            return Inertia::render('admin/wallet-deposit-credentials', $deniedProps($authorization->reason->code));
        }

        $stored = ProviderCredential::query()->where('provider', 'geniuspay')->first();

        return Inertia::render('admin/wallet-deposit-credentials', [
            'access' => ['allowed' => true, 'reason' => null],
            'credentials' => [
                'base_url' => $stored?->base_url,
                'api_key_configured' => filled($stored?->api_key),
                'api_secret_configured' => filled($stored?->api_secret),
                'webhook_secret_configured' => filled($stored?->webhook_secret),
                'updated_at' => $stored?->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: self::CAPABILITY_KEY,
            operation: Operation::Write,
            resource: $this->resourceContext($this->currentEnvironment()),
            environment: $this->currentEnvironment(),
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException $exception) {
            return $this->failureResponder->forOutcome($exception);
        }

        $validator = Validator::make($request->all(), [
            'base_url' => ['required', 'string', 'max:2048'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_secret' => ['nullable', 'string', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'validation_failed',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validated();

        $credential = ProviderCredential::query()->firstOrNew(['provider' => 'geniuspay']);
        $credential->base_url = $data['base_url'];

        // Un champ vide ne change pas la clé déjà stockée : resaisir une
        // clé existante n'est jamais nécessaire pour changer seulement
        // `base_url` (voir docblock de la classe).
        foreach (['api_key', 'api_secret', 'webhook_secret'] as $secretField) {
            if (filled($data[$secretField] ?? null)) {
                $credential->{$secretField} = $data[$secretField];
            }
        }

        $credential->updated_by_person_account_link_id = $subject->personAccountLink->id;
        $credential->save();

        return response()->json([
            'base_url' => $credential->base_url,
            'api_key_configured' => filled($credential->api_key),
            'api_secret_configured' => filled($credential->api_secret),
            'webhook_secret_configured' => filled($credential->webhook_secret),
            'updated_at' => $credential->updated_at->toIso8601String(),
        ], 200);
    }

    private function resourceContext(Environment $environment): ResourceContext
    {
        return new ResourceContext(
            resourceType: 'wallet.deposit_provider_credentials',
            resourceId: null,
            organizationId: null,
            ownerPersonId: null,
            countryCode: null,
            territoryCodes: [],
            environment: $environment,
        );
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
