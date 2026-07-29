<?php

use App\Http\Controllers\AccountOverviewController;
use App\Http\Controllers\HealthController;
use App\Modules\Advertising\Http\Controllers\Admin\AdminAdvertisingModerationController;
use App\Modules\Advertising\Http\Controllers\Admin\AdminFinanceController;
use App\Modules\Advertising\Http\Controllers\Admin\ModerationOverviewController;
use App\Modules\Advertising\Http\Controllers\AdvertiserProfileController;
use App\Modules\Advertising\Http\Controllers\AdvertisingAudiencesController;
use App\Modules\Advertising\Http\Controllers\AdvertisingBillingController;
use App\Modules\Advertising\Http\Controllers\AdvertisingBudgetController;
use App\Modules\Advertising\Http\Controllers\AdvertisingCampaignCreateController;
use App\Modules\Advertising\Http\Controllers\AdvertisingCampaignsController;
use App\Modules\Advertising\Http\Controllers\AdvertisingCreationsController;
use App\Modules\Advertising\Http\Controllers\AdvertisingOrganizationController;
use App\Modules\Advertising\Http\Controllers\AdvertisingOverviewController;
use App\Modules\Advertising\Http\Controllers\AdvertisingReportsController;
use App\Modules\Advertising\Http\Controllers\CampaignController;
use App\Modules\Advertising\Http\Controllers\CampaignFundingController;
use App\Modules\Advertising\Http\Controllers\CampaignReportController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionApprovalController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionFavoriteController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionLikeController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionShareController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionSubmissionController;
use App\Modules\Advertising\Http\Controllers\FeedController;
use App\Modules\Advertising\Http\Controllers\ModerationCaseDecisionController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventAcceptanceController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventRejectionController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventSelfSubmissionController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventSubmissionController;
use App\Modules\Governance\Authorization\Http\Controllers\Admin\AdminAccessController;
use App\Modules\Governance\Configuration\Http\Controllers\Admin\AdminConfigurationController;
use App\Modules\Wallet\Balance\Http\Controllers\WalletBalanceController;
use App\Modules\Wallet\Balance\Http\Controllers\WalletOverviewController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Accueil public minimal (L01) : explique Wasplex en 30 secondes et mène
// directement à la connexion — jamais de page marketing. Un compte déjà
// authentifié est redirigé directement vers son écran d'accueil (pas de
// second passage par l'explication), même principe que le groupe
// 'auth'/'verified' ci-dessous.
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('welcome');
})->name('home');

Route::get('/health', HealthController::class)->name('health');

