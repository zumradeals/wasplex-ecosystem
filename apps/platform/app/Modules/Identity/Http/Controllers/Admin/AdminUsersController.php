<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Models\User;
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
use App\Modules\Governance\Authorization\Services\GrantAutoIssuer;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Identity\Http\Requests\StoreAdminUserRequest;
use App\Modules\Identity\Models\AssuranceState;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestion admin des comptes utilisateurs (instruction explicite du
 * fondateur, 2026-07-31 : « à ce jour l'administrateur n'a pas de
 * fonctions essentielles pour créer, modifier ou supprimer un
 * utilisateur »). Gouvernée par `identity.manage_users` — même gabarit
 * d'autorisation que les écrans Advertising existants.
 *
 * « Supprimer » un utilisateur ne signifie jamais une suppression
 * physique (aucun module de ce dépôt ne le fait jamais pour une personne
 * réelle) : cette gestion se limite à trois transitions d'état de compte
 * déjà modélisées ({@see AccountState}) — actif, suspendu, clôturé.
 * `invited` reste hors de portée de cette transition manuelle : c'est un
 * état initial du cycle d'inscription, jamais une destination choisie
 * par le personnel.
 */
class AdminUsersController extends Controller
{
    use ResolvesStaffVisibility;

    private const CAPABILITY_KEY = 'identity.manage_users';

    private const PER_PAGE = 25;

    /**
     * @var list<string>
     */
    private const ASSIGNABLE_STATES = ['active', 'suspended', 'closed'];

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly RegistersUserIdentity $registersUserIdentity,
        private readonly GrantAutoIssuer $grantAutoIssuer,
    ) {}

    public function index(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'access' => ['allowed' => false, 'reason' => $reason],
            'users' => [],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => self::PER_PAGE, 'total' => 0, 'previous_url' => null, 'next_url' => null],
            'search' => null,
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/users', $deniedProps);

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

        $canView = in_array($authorization->decision, [
            AuthorizationDecision::Allowed,
            AuthorizationDecision::AllowedReadOnly,
        ], true);

        if (! $canView) {
            return Inertia::render('admin/users', $deniedProps($authorization->reason->code));
        }

        $search = trim((string) $request->query('q', ''));

        $query = User::query()->orderByDesc('users.created_at');

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('admin/users', [
            'access' => ['allowed' => true, 'reason' => null],
            'users' => collect($paginator->items())
                ->map(fn (User $user): array => $this->present($user))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'previous_url' => $paginator->previousPageUrl(),
                'next_url' => $paginator->nextPageUrl(),
            ],
            'search' => $search !== '' ? $search : null,
        ]);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $authorization = $this->authorize($request);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $user = DB::transaction(function () use ($request): User {
            $user = $this->registersUserIdentity->register([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
            ], LinkOrigin::SupportReview);

            $link = PersonAccountLink::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->firstOrFail();

            // Un compte créé par le personnel doit rester utilisable au
            // même titre qu'une inscription en libre-service — sans quoi
            // aucune capacité `self` (dépôt Wallet, achat d'abonnement,
            // auto-soumission d'événement…) ne serait accessible à cette
            // personne. `RegistersUserIdentity` n'émet volontairement pas
            // ce grant hors de `LinkOrigin::Registration` (voir son
            // docblock) : c'est donc à ce contrôleur de le faire
            // explicitement, dans la même transaction.
            $this->grantAutoIssuer->issueRoleTemplateGrants($link, 'user.base', (string) Str::uuid());

            return $user;
        });

        return response()->json($this->present($user), 201);
    }

    public function updateState(Request $request, User $user): JsonResponse
    {
        $authorization = $this->authorize($request);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $validated = $request->validate([
            'account_state' => ['required', 'string', Rule::in(self::ASSIGNABLE_STATES)],
        ]);

        $assurance = AssuranceState::query()->where('user_id', $user->id)->firstOrFail();
        $assurance->forceFill(['account_state' => $validated['account_state']])->save();

        return response()->json($this->present($user->fresh()), 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        $assurance = AssuranceState::query()->where('user_id', $user->id)->first();

        return [
            'id' => $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'account_state' => $assurance?->account_state->value ?? AccountState::Active->value,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    private function authorize(Request $request): ?JsonResponse
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

        return null;
    }

    private function resourceContext(Environment $environment): ResourceContext
    {
        return new ResourceContext(
            resourceType: 'identity.user',
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
