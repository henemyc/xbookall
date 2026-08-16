<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Panel\PanelAuthController;
use App\Http\Controllers\Panel\PanelMemberController;
use App\Http\Controllers\Panel\PanelAttendanceController;
use App\Http\Controllers\Panel\PanelInvoiceController;
use App\Http\Controllers\Panel\PanelReportController;
use App\Http\Controllers\Panel\PanelTrainerController;
use App\Http\Controllers\Panel\PanelExpenseController;
use App\Http\Controllers\Panel\PanelNoticeController;
use App\Http\Controllers\Panel\PanelLockerController;
use App\Http\Controllers\Panel\PanelPlanController;
use App\Http\Controllers\Panel\PanelClassController;
use App\Http\Controllers\Panel\PanelEventController;
use App\Http\Controllers\Panel\PanelProductController;
use App\Http\Controllers\Panel\PanelSubscriptionController;
use App\Http\Controllers\Panel\PanelSettingsController;
use App\Http\Controllers\Panel\PanelTransactionController;
use App\Http\Controllers\Panel\PanelQRController;
use App\Http\Controllers\Panel\PanelWorkoutController;
use App\Http\Controllers\Panel\PanelDietController;
use App\Http\Controllers\Panel\PanelStaffRoleController;
use App\Http\Controllers\Panel\PanelStaffUserController;
use App\Http\Controllers\Panel\PanelStaffActivityController;
use App\Http\Controllers\WebLoginController;

/*
|--------------------------------------------------------------------------
| Panel Routes
|--------------------------------------------------------------------------
*/

// Auth
Route::get('/login', [PanelAuthController::class, 'showLogin'])->name('panel.login');
Route::post('/login', [PanelAuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [PanelAuthController::class, 'logout'])->name('panel.logout');
Route::get('/login/qr-token', [WebLoginController::class, 'create'])->name('panel.login.qr.token');
Route::get('/login/qr-status', [WebLoginController::class, 'status'])->name('panel.login.qr.status');

// Debug/test routes are disabled in production unless explicitly enabled.
if (!app()->environment('production') || env('ENABLE_DEBUG_ROUTES', false)) {
    Route::get('/test', function () {
        return response()->json(['success' => true, 'message' => 'Panel API working']);
    });

    Route::post('/test-submit', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Form submitted!',
            'data' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method(),
        ]);
    });

    Route::get('/test-ajax', function () {
        return view('panel.test-ajax');
    });

    Route::get('/toast-test', function () {
        return view('panel.toast-test');
    });
}

