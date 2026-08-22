<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\GymController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Http\Controllers\Admin\SaasPlanController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

// Auth
Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/2fa/verify', [AdminAuthController::class, 'verifyTwoFactor'])->middleware('throttle:5,1')->name('admin.2fa.verify');
Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Protected
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminAuthController::class, 'dashboard'])->name('admin.dashboard');
    
    // Gyms
    Route::get('/gyms', [GymController::class, 'index'])->name('admin.gyms.index');
    Route::post('/gyms', [GymController::class, 'store'])->name('admin.gyms.store');
    Route::get('/gyms/{id}', [GymController::class, 'show'])->name('admin.gyms.show');
    Route::put('/gyms/{id}', [GymController::class, 'update'])->name('admin.gyms.update');
    Route::post('/gyms/{id}/toggle', [GymController::class, 'toggle'])->name('admin.gyms.toggle');
    Route::post('/gyms/{id}/delete-otp', [GymController::class, 'sendDeleteOtp'])->name('admin.gyms.deleteOtp');
    Route::delete('/gyms/{id}', [GymController::class, 'destroy'])->name('admin.gyms.destroy');
    
    // FIX #1: Update subscription
    Route::post('/gyms/{id}/subscription', [GymController::class, 'updateSubscription'])->name('admin.gyms.updateSubscription');
    Route::post('/gyms/{id}/acquisition', [GymController::class, 'updateAcquisition'])->name('admin.gyms.updateAcquisition');
    
    // FIX #2: Login as gym owner
    Route::post('/gyms/{id}/login-as', [GymController::class, 'loginAs'])->name('admin.gyms.loginAs');
    
    // Old subscription plans
    Route::get('/plans', [SubscriptionPlanController::class, 'index'])->name('admin.plans.index');
    Route::post('/plans', [SubscriptionPlanController::class, 'store'])->name('admin.plans.store');
    Route::put('/plans/{id}', [SubscriptionPlanController::class, 'update'])->name('admin.plans.update');
    Route::delete('/plans/{id}', [SubscriptionPlanController::class, 'destroy'])->name('admin.plans.destroy');

    // SaaS Bronze / Silver / Gold Plans
    Route::get('/saas-plans', [SaasPlanController::class, 'index'])->name('admin.saas-plans.index');
    Route::post('/saas-plans/seed-defaults', [SaasPlanController::class, 'seedDefaultsNow'])->name('admin.saas-plans.seed-defaults');
    Route::put('/saas-plans/tiers/{id}', [SaasPlanController::class, 'updateTier'])->name('admin.saas-plans.tiers.update');
    Route::put('/saas-plans/features/{id}', [SaasPlanController::class, 'updateFeature'])->name('admin.saas-plans.features.update');
    Route::post('/saas-plans/tiers/{tierId}/card-features', [SaasPlanController::class, 'storeCardFeature'])->name('admin.saas-plans.card-features.store');
    Route::post('/saas-plans/tiers/{tierId}/card-features/sync', [SaasPlanController::class, 'syncCardFeatures'])->name('admin.saas-plans.card-features.sync');
    Route::put('/saas-plans/card-features/{id}', [SaasPlanController::class, 'updateCardFeature'])->name('admin.saas-plans.card-features.update');
    Route::delete('/saas-plans/card-features/{id}', [SaasPlanController::class, 'destroyCardFeature'])->name('admin.saas-plans.card-features.destroy');
    Route::post('/saas-plans/tiers/{tierId}/prices', [SaasPlanController::class, 'storePrice'])->name('admin.saas-plans.prices.store');
    Route::put('/saas-plans/prices/{id}', [SaasPlanController::class, 'updatePrice'])->name('admin.saas-plans.prices.update');
    Route::delete('/saas-plans/prices/{id}', [SaasPlanController::class, 'destroyPrice'])->name('admin.saas-plans.prices.destroy');
    
    // Revenue
    Route::get('/revenue', [RevenueController::class, 'index'])->name('admin.revenue.index');
    Route::get('/revenue/payments', [RevenueController::class, 'payments'])->name('admin.revenue.payments');

    // Payment Gateways
    Route::get('/payment-gateways', [PaymentGatewayController::class, 'index'])->name('admin.payment-gateways.index');
    Route::post('/payment-gateways/{gatewayKey}', [PaymentGatewayController::class, 'update'])->name('admin.payment-gateways.update');
    Route::post('/payment-gateways/{gatewayKey}/default', [PaymentGatewayController::class, 'setDefault'])->name('admin.payment-gateways.default');
    Route::post('/payment-gateways/{gatewayKey}/test', [PaymentGatewayController::class, 'test'])->name('admin.payment-gateways.test');
    
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('admin.notifications.index');
    Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast'])->name('admin.notifications.broadcast');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('admin.notifications.destroy');
    Route::delete('/notifications', [NotificationController::class, 'destroyAll'])->name('admin.notifications.destroyAll');
    
    // System Update
    Route::get('/system-update', [SystemUpdateController::class, 'index'])->name('admin.system-update.index');
    Route::post('/system-update/run', [SystemUpdateController::class, 'run'])->name('admin.system-update.run');

    // Settings
    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
    Route::post('/settings/smtp', [AdminSettingsController::class, 'updateSmtp'])->name('admin.settings.smtp');
    Route::post('/settings/smtp/test', [AdminSettingsController::class, 'testSmtp'])->name('admin.settings.smtp.test');
    Route::post('/settings/platform', [AdminSettingsController::class, 'updatePlatform'])->name('admin.settings.platform');
    Route::post('/settings/maintenance', [AdminSettingsController::class, 'updateMaintenance'])->name('admin.settings.maintenance');
    Route::post('/settings/security', [AdminSettingsController::class, 'updateSecurity'])->name('admin.settings.security');
    Route::post('/settings/operation-mode', [AdminSettingsController::class, 'updateOperationMode'])->name('admin.settings.operation-mode');
    Route::post('/settings/profile', [AdminSettingsController::class, 'updateProfile'])->name('admin.settings.profile');
    Route::post('/settings/password', [AdminSettingsController::class, 'updatePassword'])->name('admin.settings.password');
    Route::post('/settings/google-auth/setup', [AdminSettingsController::class, 'setupGoogleAuthenticator'])->name('admin.settings.google-auth.setup');
    Route::post('/settings/google-auth/confirm', [AdminSettingsController::class, 'confirmGoogleAuthenticator'])->name('admin.settings.google-auth.confirm');
    Route::post('/settings/google-auth/disable', [AdminSettingsController::class, 'disableGoogleAuthenticator'])->name('admin.settings.google-auth.disable');

    // SUPERADMIN: Test WhatsApp API
    Route::post('/settings/whatsapp/test', [AdminSettingsController::class, 'testWhatsApp'])->name('admin.settings.whatsapp.test');
    Route::post('/settings/whatsapp/test-connection', [AdminSettingsController::class, 'testWhatsAppConnection'])->name('admin.settings.whatsapp.test-connection');
    Route::post('/settings/whatsapp/diagnose', [AdminSettingsController::class, 'diagnoseWhatsApp'])->name('admin.settings.whatsapp.diagnose');
    Route::post('/settings/fcm/test', [AdminSettingsController::class, 'testFcm'])->name('admin.settings.fcm.test');
    
    // Bug Reports Management (Super Admin)
    Route::get('/bugs', [\App\Http\Controllers\Admin\BugReportController::class, 'webIndex'])->name('admin.bugs.index');
    Route::get('/bugs/{id}', [\App\Http\Controllers\Admin\BugReportController::class, 'webShow'])->name('admin.bugs.show');
    Route::post('/bugs/bulk', [\App\Http\Controllers\Admin\BugReportController::class, 'bulkAction'])->name('admin.bugs.bulk');
    Route::post('/bugs/{id}/update', [\App\Http\Controllers\Admin\BugReportController::class, 'webUpdate'])->name('admin.bugs.update');
});

// Return to admin (no middleware - works for any authenticated user)
Route::get('/return-to-admin', [GymController::class, 'returnToAdmin'])->name('admin.returnToAdmin');
