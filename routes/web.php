<?php

use App\Http\Controllers\AdminController as A;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ResidentController as R;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bảng định tuyến hệ thống Chung cư Moons
|--------------------------------------------------------------------------
| Mỗi nhóm route gắn middleware role:<danh sách vai trò được phép>.
| Trang xem dữ liệu dùng GET; mọi thao tác thay đổi dữ liệu dùng POST (được bảo vệ CSRF).
*/

$RES = 'role:chu_ho,khach_thue';
$ALL = 'role:chu_ho,khach_thue,le_tan,ke_toan,ke_toan_truong,dieu_phoi_vien,ky_thuat_vien,admin';

/* -------------------- Công khai -------------------- */
Route::get('/', [AuthController::class, 'root'])->name('root');
Route::match(['get', 'post'], '/auth/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/auth/otp-send', [AuthController::class, 'otpSend'])->name('auth.otp-send');
Route::post('/auth/otp-verify', [AuthController::class, 'otpVerify'])->name('auth.otp-verify');
Route::get('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
Route::get('/vnpay/ipn', [PaymentController::class, 'ipn'])->name('vnpay.ipn');                         // VNPay gọi ngầm (server-to-server)
Route::match(['get', 'post'], '/vnpay/sandbox', [PaymentController::class, 'sandbox'])->name('vnpay.sandbox'); // cổng VNPay mô phỏng

/* -------------------- Mọi người dùng đã đăng nhập -------------------- */
Route::middleware($ALL)->group(function () {
    Route::match(['get', 'post'], '/auth/password', [AuthController::class, 'password'])->name('auth.password');
    Route::post('/ai/ask', [R::class, 'aiAsk'])->name('ai.ask');
});

/* -------------------- Cổng Cư dân -------------------- */
Route::middleware($RES)->group(function () {
    Route::get('/vnpay/return', [PaymentController::class, 'returnPage'])->name('vnpay.return');
    Route::post('/pay/create', [PaymentController::class, 'create'])->name('pay.create');

    Route::get('/home', [R::class, 'home'])->name('home');
    Route::get('/residents', [R::class, 'residents'])->name('residents');
    Route::post('/residents/declare', [R::class, 'declare'])->name('residents.declare');
    Route::post('/residents/remove', [R::class, 'removeMember'])->name('residents.remove');
    Route::post('/cards/report-lost', [R::class, 'reportLost'])->name('cards.report-lost');
    Route::get('/vehicles', [R::class, 'vehicles'])->name('vehicles');
    Route::post('/vehicles/register', [R::class, 'vehicleRegister'])->name('vehicles.register');
    Route::post('/vehicles/resubmit', [R::class, 'vehicleResubmit'])->name('vehicles.resubmit');
    Route::post('/vehicles/cancel', [R::class, 'vehicleCancel'])->name('vehicles.cancel');
    Route::get('/invoices', [R::class, 'invoices'])->name('invoices');
    Route::get('/facilities', [R::class, 'facilities'])->name('facilities');
    Route::post('/facilities/book', [R::class, 'book'])->name('facilities.book');
    Route::post('/facilities/cancel', [R::class, 'bookingCancel'])->name('facilities.cancel');
    Route::get('/tickets', [R::class, 'tickets'])->name('tickets');
    Route::post('/tickets/create', [R::class, 'ticketCreate'])->name('tickets.create');
    Route::post('/tickets/confirm', [R::class, 'ticketConfirm'])->name('tickets.confirm');
    Route::post('/tickets/reopen', [R::class, 'ticketReopen'])->name('tickets.reopen');
    Route::post('/tickets/cancel', [R::class, 'ticketCancel'])->name('tickets.cancel');
});

/* -------------------- Ban Quản lý -------------------- */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('role:admin,ke_toan_truong,ke_toan')->group(function () {
        Route::get('/dashboard', [A::class, 'dashboard'])->name('dashboard');
        Route::get('/export', [A::class, 'export'])->name('export');
    });
    Route::middleware('role:le_tan,admin,ke_toan_truong')->get('/apartments', [A::class, 'apartments'])->name('apartments');
    Route::middleware('role:le_tan,admin')->get('/vehicles', [A::class, 'vehicles'])->name('vehicles');
    Route::middleware('role:ke_toan,ke_toan_truong,admin')->group(function () {
        Route::get('/billing', [A::class, 'billing'])->name('billing');
        Route::get('/payments', [A::class, 'payments'])->name('payments');
        Route::get('/payments-export', [A::class, 'paymentsExport'])->name('payments-export');
    });
    Route::middleware('role:le_tan,ke_toan_truong,admin')->get('/facilities', [A::class, 'facilities'])->name('facilities');
    Route::middleware('role:dieu_phoi_vien,ky_thuat_vien,le_tan,admin')->get('/tickets', [A::class, 'tickets'])->name('tickets');

    // Lễ tân
    Route::middleware('role:le_tan')->group(function () {
        Route::post('/resident-approve', [A::class, 'residentApprove'])->name('resident-approve');
        Route::post('/resident-reject', [A::class, 'residentReject'])->name('resident-reject');
        Route::post('/vehicle-approve', [A::class, 'vehicleApprove'])->name('vehicle-approve');
        Route::post('/vehicle-decline', [A::class, 'vehicleDecline'])->name('vehicle-decline');
        Route::post('/card-issue', [A::class, 'cardIssue'])->name('card-issue');
        Route::post('/card-state', [A::class, 'cardState'])->name('card-state');
        Route::post('/ticket-verify', [A::class, 'ticketVerify'])->name('ticket-verify');
    });
    // Kế toán
    Route::middleware('role:ke_toan')->group(function () {
        Route::post('/readings-save', [A::class, 'readingsSave'])->name('readings-save');
        Route::post('/invoices-issue', [A::class, 'invoicesIssue'])->name('invoices-issue');
        Route::get('/unpaid', [A::class, 'unpaid'])->name('unpaid');
        Route::post('/payment-manual', [A::class, 'paymentManual'])->name('payment-manual');
    });
    // Kế toán trưởng
    Route::middleware('role:ke_toan_truong')->group(function () {
        Route::post('/invoices-approve', [A::class, 'invoicesApprove'])->name('invoices-approve');
        Route::post('/invoice-cancel', [A::class, 'invoiceCancel'])->name('invoice-cancel');
        Route::post('/fees-save', [A::class, 'feesSave'])->name('fees-save');
    });
    Route::middleware('role:ke_toan_truong,admin')->post('/maintenance', [A::class, 'maintenance'])->name('maintenance');
    // Điều phối viên & Kỹ thuật viên
    Route::middleware('role:dieu_phoi_vien')->group(function () {
        Route::post('/ticket-assign', [A::class, 'ticketAssign'])->name('ticket-assign');
        Route::post('/ticket-reject', [A::class, 'ticketReject'])->name('ticket-reject');
    });
    Route::middleware('role:ky_thuat_vien')->group(function () {
        Route::post('/ticket-move', [A::class, 'ticketMove'])->name('ticket-move');
        Route::post('/ticket-accept', [A::class, 'ticketAccept'])->name('ticket-accept');
    });
    // Admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/system', [A::class, 'system'])->name('system');
        Route::post('/account-create', [A::class, 'accountCreate'])->name('account-create');
        Route::post('/account-lock', [A::class, 'accountLock'])->name('account-lock');
        Route::post('/ai-save', [A::class, 'aiSave'])->name('ai-save');
        Route::post('/ai-delete', [A::class, 'aiDelete'])->name('ai-delete');
        Route::post('/ai-publish', [A::class, 'aiPublish'])->name('ai-publish');
    });
});
