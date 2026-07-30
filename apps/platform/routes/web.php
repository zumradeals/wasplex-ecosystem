<?php

use App\Http\Controllers\AccountOverviewController;
use App\Http\Controllers\AdvertisingProfileController;
use App\Http\Controllers\GeniusPayWebhookController;
use App\Http\Controllers\HealthController;
use App\Modules\Advertising\Http\Controllers\Admin\AdminAdvertisingModerationController;
use App\Modules\Advertising\Http\Controllers\Admin\AdminCampaignFundingController;
use App\Modules\Advertising\Http\Controllers\Admin\AdminFinanceController;
use App\Modules\Advertising\Http\Controllers\Admin\AdminInterestTaxonomyController;
use App\Modules\Advertising\Http\Controllers\Admin\AdminSectorClassificationController;
use App\Modules\Advertising\Http\Controllers\Admin\AdminVideoDurationController;
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
use App\Modules\Advertising\Http\Controllers\AudienceEstimateController;
use App\Modules\Advertising\Http\Controllers\CampaignController;
use App\Modules\Advertising\Http\Controllers\CampaignFundingController;
use App\Modules\Advertising\Http\Controllers\CampaignFundingInitiationController;
use App\Modules\Advertising\Http\Controllers\CampaignFundingReturnController;
use App\Modules\Advertising\Http\Controllers\CampaignImageUploadController;
use App\Modules\Advertising\Http\Controllers\CampaignReportController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionApprovalController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionFavoriteController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionLikeController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionShareController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionSubmissionController;
use App\Modules\Advertising\Http\Controllers\CampaignVideoUploadController;
use App\Modules\Advertising\Http\Controllers\FeedController;
use App\Modules\Advertising\Http\Controllers\ModerationCaseDecisionController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventAcceptanceController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventRejectionController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventSelfSubmissionController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventSubmissionController;
use App\Modules\Alerts\Http\Controllers\Admin\AdminAlertsController;
use App\Modules\Alerts\Http\Controllers\Admin\AdminCaseDecisionController;
use App\Modules\Alerts\Http\Controllers\Admin\AdminCorrespondenceDecisionController;
use App\Modules\Alerts\Http\Controllers\AlertCaseController;
use App\Modules\Alerts\Http\Controllers\AlertCaseSubmissionController;
use App\Modules\Alerts\Http\Controllers\AlertsOverviewController;
use App\Modules\Alerts\Http\Controllers\CorrespondenceReportController;
use App\Modules\Alerts\Http\Controllers\Institutional\DispatchDecisionController;
use App\Modules\Alerts\Http\Controllers\Institutional\InstitutionalPortalController;
use App\Modules\Alerts\Http\Controllers\SosReportController;
use App\Modules\Governance\Authorization\Http\Controllers\Admin\AdminAccessController;
use App\Modules\Governance\Configuration\Http\Controllers\Admin\AdminConfigurationController;
use App\Modules\Wallet\Balance\Http\Controllers\WalletBalanceController;
use App\Modules\Wallet\Balance\Http\Controllers\WalletOverviewController;
use App\Modules\Wallet\Deposit\Http\Controllers\Admin\AdminWalletDepositController;
use App\Modules\Wallet\Deposit\Http\Controllers\Admin\AdminWalletDepositCredentialsController;
use App\Modules\Wallet\Deposit\Http\Controllers\DepositInitiationController;
use App\Modules\Wallet\Deposit\Http\Controllers\DepositReturnController;
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

// P008-A : détail public d'un dossier Alertes (AMD-0007 §1 : « les
// fonctions essentielles de sécurité, de SOS, de disparition et de
// consultation des alertes critiques restent accessibles gratuitement »).
// Volontairement hors du groupe 'auth' — `AlertCaseController::show`
// résout le sujet de façon tolérante et ne montre le dossier source qu'à
// son auteur, jamais à un visiteur anonyme.
Route::get('alerts/{case}', [AlertCaseController::class, 'show'])->name('alerts.show');