// Protected routes
// IMPORTANT: We use ONLY the 'panel' middleware (NOT 'auth').
// PanelMiddleware does full auth check + always redirects using direct URL string
// (/panel/login). This is the main reason we no longer get "Route [login] not defined".
Route::middleware(['panel'])->group(function () {
    Route::get('/', [PanelAuthController::class, 'dashboard'])
        ->middleware('staff.permission:dashboard.view')
        ->name('panel.dashboard');

    // Members
    Route::get('/members', [PanelMemberController::class, 'index'])->middleware('staff.permission:members.view')->name('panel.members.index');
    Route::get('/members/create', [PanelMemberController::class, 'create'])->middleware('staff.permission:members.create')->name('panel.members.create');
    Route::get('/members/import/template', [PanelMemberController::class, 'importTemplate'])->middleware('staff.permission:members.create')->name('panel.members.import.template');
    Route::post('/members/import', [PanelMemberController::class, 'importMembers'])->middleware('staff.permission:members.create')->name('panel.members.import');
    Route::post('/members', [PanelMemberController::class, 'store'])->middleware('staff.permission:members.create')->name('panel.members.store');
    Route::get('/members/{id}', [PanelMemberController::class, 'show'])->middleware('staff.permission:members.view')->name('panel.members.show');
    Route::get('/members/{id}/edit', [PanelMemberController::class, 'edit'])->middleware('staff.permission:members.edit')->name('panel.members.edit');
    Route::put('/members/{id}', [PanelMemberController::class, 'update'])->middleware('staff.permission:members.edit')->name('panel.members.update');
    Route::delete('/members/{id}', [PanelMemberController::class, 'destroy'])->middleware('staff.permission:members.delete')->name('panel.members.destroy');
    Route::delete('/members/{id}/freeze/{freezeId}', [PanelMemberController::class, 'deleteFreeze'])->middleware('staff.permission:members.freeze')->name('panel.members.deleteFreeze');

    // Attendance
    Route::get('/attendance', [PanelAttendanceController::class, 'index'])->middleware('staff.permission:attendance.view')->name('panel.attendance.index');
    Route::get('/attendance/calendar', [PanelAttendanceController::class, 'calendar'])->middleware('staff.permission:attendance.view')->name('panel.attendance.calendar');

    // Invoices
    Route::get('/invoices', [PanelInvoiceController::class, 'index'])->middleware('staff.permission:invoices.view')->name('panel.invoices.index');
    Route::post('/invoices', [PanelInvoiceController::class, 'store'])->middleware('staff.permission:invoices.create')->name('panel.invoices.store');
    Route::get('/invoices/{id}', [PanelInvoiceController::class, 'show'])->middleware('staff.permission:invoices.view')->name('panel.invoices.show');
    Route::post('/invoices/{id}/payment', [PanelInvoiceController::class, 'addPayment'])->middleware('staff.permission:invoices.payment')->name('panel.invoices.addPayment');
    Route::delete('/invoices/{id}', [PanelInvoiceController::class, 'destroy'])->middleware('staff.permission:invoices.delete')->name('panel.invoices.destroy');

    // Reports
    Route::get('/reports', [PanelReportController::class, 'index'])->middleware('staff.permission:reports.view')->name('panel.reports.index');
    Route::get('/reports/financial', [PanelReportController::class, 'financial'])->middleware('staff.permission:reports.view')->name('panel.reports.financial');

    // Staff Users, Roles & Activity (gym owner only; controllers enforce owner/admin)
    Route::get('/staff/activity', [PanelStaffActivityController::class, 'index'])->name('panel.staff.activity.index');
    Route::get('/staff/users', [PanelStaffUserController::class, 'index'])->name('panel.staff.users.index');
    Route::get('/staff/users/create', [PanelStaffUserController::class, 'create'])->name('panel.staff.users.create');
    Route::post('/staff/users', [PanelStaffUserController::class, 'store'])->name('panel.staff.users.store');
    Route::get('/staff/users/{id}', [PanelStaffUserController::class, 'show'])->name('panel.staff.users.show');
    Route::put('/staff/users/{id}', [PanelStaffUserController::class, 'update'])->name('panel.staff.users.update');
    Route::post('/staff/users/{id}/password', [PanelStaffUserController::class, 'updatePassword'])->name('panel.staff.users.password');
    Route::put('/staff/users/{id}/toggle', [PanelStaffUserController::class, 'toggle'])->name('panel.staff.users.toggle');
    Route::delete('/staff/users/{id}', [PanelStaffUserController::class, 'destroy'])->name('panel.staff.users.destroy');
    Route::get('/staff/roles', [PanelStaffRoleController::class, 'index'])->name('panel.staff.roles.index');
    Route::post('/staff/roles', [PanelStaffRoleController::class, 'store'])->name('panel.staff.roles.store');
    Route::get('/staff/roles/{id}', [PanelStaffRoleController::class, 'show'])->name('panel.staff.roles.show');
    Route::put('/staff/roles/{id}', [PanelStaffRoleController::class, 'update'])->name('panel.staff.roles.update');
    Route::delete('/staff/roles/{id}', [PanelStaffRoleController::class, 'destroy'])->name('panel.staff.roles.destroy');

    // Trainers
    Route::get('/trainers', [PanelTrainerController::class, 'index'])->middleware('staff.permission:trainers.view')->name('panel.trainers.index');
    Route::get('/trainers/create', [PanelTrainerController::class, 'create'])->middleware('staff.permission:trainers.create')->name('panel.trainers.create');
    Route::post('/trainers', [PanelTrainerController::class, 'store'])->middleware('staff.permission:trainers.create')->name('panel.trainers.store');
    Route::get('/trainers/{id}', [PanelTrainerController::class, 'show'])->middleware('staff.permission:trainers.view')->name('panel.trainers.show');
    Route::put('/trainers/{id}', [PanelTrainerController::class, 'update'])->middleware('staff.permission:trainers.edit')->name('panel.trainers.update');
    Route::put('/trainers/{id}/toggle', [PanelTrainerController::class, 'toggle'])->middleware('staff.permission:trainers.edit')->name('panel.trainers.toggle');
    Route::delete('/trainers/{id}', [PanelTrainerController::class, 'destroy'])->middleware('staff.permission:trainers.delete')->name('panel.trainers.destroy');

    // Expenses
    Route::get('/expenses', [PanelExpenseController::class, 'index'])->middleware('staff.permission:expenses.view')->name('panel.expenses.index');
    Route::get('/expenses/create', [PanelExpenseController::class, 'create'])->middleware('staff.permission:expenses.create')->name('panel.expenses.create');
    Route::post('/expenses', [PanelExpenseController::class, 'store'])->middleware('staff.permission:expenses.create')->name('panel.expenses.store');
    Route::get('/expenses/{id}/edit', [PanelExpenseController::class, 'edit'])->middleware('staff.permission:expenses.edit')->name('panel.expenses.edit');
    Route::put('/expenses/{id}', [PanelExpenseController::class, 'update'])->middleware('staff.permission:expenses.edit')->name('panel.expenses.update');
    Route::delete('/expenses/{id}', [PanelExpenseController::class, 'destroy'])->middleware('staff.permission:expenses.delete')->name('panel.expenses.destroy');

    // Notices
    Route::get('/notices', [PanelNoticeController::class, 'index'])->middleware('staff.permission:notices.view')->name('panel.notices.index');
    Route::get('/notices/create', [PanelNoticeController::class, 'create'])->middleware('staff.permission:notices.create')->name('panel.notices.create');
    Route::post('/notices', [PanelNoticeController::class, 'store'])->middleware('staff.permission:notices.create')->name('panel.notices.store');
    Route::get('/notices/{id}/edit', [PanelNoticeController::class, 'edit'])->middleware('staff.permission:notices.edit')->name('panel.notices.edit');
    Route::put('/notices/{id}', [PanelNoticeController::class, 'update'])->middleware('staff.permission:notices.edit')->name('panel.notices.update');
    Route::delete('/notices/{id}', [PanelNoticeController::class, 'destroy'])->middleware('staff.permission:notices.delete')->name('panel.notices.destroy');

    // Lockers
    Route::get('/lockers', [PanelLockerController::class, 'index'])->middleware('staff.permission:lockers.view')->name('panel.lockers.index');
    Route::post('/lockers', [PanelLockerController::class, 'store'])->middleware('staff.permission:lockers.create')->name('panel.lockers.store');
    Route::post('/lockers/assign', [PanelLockerController::class, 'assign'])->middleware('staff.permission:lockers.assign')->name('panel.lockers.assign');
    Route::delete('/lockers/delete-all', [PanelLockerController::class, 'deleteAll'])->middleware('staff.permission:lockers.delete')->name('panel.lockers.deleteAll');
    Route::delete('/lockers/{id}/unassign', [PanelLockerController::class, 'unassign'])->middleware('staff.permission:lockers.assign')->name('panel.lockers.unassign');
    Route::delete('/lockers/{id}', [PanelLockerController::class, 'destroy'])->middleware('staff.permission:lockers.delete')->name('panel.lockers.destroy');

    // Plans
    Route::get('/plans', [PanelPlanController::class, 'index'])->middleware('staff.permission:plans.view')->name('panel.plans.index');
    Route::post('/plans', [PanelPlanController::class, 'store'])->middleware('staff.permission:plans.create')->name('panel.plans.store');
    Route::put('/plans/{id}', [PanelPlanController::class, 'update'])->middleware('staff.permission:plans.edit')->name('panel.plans.update');
    Route::delete('/plans/{id}', [PanelPlanController::class, 'destroy'])->middleware('staff.permission:plans.delete')->name('panel.plans.destroy');

    // Classes
    Route::get('/classes', [PanelClassController::class, 'index'])->middleware('staff.permission:classes.view')->name('panel.classes.index');
    Route::post('/classes', [PanelClassController::class, 'store'])->middleware('staff.permission:classes.create')->name('panel.classes.store');
    Route::put('/classes/{id}', [PanelClassController::class, 'update'])->middleware('staff.permission:classes.edit')->name('panel.classes.update');
    Route::delete('/classes/{id}', [PanelClassController::class, 'destroy'])->middleware('staff.permission:classes.delete')->name('panel.classes.destroy');

    // Events
    Route::get('/events', [PanelEventController::class, 'index'])->middleware('staff.permission:events.view')->name('panel.events.index');
    Route::post('/events', [PanelEventController::class, 'store'])->middleware('staff.permission:events.create')->name('panel.events.store');
    Route::put('/events/{id}', [PanelEventController::class, 'update'])->middleware('staff.permission:events.edit')->name('panel.events.update');
    Route::delete('/events/{id}', [PanelEventController::class, 'destroy'])->middleware('staff.permission:events.delete')->name('panel.events.destroy');

    // Products
    Route::get('/products', [PanelProductController::class, 'index'])->middleware('staff.permission:products.view')->name('panel.products.index');
    Route::post('/products', [PanelProductController::class, 'store'])->middleware('staff.permission:products.create')->name('panel.products.store');
    Route::put('/products/{id}', [PanelProductController::class, 'update'])->middleware('staff.permission:products.edit')->name('panel.products.update');
    Route::delete('/products/{id}', [PanelProductController::class, 'destroy'])->middleware('staff.permission:products.delete')->name('panel.products.destroy');

    // Subscription
    Route::get('/subscription', [PanelSubscriptionController::class, 'index'])->middleware('staff.permission:subscription.view')->name('panel.subscription.index');
    Route::post('/subscription/payment', [PanelSubscriptionController::class, 'createPayment'])->middleware('staff.permission:subscription.pay')->name('panel.subscription.createPayment');
    Route::get('/subscription/verify', [PanelSubscriptionController::class, 'verifyPayment'])->middleware('staff.permission:subscription.view')->name('panel.subscription.verify');

    // Transactions
    Route::get('/transactions', [PanelTransactionController::class, 'index'])->middleware('staff.permission:transactions.view')->name('panel.transactions.index');

    // QR Code
    Route::get('/qr', [PanelQRController::class, 'index'])->middleware('staff.permission:attendance.qr')->name('panel.qr.index');

    // Diet Templates — Gym Owner/Admin only (controller also enforces this).
    Route::get('/diets', [PanelDietController::class, 'index'])->name('panel.diets.index');
    Route::post('/diets', [PanelDietController::class, 'store'])->name('panel.diets.store');
    Route::put('/diets/{id}', [PanelDietController::class, 'update'])->name('panel.diets.update');
    Route::delete('/diets/{id}', [PanelDietController::class, 'destroy'])->name('panel.diets.destroy');

    // Workouts
    Route::post('/workouts', [PanelWorkoutController::class, 'store'])->middleware('staff.permission:workouts.create')->name('panel.workouts.store');
    Route::put('/workouts/{id}', [PanelWorkoutController::class, 'update'])->middleware('staff.permission:workouts.edit')->name('panel.workouts.update');
    Route::delete('/workouts/{id}', [PanelWorkoutController::class, 'destroy'])->middleware('staff.permission:workouts.delete')->name('panel.workouts.destroy');
    Route::get('/workouts/activities', [PanelWorkoutController::class, 'activities'])->middleware('staff.permission:workouts.view')->name('panel.workouts.activities');
    Route::post('/workouts/activities', [PanelWorkoutController::class, 'storeActivity'])->middleware('staff.permission:workouts.create')->name('panel.workouts.storeActivity');
    Route::put('/workouts/activities/{id}', [PanelWorkoutController::class, 'updateActivity'])->middleware('staff.permission:workouts.edit')->name('panel.workouts.updateActivity');
    Route::delete('/workouts/activities/{id}', [PanelWorkoutController::class, 'destroyActivity'])->middleware('staff.permission:workouts.delete')->name('panel.workouts.destroyActivity');

    // Settings: every authenticated panel user may manage their own profile
    // and password. Gym/business profile writes are owner-only in the
    // controller as a direct-request defense.
    Route::get('/settings', [PanelSettingsController::class, 'index'])->name('panel.settings.index');
    Route::post('/settings/profile', [PanelSettingsController::class, 'updateProfile'])->name('panel.settings.updateProfile');
    Route::post('/settings/personal', [PanelSettingsController::class, 'updatePersonalProfile'])->name('panel.settings.updatePersonalProfile');
    Route::post('/settings/password', [PanelSettingsController::class, 'updatePassword'])->name('panel.settings.updatePassword');
});