Route::middleware(['auth', 'verified'])->group(function () {
    // W4 (L03) : le Feed remplace le placeholder générique du kit de
    // démarrage — point d'entrée quotidien de l'utilisateur (voir
    // FeedController).
    Route::get('dashboard', [FeedController::class, 'index'])->name('dashboard');

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

    // P007-W2 : portail annonceur complet (UX-0001 §8 « Navigation
    // professionnelle » — Annonceur) — chaque écran reprend la même
    // autorisation campaign.view (portée self) que 'advertising'
    // ci-dessus (voir Concerns\ResolvesAdvertiserWorkspace), un écran par
    // destination de navigation décidée, jamais une route API.
    Route::get('advertising/campaigns', [AdvertisingCampaignsController::class, 'index'])
        ->name('advertising.campaigns.index');
    Route::get('advertising/campaigns/create', [AdvertisingCampaignCreateController::class, 'create'])
        ->name('advertising.campaigns.create');
    Route::get('advertising/audiences', [AdvertisingAudiencesController::class, 'index'])
        ->name('advertising.audiences');
    Route::get('advertising/creations', [AdvertisingCreationsController::class, 'index'])
        ->name('advertising.creations');
    Route::get('advertising/budget', [AdvertisingBudgetController::class, 'index'])
        ->name('advertising.budget');
    Route::get('advertising/reports', [AdvertisingReportsController::class, 'index'])
        ->name('advertising.reports');
    Route::get('advertising/billing', [AdvertisingBillingController::class, 'index'])
        ->name('advertising.billing');
    Route::get('advertising/organization', [AdvertisingOrganizationController::class, 'index'])
        ->name('advertising.organization');

    // Fermeture de la boucle de gain (P0, demande Koné 2026-07-26) : file
    // personnel Wasplex pour approuver une CampaignVersion
    // (campaign.approve), financer une campagne (campaign.fund) et
    // accepter/refuser un QualifiedEvent (event.accept/event.reject) — les
    // trois actions qui étaient jusqu'ici backend-only, sans aucun écran
    // (voir ModerationOverviewController). Chaque section reste gouvernée
    // par sa propre capacité ; aucun rôle « admin » générique n'existe.
    // Volontairement dans le groupe 'auth' : c'est un écran, pas un point
    // de terminaison API — même raisonnement que 'advertising'/'wallet'.
    Route::get('admin/moderation', [ModerationOverviewController::class, 'index'])->name('admin.moderation');

    // P0-Admin : portail personnel Wasplex complet (UX-0001 §8
    // « Administration Wasplex »). Destinations réellement backées :
    // Finance et rapprochement (campaign.fund), Publicité et modération
    // (campaign.approve / campaign.moderate), Configurations
    // (configuration.view, lecture seule) et Accès (access.view, lecture
    // seule — voir chaque migration de déclaration pour ce qui reste
    // volontairement hors périmètre). Les autres destinations décidées
    // par le texte (Risques et incidents, Alertes et institutions, Fonds
    // Social, Cartes et partenaires, Audit) n'ont aujourd'hui aucun
    // module ni capacité de lecture déclarée — jamais simulées, seulement
    // annoncées indisponibles côté navigation (DS-0001 §23).
    Route::get('admin/finance', [AdminFinanceController::class, 'index'])->name('admin.finance');
    Route::get('admin/advertising-moderation', [AdminAdvertisingModerationController::class, 'index'])
        ->name('admin.advertising-moderation');
    Route::get('admin/configurations', [AdminConfigurationController::class, 'index'])
        ->name('admin.configurations');
    Route::get('admin/access', [AdminAccessController::class, 'index'])->name('admin.access');

    // W4 : « Mon espace » — cinquième destination de la navigation mobile,
    // restée désactivée depuis la refonte W4 faute d'écran. Équivalent
    // mobile-first de Settings\ProfileController (mêmes colonnes users,
    // mêmes règles de validation), jamais de nouveau champ de profil (voir
    // AccountOverviewController).
    Route::get('me', [AccountOverviewController::class, 'index'])->name('account.overview');

    // POST, pas PATCH : ces écrans mobiles appellent l'action via `fetch`
    // JSON (postJson, lib/api.ts) — le spoofing `_method` de Laravel ne
    // s'applique qu'aux soumissions de formulaire, jamais à un corps JSON
    // (même raisonnement que les autres routes d'action mobile de ce
    // fichier, toutes en POST).
    Route::post('me/profile', [AccountOverviewController::class, 'update'])->name('account.profile.update');
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

// W2 : auto-soumission par le bénéficiaire de sa propre preuve
// d'attention qualifiée (event.self_submit, prix résolu serveur via le
// registre Configuration) — même discipline que ci-dessus : groupe 'web'
// hors du groupe 'auth', 401 JSON structuré pour un appel non authentifié.
Route::middleware('web')->post('advertising/campaign-versions/{campaignVersion}/qualified-events/self-submit', [QualifiedEventSelfSubmissionController::class, 'store'])
    ->name('advertising.qualified-events.self-submit');

Route::middleware('web')->post('advertising/qualified-events/{qualifiedEvent}/accept', [QualifiedEventAcceptanceController::class, 'store'])
    ->name('advertising.qualified-events.accept');

Route::middleware('web')->post('advertising/qualified-events/{qualifiedEvent}/reject', [QualifiedEventRejectionController::class, 'store'])
    ->name('advertising.qualified-events.reject');

// Lot 3 Phase A : menu vertical du Feed (j'aime, favori, intention de
// partage — décision de Koné 2026-07-26). Signaux sociaux purs, aucun
// effet financier (campaign_version.like/favorite/share, migrations
// 2026_07_26_200004 à 200006). Même discipline que ci-dessus : groupe
// 'web' hors du groupe 'auth', 401 JSON structuré pour un appel non
// authentifié.
Route::middleware('web')->post('advertising/campaign-versions/{campaignVersion}/like', [CampaignVersionLikeController::class, 'store'])
    ->name('advertising.campaign-versions.like');

Route::middleware('web')->post('advertising/campaign-versions/{campaignVersion}/favorite', [CampaignVersionFavoriteController::class, 'store'])
    ->name('advertising.campaign-versions.favorite');

Route::middleware('web')->post('advertising/campaign-versions/{campaignVersion}/share', [CampaignVersionShareController::class, 'store'])
    ->name('advertising.campaign-versions.share');

// P006-A : première route sensible réelle du module Wallet — consultation
// par une personne de son propre solde WP (ecosystem/wallet/01 §3, §7),
// jamais celui d'autrui (wallet.view, migration 2026_07_25_200001). Même
// discipline que ci-dessus : groupe 'web' hors du groupe 'auth', 401 JSON
// structuré pour un appel non authentifié.
Route::middleware('web')->get('wallet/balance', [WalletBalanceController::class, 'show'])
    ->name('wallet.balance.show');

require __DIR__.'/settings.php';