// P008-A : destination mobile « Alertes » (UX-0001 §19, §22) — alertes
// publiées et « Mes déclarations ». Corrigée hors du groupe 'auth' (control
// de navigateur réel, dossier final) : un SOS ne peut être créé sans
// authentification complète (AMD-0007 §2 ; Constitution article 14.2) que
// si l'écran qui porte son formulaire est lui-même atteignable sans
// connexion — le laisser dans 'auth' aurait silencieusement contredit
// `alerts/sos` (public, voir plus bas) en redirigeant tout visiteur non
// connecté vers la page de connexion avant qu'il n'atteigne le bouton SOS.
// `AlertsOverviewController::index` résout déjà le sujet de façon
// tolérante (`my_declarations` reste vide pour un visiteur anonyme) ; la
// déclaration communautaire, elle, exige un compte (`alert_case.submit`,
// portée self) et le reste — seul l'écran qui la porte devient public.
Route::get('alerts', [AlertsOverviewController::class, 'index'])->name('alerts.index');

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

    // AMD-0017 : page de retour après redirection GeniusPay
    // (success_url/error_url) — même raisonnement que 'wallet' ci-dessus :
    // un écran, jamais un point de terminaison API, redirection vers la
    // connexion pour un visiteur non authentifié plutôt qu'un 401 JSON.
    Route::get('wallet/deposits/{deposit}/return', [DepositReturnController::class, 'show'])
        ->name('wallet.deposits.return');

    // Véto du dirigeant 2026-07-30 : page de retour après redirection
    // GeniusPay pour un financement de campagne — même raisonnement que
    // 'wallet/deposits/{deposit}/return' ci-dessus.
    Route::get('advertising/campaigns/{campaign}/self-funding/{campaignFunding}/return', [CampaignFundingReturnController::class, 'show'])
        ->name('advertising.campaigns.self-funding.return');

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
    // (configuration.view, lecture seule), Accès (access.view, lecture
    // seule) et Alertes et institutions (P008-A — voir chaque migration de
    // déclaration pour ce qui reste volontairement hors périmètre). Les
    // autres destinations décidées par le texte (Risques et incidents,
    // Fonds Social, Cartes et partenaires, Audit) n'ont aujourd'hui aucun
    // module ni capacité de lecture déclarée — jamais simulées, seulement
    // annoncées indisponibles côté navigation (DS-0001 §23).
    Route::get('admin/finance', [AdminFinanceController::class, 'index'])->name('admin.finance');
    Route::get('admin/advertising-moderation', [AdminAdvertisingModerationController::class, 'index'])
        ->name('admin.advertising-moderation');
    Route::get('admin/alerts', [AdminAlertsController::class, 'index'])->name('admin.alerts');
    Route::get('admin/configurations', [AdminConfigurationController::class, 'index'])
        ->name('admin.configurations');
    Route::get('admin/access', [AdminAccessController::class, 'index'])->name('admin.access');

    // TD-0008-D : supervision des dépôts Wallet GeniusPay en litige
    // (unknown_reconciliation) et des webhooks à signature invalide
    // (wallet_deposit.review, lecture seule — voir la migration de
    // déclaration pour ce qui reste volontairement hors périmètre).
    Route::get('admin/wallet-deposits', [AdminWalletDepositController::class, 'index'])
        ->name('admin.wallet-deposits');

    // Véto du dirigeant 2026-07-30 (TD-0008-A) : configuration admin des
    // clés GeniusPay (wallet_deposit.manage_credentials, écriture critique —
    // voir la migration de déclaration pour le raisonnement complet).
    Route::get('admin/wallet-deposits/credentials', [AdminWalletDepositCredentialsController::class, 'edit'])
        ->name('admin.wallet-deposit-credentials');
    Route::post('admin/wallet-deposits/credentials', [AdminWalletDepositCredentialsController::class, 'update'])
        ->name('admin.wallet-deposit-credentials.update');

    // Véto du dirigeant 2026-07-30 : supervision des financements de
    // campagne GeniusPay en litige (unknown_reconciliation) et des webhooks
    // à signature invalide (campaign_funding.review, lecture seule) — même
    // doctrine TD-0008-D que 'admin/wallet-deposits' ci-dessus.
    Route::get('admin/campaign-fundings', [AdminCampaignFundingController::class, 'index'])
        ->name('admin.campaign-fundings');

    // Véto du dirigeant 2026-07-30 : gestion admin de la référence des
    // centres d'intérêt du profil publicitaire (advertising.manage_
    // interest_taxonomy) — remplace, pour le personnel habilité, la
    // commande advertising:manage-interest-taxonomy (conservée en secours).
    Route::get('admin/interest-taxonomy', [AdminInterestTaxonomyController::class, 'index'])
        ->name('admin.interest-taxonomy');
    Route::post('admin/interest-taxonomy', [AdminInterestTaxonomyController::class, 'store'])
        ->name('admin.interest-taxonomy.store');
    Route::post('admin/interest-taxonomy/{interestTaxonomyEntry}/toggle', [AdminInterestTaxonomyController::class, 'toggle'])
        ->name('admin.interest-taxonomy.toggle');

    // Lot 4 (instruction explicite du fondateur 2026-07-30) : bornes de
    // durée vidéo autorisée pour une création publicitaire
    // (advertising.manage_video_duration_bounds) — mirroir exact de
    // 'admin/interest-taxonomy' ci-dessus.
    Route::get('admin/video-duration-bounds', [AdminVideoDurationController::class, 'index'])
        ->name('admin.video-duration-bounds');
    Route::post('admin/video-duration-bounds', [AdminVideoDurationController::class, 'store'])
        ->name('admin.video-duration-bounds.store');

    // Lot 9 (véto du dirigeant 2026-07-30) : matrice de classification des
    // secteurs (advertising.manage_sector_classifications) — mirroir
    // d'autorisation exact des deux écrans ci-dessus. Contrairement à
    // 'admin/interest-taxonomy', pas de {resource} sur l'action de
    // retrait : chaque classification identifie déjà sa paire
    // (pays, secteur) par son id propre.
    Route::get('admin/sector-classifications', [AdminSectorClassificationController::class, 'index'])
        ->name('admin.sector-classifications');
    Route::post('admin/sector-classifications', [AdminSectorClassificationController::class, 'store'])
        ->name('admin.sector-classifications.store');
    Route::post('admin/sector-classifications/{sectorClassification}/retire', [AdminSectorClassificationController::class, 'retire'])
        ->name('admin.sector-classifications.retire');

    // P008-A : Portail des institutions Wasplex (ecosystem/institutions/01
    // §10) — distinct du portail personnel Wasplex ci-dessus. Une personne
    // sans appartenance institutionnelle active voit un état refusé
    // honnête, jamais une redirection silencieuse.
    Route::get('institutions/alerts', [InstitutionalPortalController::class, 'index'])
        ->name('institutions.alerts.index');

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

    // Véto du dirigeant 2026-07-30 (AMD-0009) : « Intérêts publicitaires »,
    // section distincte de « Mon espace » (UX-0001 §11) — consentement
    // facultatif, spécifique, versionné et révocable. Même discipline POST
    // (jamais PATCH/DELETE) que 'me/profile' ci-dessus : appelé via
    // `postJson`, pas de soumission de formulaire à spoofer.
    Route::get('me/advertising-profile', [AdvertisingProfileController::class, 'index'])
        ->name('account.advertising-profile');
    Route::post('me/advertising-profile', [AdvertisingProfileController::class, 'update'])
        ->name('account.advertising-profile.update');
    Route::post('me/advertising-profile/withdraw', [AdvertisingProfileController::class, 'destroy'])
        ->name('account.advertising-profile.withdraw');
});

