<?php

use App\Http\Controllers\HealthController;
use App\Modules\Advertising\Http\Controllers\CampaignController;
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

require __DIR__.'/settings.php';
