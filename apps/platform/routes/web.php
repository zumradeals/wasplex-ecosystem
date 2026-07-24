<?php

use App\Http\Controllers\HealthController;
use App\Modules\Advertising\Http\Controllers\CampaignController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionApprovalController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionSubmissionController;
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

require __DIR__.'/settings.php';