// Non protégée par le middleware 'auth' : une requête non authentifiée doit
// recevoir un 401 JSON structuré via AuthenticatedSubjectHttpResolver /
// AuthorizationFailureResponder (P003-B2/B4), jamais une redirection vers
// une page de connexion (P005-B).
Route::middleware('web')->post('advertising/campaigns', [CampaignController::class, 'store'])
    ->name('advertising.campaigns.store');

// Lot 3 (véto du dirigeant) : aperçu en direct de la taille d'audience
// avant création de campagne — même discipline que ci-dessus (groupe
// 'web' hors du groupe 'auth', 401 JSON structuré). Ne crée jamais rien.
Route::middleware('web')->post('advertising/audience-estimate', [AudienceEstimateController::class, 'store'])
    ->name('advertising.audience-estimate.store');

// Lot 4 (instruction explicite du fondateur 2026-07-30) : upload d'une
// vidéo publicitaire avant création de campagne (multipart, jamais du
// JSON) — même discipline que ci-dessus. Ne crée jamais de campagne.
Route::middleware('web')->post('advertising/campaign-videos', [CampaignVideoUploadController::class, 'store'])
    ->name('advertising.campaign-videos.store');

// Lot 6 (instruction explicite du fondateur 2026-07-30) : upload d'une
// image publicitaire — mirroir exact de la route vidéo ci-dessus.
Route::middleware('web')->post('advertising/campaign-images', [CampaignImageUploadController::class, 'store'])
    ->name('advertising.campaign-images.store');

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

