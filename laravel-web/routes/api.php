<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\LockerController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\HealthRecordController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Admin\BugReportController;
use App\Http\Controllers\WebLoginController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Health check - no auth required
Route::get('/v1/health', [HealthController::class, 'check']);

// Auth routes - no auth required
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/forgot-password/send-otp', [AuthController::class, 'forgotPasswordSendOtp']);
    Route::post('/forgot-password/verify-otp', [AuthController::class, 'forgotPasswordVerifyOtp']);
    Route::post('/forgot-password/reset', [AuthController::class, 'forgotPasswordReset']);
    Route::get('/app-update', [SettingsController::class, 'appUpdate']);
    Route::get('/system-status', [SettingsController::class, 'systemStatus']);
});

// Webhook - no auth required
Route::post('/v1/subscription/webhook', [SubscriptionController::class, 'webhook']);
Route::post('/v1/subscription/webhook/razorpay', [SubscriptionController::class, 'razorpayWebhook']);
Route::get('/v1/subscription/webhook/razorpay', [SubscriptionController::class, 'razorpayReturn']);
Route::match(['get', 'post'], '/v1/subscription/webhook/payu', [SubscriptionController::class, 'payuCallback']);
Route::match(['get', 'post'], '/v1/subscription/webhook/phonepe', [SubscriptionController::class, 'phonepeCallback']);
Route::match(['get', 'post'], '/v1/subscription/webhook/instamojo', [SubscriptionController::class, 'instamojoCallback']);

