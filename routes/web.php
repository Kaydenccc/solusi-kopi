<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Models\Payment;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\Console\{
    UserController,
    OutletController,
    TableController,
    CategoryController,
    ProductController,
    PromotionController,
    OrderManagementController,
    ReportingController
};
use App\Http\Controllers\{
    DashboardController,
    ProfileController,
    OrderController,
    OrderHistoryController,
    WelcomeController
};
use Illuminate\Support\Facades\Auth;

// Landing
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');
Route::post('/search-table', [WelcomeController::class, 'searchTable'])->name('welcome.search-table');
Route::get('/select-table/{table_code}', [WelcomeController::class, 'selectTable'])->name('welcome.select-table');

// ==================================================
// 🛡️ Protected Routes (auth + verified)
// ==================================================
Route::middleware(['auth'])->group(function () {

    // Dashboard (role-based)
    Route::get('/dashboard', [DashboardController::class, '__invoke'])
        ->middleware('role:admin|kasir|user')
        ->name('dashboard');

    // Profile
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    // Order history (admin/kasir only)
    Route::middleware('role:admin|kasir')->prefix('order-history')->name('order.history.')->group(function () {
        Route::get('/', [OrderHistoryController::class, 'index'])->name('index');
        Route::get('/{order}', [OrderHistoryController::class, 'show'])->name('show');
    });

    // Admin Console
    Route::prefix('console')->middleware('role:admin')->group(function () {
        Route::prefix('user-management')->group(function () {
            Route::resource('users', UserController::class);
        });

        Route::get('tables/master-qr', [App\Http\Controllers\Console\TableController::class, 'masterQr'])->name('tables.master-qr');

        Route::resources([
            'outlets'     => OutletController::class,
            'tables'      => TableController::class,
            'categories'  => CategoryController::class,
            'products'    => ProductController::class,
            'promotions'  => PromotionController::class,
        ]);
    });

    // Order Management (Admin & Kasir)
    Route::prefix('console')->middleware('role:admin|kasir')->group(function () {
        Route::prefix('orders')->name('console.orders.')->group(function () {
            Route::get('/', [OrderManagementController::class, 'index'])->name('index');
            Route::get('/{order}', [OrderManagementController::class, 'show'])->name('show');
            Route::patch('/{order}/status', [OrderManagementController::class, 'updateStatus'])->name('update-status');
            Route::patch('/{order}/payment', [OrderManagementController::class, 'updatePaymentStatus'])->name('update-payment');
            Route::get('/stats', [OrderManagementController::class, 'getStats'])->name('stats');
            Route::get('/export', [OrderManagementController::class, 'export'])->name('export');
        });

        // Reporting (Admin & Kasir)
        Route::prefix('reporting')->name('console.reporting.')->group(function () {
            Route::get('/', [ReportingController::class, 'index'])->name('index');
            Route::get('/export', [ReportingController::class, 'export'])->name('export');
            Route::get('/stats/realtime', [ReportingController::class, 'getRealTimeStats'])->name('realtime-stats');
        });
    });
});

// ==================================================
// 🔐 Auth & Social Login
// ==================================================
require __DIR__ . '/auth.php';

Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('google', [SocialLoginController::class, 'redirectToGoogle'])->name('google');
    Route::get('google/callback', [SocialLoginController::class, 'handleGoogleCallback'])->name('google.callback');

    Route::get('facebook', [SocialLoginController::class, 'redirectToFacebook'])->name('facebook');
    Route::get('facebook/callback', [SocialLoginController::class, 'handleFacebookCallback'])->name('facebook.callback');
});

// ==================================================
// 🧾 Guest Order Routes
// ==================================================
Route::prefix('order')->name('order.')->group(function () {
    Route::get('/history', [OrderController::class, 'showOrderHistory'])->name('history');
    Route::get('/menu', [OrderController::class, 'showMenuByTableCode'])->name('menu');
    Route::get('/{table_code}', [OrderController::class, 'showMenuByTableCode'])->name('menu.with-table');
    Route::get('/history/{order_number}', [OrderController::class, 'showOrderDetail'])->name('detail');
    Route::get('/payment/qris/{order_number}', [OrderController::class, 'showPaymentQris'])->name('payment.qris');
    Route::post('/payment/confirm/{order_number}', [OrderController::class, 'confirmPayment'])->name('payment.confirm');
    Route::get('/success/{order_number}', [OrderController::class, 'showOrderSuccess'])->name('success');
    Route::get('/payment/status/{order_number}', [OrderController::class, 'getPaymentStatus'])->name('payment.status');
    Route::post('/payment/midtrans/{order}', [OrderController::class, 'payWithMidtrans'])->name('order.payment.midtrans');
    Route::get('/payment/midtrans/{order}/qris', [OrderController::class, 'showMidtransQris'])->name('order.payment.midtrans.qris');
    Route::post('/cancel/{order_number}', [OrderController::class, 'cancelOrder'])->name('cancel');
});

// Route untuk logout dan clear session
Route::post('/logout-clear', function() {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();
    session()->flush();
    return redirect()->route('welcome');
})->name('logout.clear');

Route::post('/clear-session', function() {
    session()->flush();
    return redirect()->route('welcome');
})->name('clear.session');

// ==================================================
// ✅ Simulasi QRIS POS (Dev/Test Only?)
// ==================================================
Route::prefix('order')->group(function () {
    Route::match(['get', 'post'], '/payment/{order}', function (Order $order) {
        if (request()->isMethod('post')) {
            $order->update(['payment_status' => 'paid', 'status' => 'preparing']);

            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'method' => $order->payment_method,
                'status' => 'completed',
                'payment_gateway_ref' => 'QRIS_SIM_' . Str::upper(Str::random(8)),
                'paid_at' => now(),
            ]);

            return redirect()->route('order.success', $order->order_number)->with('success', 'Pembayaran berhasil dikonfirmasi!');
        }

        if ($order->payment_status === 'paid') {
            return redirect()->route('order.success', $order->order_number)->with('info', 'Pesanan sudah dibayar.');
        }
        return view('order.payment_qris', compact('order'));
    })->name('order.payment');
});

Route::get('/console/reporting/export-summary', [\App\Http\Controllers\Console\ReportingController::class, 'exportSummary'])->name('console.reporting.exportSummary');
