<?php

use App\Http\Controllers\Admin\DeadlineController;
use App\Http\Controllers\Admin\CandidatureAdminController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\CommissionMemberController;
use App\Http\Controllers\Admin\CommissionUserController;
use App\Http\Controllers\Admin\DocumentAdminController;
use App\Http\Controllers\Admin\DossierAdminController;
use App\Http\Controllers\Admin\SettingsAdminController;
use App\Http\Controllers\Admin\AnalyticsAdminController;
use App\Http\Controllers\Admin\SpecialiteAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Candidat\ActiviteController;
use App\Http\Controllers\Candidat\CandidatureController;
use App\Http\Controllers\Candidat\DocumentController;
use App\Http\Controllers\Candidat\EnseignementController;
use App\Http\Controllers\Candidat\PfeController;
use App\Http\Controllers\Candidat\ProfileController;
use App\Http\Controllers\Commission\CommissionDossierController;
use App\Http\Controllers\Commission\CommissionEvaluationController;
use App\Http\Controllers\President\PresidentDossierController;
use App\Http\Controllers\President\PresidentResultController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('jwt');
    Route::get('/me', [AuthController::class, 'me'])->middleware('jwt');
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);
});

/*
|--------------------------------------------------------------------------
| Health Check Routes (for role-based access verification)
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt', 'role:'.User::ROLE_CANDIDAT])->get('/candidat/health', function () {
    return response()->json(['ok' => true]);
});

Route::middleware(['jwt', 'role:'.User::ROLE_SYSTEME])->get('/systeme/health', function () {
    return response()->json(['ok' => true]);
});

Route::middleware(['jwt', 'role:'.User::ROLE_ADMIN])->get('/admin/health', function () {
    return response()->json(['ok' => true]);
});

Route::middleware(['jwt', 'role:'.User::ROLE_COMMISSION])->get('/commission/health', function () {
    return response()->json(['ok' => true]);
});

Route::middleware(['jwt', 'role:'.User::ROLE_PRESIDENT])->get('/president/health', function () {
    return response()->json(['ok' => true]);
});

/*
|--------------------------------------------------------------------------
| Candidat Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt', 'role:'.User::ROLE_CANDIDAT])
    ->prefix('candidat')
    ->group(function () {
        // Candidature management
        Route::get('/candidature', [CandidatureController::class, 'index']);
        Route::get('/candidature/status', [CandidatureController::class, 'status']);
        Route::post('/candidature/submit', [CandidatureController::class, 'submit']);

        // Step 1: Profile
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/profile', [ProfileController::class, 'store']);
        Route::patch('/profile/autosave', [ProfileController::class, 'autosave']);

        // Step 2: Enseignements
        Route::get('/enseignements', [EnseignementController::class, 'index']);
        Route::post('/enseignements', [EnseignementController::class, 'store']);
        Route::delete('/enseignements/{id}', [EnseignementController::class, 'destroy']);
        Route::post('/enseignements/bulk', [EnseignementController::class, 'bulkSave']);

        // Step 3: PFE
        Route::get('/pfes', [PfeController::class, 'index']);
        Route::post('/pfes', [PfeController::class, 'store']);
        Route::put('/pfes/{id}', [PfeController::class, 'update']);
        Route::delete('/pfes/{id}', [PfeController::class, 'destroy']);
        Route::post('/pfes/bulk', [PfeController::class, 'bulkSave']);

        // Steps 4-5: Activités
        Route::get('/activites', [ActiviteController::class, 'index']);
        Route::post('/activites', [ActiviteController::class, 'save']);
        Route::post('/activites/bulk', [ActiviteController::class, 'bulkSave']);
        Route::delete('/activites/{id}', [ActiviteController::class, 'destroy']);
        Route::get('/activites/categories', [ActiviteController::class, 'categories']);

        // Documents
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'upload']);
        Route::post('/documents/activite/{activiteId}', [DocumentController::class, 'uploadForActivite']);
        Route::get('/documents/{id}/preview', [DocumentController::class, 'preview']);
        Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt', 'role:'.User::ROLE_ADMIN])
    ->prefix('admin')
    ->group(function () {
        // Analytics
        Route::get('/analytics/overview', [AnalyticsAdminController::class, 'overview']);

        // Settings
        Route::get('/settings', [SettingsAdminController::class, 'index']);
        Route::put('/settings', [SettingsAdminController::class, 'update']);

        // Dossiers (candidatures) - paginated
        Route::get('/dossiers', [DossierAdminController::class, 'index']);
        Route::get('/dossiers/{candidature}', [DossierAdminController::class, 'show']);
        Route::get('/dossiers/{candidature}/documents', [DossierAdminController::class, 'documents']);

        // Documents download (admin)
        Route::get('/documents/{document}/download', [DocumentAdminController::class, 'download']);

        // Users management
        Route::get('/users', [UserAdminController::class, 'index']);
        Route::post('/users', [UserAdminController::class, 'store']);
        Route::put('/users/{user}', [UserAdminController::class, 'update']);
        Route::delete('/users/{user}', [UserAdminController::class, 'destroy']);

        // Candidatures overview + export
        Route::get('/candidatures', [CandidatureAdminController::class, 'index']);
        Route::get('/candidatures/export', [CandidatureAdminController::class, 'export']);

        // Deadlines CRUD
        Route::get('/deadlines', [DeadlineController::class, 'index']);
        Route::get('/deadlines/active', [DeadlineController::class, 'active']);
        Route::get('/deadlines/{deadline}', [DeadlineController::class, 'show']);
        Route::post('/deadlines', [DeadlineController::class, 'store']);
        Route::put('/deadlines/{deadline}', [DeadlineController::class, 'update']);
        Route::delete('/deadlines/{deadline}', [DeadlineController::class, 'destroy']);
        Route::post('/deadlines/{deadline}/remind', [DeadlineController::class, 'remind']);

        // Commissions
        Route::get('/commissions', [CommissionController::class, 'index']);
        Route::post('/commissions', [CommissionController::class, 'store']);
        Route::get('/commissions/{commission}', [CommissionController::class, 'show']);
        Route::delete('/commissions/{commission}', [CommissionController::class, 'destroy']);

        // Spécialités
        Route::get('/specialites', [SpecialiteAdminController::class, 'index']);
        Route::post('/specialites', [SpecialiteAdminController::class, 'store']);

        // Commission members
        Route::post('/commissions/{commission}/members', [CommissionMemberController::class, 'store']);
        Route::put('/commissions/{commission}/members/{member}/president', [CommissionMemberController::class, 'setPresident']);
        Route::delete('/commissions/{commission}/members/{member}', [CommissionMemberController::class, 'destroy']);

        // Commission user assignments (link app users to commissions)
        Route::get('/commissions/{commission}/users', [CommissionUserController::class, 'indexForCommission']);
        Route::get('/users/{user}/commission', [CommissionUserController::class, 'showForUser']);
        Route::put('/users/{user}/commission', [CommissionUserController::class, 'assignForUser']);
        Route::delete('/users/{user}/commission', [CommissionUserController::class, 'removeForUser']);
    });

/*
|--------------------------------------------------------------------------
| Commission Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt', 'role:'.User::ROLE_COMMISSION.','.User::ROLE_PRESIDENT])
    ->prefix('commission')
    ->group(function () {
        Route::get('/dossiers', [CommissionDossierController::class, 'index']);
        Route::get('/dossiers/{candidature}', [CommissionDossierController::class, 'show']);
        Route::get('/dossiers/{candidature}/documents', [CommissionDossierController::class, 'documents']);
        Route::get('/documents/{document}/download', [CommissionDossierController::class, 'downloadDocument']);

        Route::get('/dossiers/{candidature}/notes', [CommissionEvaluationController::class, 'index']);
        Route::put('/dossiers/{candidature}/notes', [CommissionEvaluationController::class, 'upsert']);
    });

/*
|--------------------------------------------------------------------------
| President Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt', 'role:'.User::ROLE_PRESIDENT])
    ->prefix('president')
    ->group(function () {
        Route::get('/dossiers', [PresidentDossierController::class, 'index']);
        Route::get('/dossiers/{candidature}', [PresidentDossierController::class, 'show']);
        Route::get('/dossiers/{candidature}/documents', [PresidentDossierController::class, 'documents']);
        Route::get('/documents/{document}/download', [PresidentDossierController::class, 'downloadDocument']);

        Route::get('/dossiers/{candidature}/notes', [CommissionEvaluationController::class, 'index']);
        Route::put('/dossiers/{candidature}/notes', [CommissionEvaluationController::class, 'upsert']);

        Route::get('/dossiers/{candidature}/result', [PresidentResultController::class, 'show']);
        Route::put('/dossiers/{candidature}/result', [PresidentResultController::class, 'upsert']);
        Route::post('/dossiers/{candidature}/validate', [PresidentResultController::class, 'validateResult']);
    });

/*
|--------------------------------------------------------------------------
| Public Routes (for all authenticated users)
|--------------------------------------------------------------------------
*/
Route::middleware('jwt')->group(function () {
    // Get active deadlines (visible to all authenticated users)
    Route::get('/deadlines/active', [DeadlineController::class, 'active']);
});
