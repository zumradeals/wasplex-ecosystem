<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Advertising\Models\AdvertiserWalletDeposit;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Projections\AdvertiserWalletBalanceProjection;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wallet annonceur (instruction explicite du fondateur, 2026-07-31) : solde
 * mutualisé par devise, reconstruit depuis le Ledger
 * ({@see AdvertiserWalletBalanceProjection}), jamais une colonne de solde
 * mise en cache — même discipline que 'advertising/budget'
 * ({@see AdvertisingBudgetController}).
 *
 * Distinct de 'advertising/budget' : le Wallet est la source des fonds
 * (dépôt libre-service, pas encore rattaché à une campagne), le Budget est
 * l'emploi des fonds une fois alloués à une campagne précise. Le financement
 * direct d'une campagne existant déjà sur l'écran Budget
 * (`campaign.fund_self`) reste inchangé et coexiste avec ce nouveau chemin :
 * ni l'un ni l'autre n'est retiré.
 */
class AdvertisingWalletController extends Controller
{
    use ResolvesAdvertiserWorkspace;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AdvertiserWalletBalanceProjection $walletBalance,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/wallet', [
            'balances' => [],
            'campaigns' => [],
            'recentDeposits' => [],
        ]);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $profile = $workspace['profile'];

        $campaigns = Campaign::query()
            ->where('advertiser_profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->get(['id', 'code', 'currency']);

        $recentDeposits = AdvertiserWalletDeposit::query()
            ->where('advertiser_profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'state', 'currency', 'amount', 'created_at']);

        return Inertia::render('advertising/wallet', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
            'balances' => $this->walletBalance->forAdvertiser($profile),
            'campaigns' => $campaigns->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'code' => $campaign->code,
                'currency' => $campaign->currency,
            ])->all(),
            'recentDeposits' => $recentDeposits->map(fn (AdvertiserWalletDeposit $deposit): array => [
                'id' => $deposit->id,
                'state' => $deposit->state->value,
                'currency' => $deposit->currency,
                'amount' => $deposit->amount,
                'created_at' => $deposit->created_at->toIso8601String(),
            ])->all(),
        ]);
    }
}
