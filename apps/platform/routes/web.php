<?php

use App\Http\Controllers\HealthController;
use App\Modules\Advertising\Http\Controllers\CampaignController;
use App\Modules\Advertising\Http\Controllers\CampaignReportController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionApprovalController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionSubmissionController;
use App\Modules\Advertising\Http\Controllers\ModerationCaseDecisionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('/health', HealthController::class)->name('health');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Non protégée par le middleware 'auth' : une requête non authentifiée doit
// recevoir un 401 JSON structuré via AuthenticatedSubjectHttpResolver /
// AuthorizationFailureResponder (P003-B2/B4), jamais une redirection vers
// une page de connexion (P005-B).
Route::middleware('web')->post('advertising/campaigns', [CampaignController::class, 'store'])
    ->name('advertising.campaigns.store');

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

require __DIR__.'/settings.php';
