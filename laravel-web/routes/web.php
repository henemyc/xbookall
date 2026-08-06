<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Root URL should open the Gym Owner web login.
Route::get('/', function () {
    return redirect('/panel/login');
})->middleware('maintenance');

/*
 * ============================================================
 * ABSOLUTELY CRITICAL: Named 'login' route
 * ============================================================
 * This MUST be defined for the app to work.
 * Laravel core will call route('login') or redirect()->route('login')
 * from Authenticate middleware, AuthenticationException, guest middleware, etc.
 *
 * We define it at the very top, using only direct paths.
 */
Route::get('/login', function () {
    if (auth()->check() && (auth()->user()->type ?? null) === 'admin') {
        return redirect('/panel');
    }
    return redirect('/panel/login');
})->middleware('maintenance')->name('login');

// Admin Routes
Route::prefix('admin')->group(base_path('routes/admin.php'));

// Panel Routes (Gym Owner)
Route::prefix('panel')->middleware('maintenance')->group(base_path('routes/panel.php'));

// Payment redirects
Route::get('/payment/payu/redirect/{orderId}', [\App\Http\Controllers\SubscriptionController::class, 'payuRedirect'])->name('payment.payu.redirect');

// Debug/API tester routes are disabled in production unless explicitly enabled.
if (!app()->environment('production') || env('ENABLE_DEBUG_ROUTES', false)) {
    Route::get('/api-tester', function () {
        return response()->file(public_path('api-tester.html'));
    });

    Route::get('/test-api', function () {
        return response()->file(public_path('api-tester.html'));
    });

    Route::get('/api-debug', function () {
        return response()->json([
            'message' => 'GymXBook API Tester is ready',
            'tester_url' => url('/api-tester'),
            'base_api' => url('/api/v1'),
            'timestamp' => now()->toDateTimeString(),
        ]);
    });
}