// Protected routes - auth required (Sanctum)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/track-app-open', [AuthController::class, 'trackAppOpen']);
    Route::put('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    
    // Dashboard & Reports
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/transactions', [ReportController::class, 'transactions']);
    Route::get('/member-transactions', [ReportController::class, 'memberTransactions']);
    
    // Members
    Route::get('/members', [MemberController::class, 'index']);
    Route::post('/members', [MemberController::class, 'store']);
    Route::get('/members/{id}', [MemberController::class, 'show']);
    Route::put('/members/{id}', [MemberController::class, 'update']);
    Route::delete('/members/{id}', [MemberController::class, 'destroy']);
    Route::delete('/members/{id}/hard', [MemberController::class, 'hardDelete']);
    Route::post('/members/{id}/renew', [MemberController::class, 'renew']);
    Route::post('/members/{id}/freeze', [MemberController::class, 'freeze']);
    Route::post('/members/{id}/unfreeze', [MemberController::class, 'unfreeze']);
    
    // Attendance
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    // QR scan is ALSO registered here for consistency (protected version)
    Route::post('/attendance/scan', [AttendanceController::class, 'scan']);
    Route::put('/attendance/{id}', [AttendanceController::class, 'update']);
    Route::delete('/attendance/{id}', [AttendanceController::class, 'destroy']);
    Route::get('/attendance/search', [AttendanceController::class, 'search']);
    Route::get('/attendance/calendar', [AttendanceController::class, 'calendar']);
    
    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);
    Route::post('/invoices/{id}/payments', [InvoiceController::class, 'addPayment']);
    
    // Trainers
    Route::get('/trainers', [TrainerController::class, 'index']);
    Route::post('/trainers', [TrainerController::class, 'store']);
    Route::get('/trainers/{id}', [TrainerController::class, 'show']);
    Route::put('/trainers/{id}', [TrainerController::class, 'update']);
    Route::put('/trainers/{id}/toggle', [TrainerController::class, 'toggle']);
    Route::delete('/trainers/{id}', [TrainerController::class, 'destroy']);

    // Trainer app panel
    Route::get('/trainer/dashboard', [TrainerController::class, 'dashboard']);
    Route::get('/trainer/members', [TrainerController::class, 'assignedMembers']);
    Route::get('/trainer/members/{id}', [TrainerController::class, 'memberDetail']);
    Route::get('/trainer/workouts', [TrainerController::class, 'workouts']);
    Route::post('/trainer/workouts', [TrainerController::class, 'storeWorkout']);
    Route::put('/trainer/workouts/{id}', [TrainerController::class, 'updateWorkout']);
    Route::delete('/trainer/workouts/{id}', [TrainerController::class, 'destroyWorkout']);
    Route::get('/trainer/classes', [TrainerController::class, 'classes']);
    
    // Memberships (Plans)
    Route::get('/memberships', [MembershipController::class, 'index']);
    Route::post('/memberships', [MembershipController::class, 'store']);
    Route::put('/memberships/{id}', [MembershipController::class, 'update']);
    Route::delete('/memberships/{id}', [MembershipController::class, 'destroy']);
    
    // Classes
    Route::get('/classes', [ClassController::class, 'index']);
    Route::post('/classes', [ClassController::class, 'store']);
    Route::get('/classes/{id}', [ClassController::class, 'show']);
    Route::put('/classes/{id}', [ClassController::class, 'update']);
    Route::delete('/classes/{id}', [ClassController::class, 'destroy']);
    
    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    
    // Expenses
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);
    Route::get('/expenses/types', [ExpenseController::class, 'types']);
    
    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    
    // Notices
    Route::get('/notices', [NoticeController::class, 'index']);
    Route::post('/notices', [NoticeController::class, 'store']);
    Route::put('/notices/{id}', [NoticeController::class, 'update']);
    Route::delete('/notices/{id}', [NoticeController::class, 'destroy']);
    
    // Lockers
    Route::get('/lockers', [LockerController::class, 'index']);
    Route::post('/lockers', [LockerController::class, 'store']);
    Route::post('/lockers/assign', [LockerController::class, 'assign']);
    Route::put('/lockers/unassign', [LockerController::class, 'unassign']);
    Route::delete('/lockers/{id}', [LockerController::class, 'destroy']);
    
    // Events
    Route::get('/events', [EventController::class, 'index']);
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::get('/events/types', [EventController::class, 'types']);
    Route::post('/events/types', [EventController::class, 'storeType']);
    
    // Workouts
    Route::get('/workouts', [WorkoutController::class, 'index']);
    Route::post('/workouts', [WorkoutController::class, 'store']);
    Route::put('/workouts/{id}', [WorkoutController::class, 'update']);
    Route::delete('/workouts/{id}', [WorkoutController::class, 'destroy']);
    Route::get('/workout-activities', [WorkoutController::class, 'activities']);
    Route::post('/workout-activities', [WorkoutController::class, 'storeActivity']);
    Route::delete('/workout-activities/{id}', [WorkoutController::class, 'destroyActivity']);
    
    // Health Records
    Route::get('/health-records', [HealthRecordController::class, 'index']);
    Route::post('/health-records', [HealthRecordController::class, 'store']);
    Route::put('/health-records/{id}', [HealthRecordController::class, 'update']);
    Route::delete('/health-records/{id}', [HealthRecordController::class, 'destroy']);
    
    // Subscription
    Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);
    Route::post('/subscription/payment-link', [SubscriptionController::class, 'createPaymentLink']);
    Route::get('/subscription/verify', [SubscriptionController::class, 'verify']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
    
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'destroyAll']);
    
    // Settings
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::post('/settings', [SettingsController::class, 'update']);
    Route::get('/settings/gym-profile', [SettingsController::class, 'gymProfile']);
    Route::put('/settings/gym-profile', [SettingsController::class, 'updateGymProfile']);
    Route::get('/settings/smtp', [SettingsController::class, 'smtpSettings']);
    Route::put('/settings/smtp', [SettingsController::class, 'updateSmtpSettings']);
    Route::post('/settings/smtp/test', [SettingsController::class, 'testSmtp']);
    
    // Web login approval/session management (WhatsApp Web style)
    Route::get('/web-login/sessions', [WebLoginController::class, 'sessions']);
    Route::post('/web-login/logout', [WebLoginController::class, 'logoutSession']);
    Route::post('/web-login/approve', [WebLoginController::class, 'approve']);

    // Bug Reports (for users)
    Route::post('/bugs/report', [BugReportController::class, 'store']);
});

// Super Admin / Platform routes (protected)
Route::prefix('v1/admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/bug-reports', [BugReportController::class, 'index']);
    Route::get('/bug-reports/{id}', [BugReportController::class, 'show']);
    Route::put('/bug-reports/{id}', [BugReportController::class, 'update']);
    Route::delete('/bug-reports/{id}', [BugReportController::class, 'destroy']);
});

// Debug test endpoint is disabled in production unless explicitly enabled.
if (!app()->environment('production') || env('ENABLE_DEBUG_ROUTES', false)) {
    Route::get('/v1/test', function () {
        return response()->json([
            'message' => 'GymXBook API is working!',
            'timezone' => config('app.timezone'),
            'timestamp' => now()->toDateTimeString(),
        ]);
    });
}