// Véto du dirigeant 2026-07-30 : financement de campagne en libre-service
// par l'annonceur via GeniusPay (campaign.fund_self, portée self) —
// distinct de la route ci-dessus (personnel finance Wasplex, paiements hors
// GeniusPay). Même discipline : groupe 'web' hors du groupe 'auth', 401
// JSON structuré pour un appel non authentifié. Ne crédite jamais de valeur
// elle-même — seul le webhook signé le fait (webhooks.geniuspay.store).
Route::middleware('web')->post('advertising/campaigns/{campaign}/self-funding', [CampaignFundingInitiationController::class, 'store'])
    ->name('advertising.campaigns.self-funding.store');

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

// AMD-0017 : initiation d'un dépôt (wallet.deposit, portée self) — pilote
// Côte d'Ivoire via GeniusPay (ecosystem/wallet/05). Même discipline que
// ci-dessus : groupe 'web' hors du groupe 'auth', 401 JSON structuré pour
// un appel non authentifié. Ne crédite jamais de valeur elle-même — voir
// la route webhook ci-dessous, seule habilitée à le faire.
Route::middleware('web')->post('wallet/deposits', [DepositInitiationController::class, 'store'])
    ->name('wallet.deposits.store');

// AMD-0017 : webhook entrant GeniusPay (ADR-0007 §11). Authentifié par
// signature HMAC du corps brut, jamais par une capacité ni une session —
// exempté de CSRF (bootstrap/app.php, `validateCsrfTokens(except: ...)`)
// puisqu'il s'agit d'un appel serveur à serveur sans jeton de formulaire.
// Véto du dirigeant 2026-07-30 : une seule URL de webhook est configurée
// côté GeniusPay en production — `GeniusPayWebhookController` répartit
// chaque réception entre dépôt Wallet et financement de campagne (voir son
// docblock), remplace l'ancien `DepositWebhookController`.
Route::middleware('web')->post('webhooks/geniuspay', [GeniusPayWebhookController::class, 'store'])
    ->name('webhooks.geniuspay.store');

// P008-A : déclaration communautaire (alert_case.submit, portée self).
// Même discipline que ci-dessus : groupe 'web' hors du groupe 'auth', 401
// JSON structuré pour un appel non authentifié.
Route::middleware('web')->post('alerts/community', [AlertCaseSubmissionController::class, 'store'])
    ->name('alerts.community.store');

// P008-A : SOS (AMD-0007 §2 ; ecosystem/alertes/02 §2, §22) — aucune
// authentification requise, aucune capacité. Seule protection : une
// limite de fréquence par adresse IP (`throttle`), en plus de la
// validation de forme. Volontairement dans le groupe 'web' (session/CSRF
// disponibles pour un visiteur déjà connecté) mais jamais dans le groupe
// 'auth' : un SOS anonyme ne doit jamais être redirigé vers la connexion.
Route::middleware(['web', 'throttle:5,1'])->post('alerts/sos', [SosReportController::class, 'store'])
    ->name('alerts.sos.store');

// P008-A : correspondance proposée sur un dossier publié
// (alert_match.propose, portée self). Même discipline que ci-dessus :
// groupe 'web' hors du groupe 'auth', 401 JSON structuré pour un appel non
// authentifié.
Route::middleware('web')->post('alerts/{case}/correspondence', [CorrespondenceReportController::class, 'store'])
    ->name('alerts.correspondence.store');

// P008-A : décision institutionnelle sur une transmission
// (ecosystem/institutions/01 §6). Même discipline que ci-dessus : groupe
// 'web' hors du groupe 'auth', 401 JSON structuré pour un appel non
// authentifié.
Route::middleware('web')->post('institutions/alerts/dispatches/{dispatch}/decisions', [DispatchDecisionController::class, 'store'])
    ->name('institutions.alerts.dispatches.decisions.store');

// P008-A : décisions de modération admin sur un dossier communautaire et
// sur une correspondance (mission §17). Même discipline que ci-dessus :
// groupe 'web' hors du groupe 'auth', 401 JSON structuré pour un appel non
// authentifié.
Route::middleware('web')->post('admin/alerts/cases/{case}/decisions', [AdminCaseDecisionController::class, 'store'])
    ->name('admin.alerts.cases.decisions.store');

Route::middleware('web')->post('admin/alerts/correspondence-reports/{correspondenceReport}/decisions', [AdminCorrespondenceDecisionController::class, 'store'])
    ->name('admin.alerts.correspondence-reports.decisions.store');

require __DIR__.'/settings.php';
