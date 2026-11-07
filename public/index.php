<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$R = Auth::RESIDENT;
$ALL = array_merge(Auth::RESIDENT, Auth::STAFF);
/* Bảng định tuyến: route => [Controller, phương thức, vai trò được phép (null = công khai)] */
$routes = [
    'auth/login'        => ['AuthController', 'login', null],
    'auth/otp-send'     => ['AuthController', 'otpSend', null],
    'auth/otp-verify'   => ['AuthController', 'otpVerify', null],
    'auth/logout'       => ['AuthController', 'logout', null],
    'auth/password'     => ['AuthController', 'password', $ALL],
    'vnpay/ipn'         => ['PaymentController', 'ipn', null],          // tín hiệu ngầm server-to-server
    'vnpay/sandbox'     => ['PaymentController', 'sandbox', null],      // cổng VNPay mô phỏng (khi chưa có TMN code)
    'vnpay/return'      => ['PaymentController', 'returnPage', $R],
    'pay/create'        => ['PaymentController', 'create', $R],
    'ai/ask'            => ['ResidentController', 'aiAsk', $ALL],

    'home'              => ['ResidentController', 'home', $R],
    'residents'         => ['ResidentController', 'residents', $R],
    'residents/declare' => ['ResidentController', 'declare', $R],
    'residents/remove'  => ['ResidentController', 'removeMember', $R],
    'cards/report-lost' => ['ResidentController', 'reportLost', $R],
    'vehicles'          => ['ResidentController', 'vehicles', $R],
    'vehicles/register' => ['ResidentController', 'vehicleRegister', $R],
    'vehicles/resubmit' => ['ResidentController', 'vehicleResubmit', $R],
    'vehicles/cancel'   => ['ResidentController', 'vehicleCancel', $R],
    'invoices'          => ['ResidentController', 'invoices', $R],
    'facilities'        => ['ResidentController', 'facilities', $R],
    'facilities/book'   => ['ResidentController', 'book', $R],
    'facilities/cancel' => ['ResidentController', 'bookingCancel', $R],
    'tickets'           => ['ResidentController', 'tickets', $R],
    'tickets/create'    => ['ResidentController', 'ticketCreate', $R],
    'tickets/confirm'   => ['ResidentController', 'ticketConfirm', $R],
    'tickets/reopen'    => ['ResidentController', 'ticketReopen', $R],
    'tickets/cancel'    => ['ResidentController', 'ticketCancel', $R],

    'admin/dashboard'   => ['AdminController', 'dashboard', ['admin', 'ke_toan_truong', 'ke_toan']],
    'admin/export'      => ['AdminController', 'export', ['admin', 'ke_toan_truong', 'ke_toan']],
    'admin/apartments'  => ['AdminController', 'apartments', ['le_tan', 'admin', 'ke_toan_truong']],
    'admin/resident-approve' => ['AdminController', 'residentApprove', ['le_tan']],
    'admin/resident-reject'  => ['AdminController', 'residentReject', ['le_tan']],
    'admin/vehicles'    => ['AdminController', 'vehicles', ['le_tan', 'admin']],
    'admin/vehicle-approve'  => ['AdminController', 'vehicleApprove', ['le_tan']],
    'admin/vehicle-decline'  => ['AdminController', 'vehicleDecline', ['le_tan']],
    'admin/card-issue'  => ['AdminController', 'cardIssue', ['le_tan']],
    'admin/card-state'  => ['AdminController', 'cardState', ['le_tan']],
    'admin/billing'     => ['AdminController', 'billing', ['ke_toan', 'ke_toan_truong', 'admin']],
    'admin/readings-save'    => ['AdminController', 'readingsSave', ['ke_toan']],
    'admin/invoices-issue'   => ['AdminController', 'invoicesIssue', ['ke_toan']],
    'admin/invoices-approve' => ['AdminController', 'invoicesApprove', ['ke_toan_truong']],
    'admin/invoice-cancel'   => ['AdminController', 'invoiceCancel', ['ke_toan_truong']],
    'admin/fees-save'   => ['AdminController', 'feesSave', ['ke_toan_truong']],
    'admin/payments'    => ['AdminController', 'payments', ['ke_toan', 'ke_toan_truong', 'admin']],
    'admin/unpaid'      => ['AdminController', 'unpaid', ['ke_toan']],
    'admin/payment-manual'   => ['AdminController', 'paymentManual', ['ke_toan']],
    'admin/payments-export'  => ['AdminController', 'paymentsExport', ['ke_toan', 'ke_toan_truong', 'admin']],
    'admin/facilities'  => ['AdminController', 'facilities', ['le_tan', 'ke_toan_truong', 'admin']],
    'admin/ticket-verify'    => ['AdminController', 'ticketVerify', ['le_tan']],
    'admin/maintenance' => ['AdminController', 'maintenance', ['ke_toan_truong', 'admin']],
    'admin/tickets'     => ['AdminController', 'tickets', ['dieu_phoi_vien', 'ky_thuat_vien', 'le_tan', 'admin']],
    'admin/ticket-assign'    => ['AdminController', 'ticketAssign', ['dieu_phoi_vien']],
    'admin/ticket-reject'    => ['AdminController', 'ticketReject', ['dieu_phoi_vien']],
    'admin/ticket-move'      => ['AdminController', 'ticketMove', ['ky_thuat_vien']],
    'admin/ticket-accept'    => ['AdminController', 'ticketAccept', ['ky_thuat_vien']],
    'admin/system'      => ['AdminController', 'system', ['admin']],
    'admin/account-create'   => ['AdminController', 'accountCreate', ['admin']],
    'admin/account-lock'     => ['AdminController', 'accountLock', ['admin']],
    'admin/ai-save'     => ['AdminController', 'aiSave', ['admin']],
    'admin/ai-delete'   => ['AdminController', 'aiDelete', ['admin']],
    'admin/ai-publish'  => ['AdminController', 'aiPublish', ['admin']],
];

$r = $_GET['r'] ?? '';
if ($r === '') {
    $home = ['chu_ho' => 'home', 'khach_thue' => 'home', 'le_tan' => 'admin/apartments', 'ke_toan' => 'admin/billing',
        'ke_toan_truong' => 'admin/dashboard', 'dieu_phoi_vien' => 'admin/tickets', 'ky_thuat_vien' => 'admin/tickets',
        'admin' => 'admin/dashboard'];
    redirect(Auth::user() ? $home[Auth::role()] : 'auth/login');
}
if (!isset($routes[$r])) { http_response_code(404); exit('Không tìm thấy trang.'); }
[$ctrl, $method, $roles] = $routes[$r];
if ($roles !== null) Auth::guard($roles);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $r !== 'vnpay/ipn') Csrf::check();

try {
    (new $ctrl())->$method();
} catch (BusinessException $e) {
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) json_out(['ok' => false, 'error' => $e->getMessage()], 422);
    flash('error', $e->getMessage());
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('')));
}
