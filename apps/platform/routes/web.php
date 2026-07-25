<?php

use App\Http\Controllers\HealthController;
use App\Modules\Advertising\Http\Controllers\AdvertiserProfileController;
use App\Modules\Advertising\Http\Controllers\AdvertisingOverviewController;
use App\Modules\Advertising\Http\Controllers\CampaignController;
use App\Modules\Advertising\Http\Controllers\CampaignFundingController;
use App\Modules\Advertising\Http\Controllers\CampaignReportController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionApprovalController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionSubmissionController;
use App\Modules\Advertising\Http\Controllers\ModerationCaseDecisionController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventAcceptanceController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventRejectionController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventSubmissionController;
use App\Modules\Wallet\Balance\Http\Controllers\WalletBalanceController;
use App\Modules\Wallet\Balance\Http\Controllers\WalletOverviewController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('/health', HealthController::class)->name('health');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // P006-A2 : premier écran de production réel (U-006-01-apercu-wallet,
    // UX-0002 §3.3) — reprend l'autorisation wallet.view (portée self) et
    // affiche un état d'écran plutôt qu'une erreur JSON en cas de refus
    // (voir WalletOverviewController). Volontairement dans le groupe 'auth'
    // (redirection vers la connexion, jamais un 401 JSON) : c'est un écran,
    // pas un point de terminaison API.
    Route::get('wallet', [WalletOverviewController::class, 'show'])->name('wallet.show');

    // P007-W1 : tableau de bord annonceur (A-001-01/A-002-01) — reprend
    // l'autorisation campaign.view (portée self) et affiche un état d'écran
    // plutôt qu'une erreur JSON en cas de refus (voir
    // AdvertisingOverviewController). Volontairement dans le groupe 'auth'
    // (redirection vers la connexion, jamais un 401 JSON) : c'est un écran,
    // pas un point de terminaison API — même raisonnement que 'wallet'.
    Route::get('advertising', [AdvertisingOverviewController::class, 'index'])->name('advertising.overview');
});

// Non protégée par le middleware 'auth' : une requête non authentifiée doit
// recevoir un 401 JSON structuré via AuthenticatedSubjectHttpResolver /
// AuthorizationFailureResponder (P003-B2/B4), jamais une redirection vers
// une page de connexion (P005-B).
Route::middleware('web')->post('advertising/campaigns', [CampaignController::class, 'store'])
    ->name('advertising.campaigns.store');

// P007-W1 : déclaration par une personne authentifiée de son propre
// dossier annonceur (advertiser_profile.create, migration
// 2026_07_25_100012) — même discipline que ci-dessus : groupe 'web' hors
// du groupe 'auth', 401 JSON structuré pour un appel non authentifié.
Route::middleware('web')->post('advertising/advertiser-profile', [AdvertiserProfileController::class, 'store'])
    ->name('advertising.advertiser-profile.store');

// P005-C : cycle de revue d'une CampaignVersion. Mêmes garanties que
// ci-dessus (groupe 'web' pour session/CSRF, hors du groupe 'auth' : un
// appel non authentifié reçoit un 401 JSON structuré, jamais une
// redirection).
Route::middleware('web')->post('advertising/campaign-versions/{campaignVersion}/submit-for-review', [CampaignVersionSubmissionController::class, 'store'])
    ->name('advertising.campaign-versions.submit-for-review');

Route::middleware('web')->post('advertising/campaign-versions/{campaignVersion}/approve', [CampaignVersionApprovalController::class, 'store'])
    ->name('advertising.campaign-versions.approve');

// P005-D : signalement (n'importe quel utilisateur authentifié, sur
// n'importe quelle campagne) et décision de modération. Même discipline
// que ci-dessus : groupe 'web' hors du groupe 'auth', 401 JSON structuré
// pour un appel non authentifié.
Route::middleware('web')->post('advertising/campaigns/{campaign}/reports', [CampaignReportController::class, 'store'])
    ->name('advertising.campaigns.reports.store');

Route::middleware('web')->post('advertising/moderation-cases/{moderationCase}/decisions', [ModerationCaseDecisionController::class, 'store'])
    ->name('advertising.moderation-cases.decisions.store');

// P005-E : encaissement confirmé d'annonceur (ADR-0010 §4 ligne 1 ;
// ADR-0003 §7-8). Réservé au personnel finance Wasplex, jamais à
// l'annonceur — voir `campaign.fund` (migration 2026_07_25_100008). Même
// discipline que ci-dessus : groupe 'web' hors du groupe 'auth', 401 JSON
// structuré pour un appel non authentifié.
Route::middleware('web')->post('advertising/campaigns/{campaign}/funding', [CampaignFundingController::class, 'store'])
    ->name('advertising.campaigns.funding.store');

// P005-F : cycle de vie d'un QualifiedEvent (ADR-0010 §4 lignes 3-5).
// event.submit/event.accept/event.reject sont réservées au personnel
// Wasplex anti-fraude/mesure d'attention, jamais au bénéficiaire lui-même
// — voir le raisonnement documenté sur chaque capacité (migrations
// 2026_07_25_100009 à 100011). Même discipline que ci-dessus : groupe
// 'web' hors du groupe 'auth', 401 JSON structuré pour un appel non
// authentifié.
Route::middleware('web')->post('advertising/campaign-versions/{campaignVersion}/qualified-events', [QualifiedEventSubmissionController::class, 'store'])
    ->name('advertising.qualified-events.store');

Route::middleware('web')->post('advertising/qualified-events/{qualifiedEvent}/accept', [QualifiedEventAcceptanceController::class, 'store'])
    ->name('advertising.qualified-events.accept');

Route::middleware('web')->post('advertising/qualified-events/{qualifiedEvent}/reject', [QualifiedEventRejectionController::class, 'store'])
    ->name('advertising.qualified-events.reject');

// P006-A : première route sensible réelle du module Wallet — consultation
// par une personne de son propre solde WP (ecosystem/wallet/01 §3, §7),
// jamais celui d'autrui (wallet.view, migration 2026_07_25_200001). Même
// discipline que ci-dessus : groupe 'web' hors du groupe 'auth', 401 JSON
// structuré pour un appel non authentifié.
Route::middleware('web')->get('wallet/balance', [WalletBalanceController::class, 'show'])
    ->name('wallet.balance.show');

require __DIR__.'/settings.php';
