<?php
final class AuthController
{
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                Auth::login(AuthService::attempt((string)input('login'), (string)($_POST['password'] ?? '')));
                redirect('');
            } catch (BusinessException $e) {
                flash('error', $e->getMessage());
                $_SESSION['old_login'] = input('login');
                redirect('auth/login');
            }
        }
        if (Auth::user()) redirect('');
        View::render('auth/login', ['old' => $_SESSION['old_login'] ?? '']);
        unset($_SESSION['old_login']);
    }
    public function otpSend(): void
    {
        try {
            json_out(['ok' => true] + AuthService::sendOtp((string)input('login')) + ['ttl' => cfg('otp_ttl')]);
        } catch (BusinessException $e) { json_out(['ok' => false, 'error' => $e->getMessage()], 422); }
    }
    public function otpVerify(): void
    {
        try {
            Auth::login(AuthService::verifyOtp((string)input('otp')));
            json_out(['ok' => true, 'redirect' => url('')]);
        } catch (BusinessException $e) { json_out(['ok' => false, 'error' => $e->getMessage()], 422); }
    }
    public function logout(): void
    {
        if (Auth::user()) AuditService::log('dang_xuat', 'tai_khoan', Auth::id());
        Auth::logout();
        session_start();
        flash('success', 'Bạn đã đăng xuất.');
        redirect('auth/login');
    }
    public function password(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AccountService::changePassword(Auth::id(), (string)$_POST['old'], (string)$_POST['new'], (string)$_POST['confirm']);
            $_SESSION['user']['phai_doi_mat_khau'] = 0;
            flash('success', 'Đã đổi mật khẩu.');
            redirect('');
        }
        View::render('auth/password', [], Auth::isResident() ? 'resident' : 'admin');
    }
}

final class PaymentController
{
    public function create(): void
    {
        $me = ResidentController::me();
        $url = PaymentService::create($me, (int)input('id'), $this->absUrl('vnpay/return'));
        json_out(['ok' => true, 'url' => $url]);
    }
    private function absUrl(string $route): string
    {
        $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($route);
    }
    /** Endpoint IPN: VNPay gọi ngầm, phản hồi JSON {RspCode, Message} */
    public function ipn(): void
    {
        $q = $_GET;
        unset($q['r']);                 // tham số định tuyến nội bộ, không thuộc dữ liệu VNPay ký
        json_out(PaymentService::ipn($q));
    }
    public function returnPage(): void
    {
        $q = $_GET;
        unset($q['r']);
        $valid = hash_equals(PaymentService::sign($q), (string)($q['vnp_SecureHash'] ?? ''));
        $gd = DB::one("SELECT g.*, h.ma_hoa_don, h.ky_cuoc, h.trang_thai_thanh_toan FROM giao_dich_thanh_toan g
                        JOIN hoa_don h ON h.ID_hoa_don=g.ID_hoa_don WHERE ma_giao_dich_ref=?", [$_GET['vnp_TxnRef'] ?? '']);
        View::render('resident/payment_result', ['valid' => $valid, 'gd' => $gd, 'code' => $_GET['vnp_ResponseCode'] ?? ''], 'resident');
    }
    /**
     * Cổng VNPay MÔ PHỎNG (chỉ dùng khi chưa cấu hình TMN code): kiểm tra chữ ký yêu cầu,
     * cho người dùng chọn Thanh toán/Hủy, sau đó gửi IPN có ký HMAC SHA512 rồi chuyển về ReturnUrl – đúng trình tự của VNPay thật.
     */
    public function sandbox(): void
    {
        if (!PaymentService::mockMode()) { http_response_code(404); exit; }
        $q = $_GET;
        unset($q['r']);
        $valid = hash_equals(PaymentService::sign($q), (string)($q['vnp_SecureHash'] ?? ''));
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
            $ok = ($_POST['action'] ?? '') === 'pay';
            $res = ['vnp_Amount' => $q['vnp_Amount'], 'vnp_BankCode' => $_POST['bank'] ?? 'NCB', 'vnp_OrderInfo' => $q['vnp_OrderInfo'],
                'vnp_PayDate' => date('YmdHis'), 'vnp_ResponseCode' => $ok ? '00' : '24', 'vnp_TmnCode' => $q['vnp_TmnCode'],
                'vnp_TransactionNo' => $ok ? (string)random_int(14000000, 14999999) : '0', 'vnp_TransactionStatus' => $ok ? '00' : '02',
                'vnp_TxnRef' => $q['vnp_TxnRef']];
            $res['vnp_SecureHash'] = PaymentService::sign($res);
            PaymentService::ipn($res);                       // 1) tín hiệu ngầm IPN
            $sep = str_contains($q['vnp_ReturnUrl'], '?') ? '&' : '?';
            header('Location: ' . $q['vnp_ReturnUrl'] . $sep . http_build_query($res));   // 2) chuyển hướng ReturnUrl
            exit;
        }
        View::render('resident/vnpay_sandbox', ['q' => $q, 'valid' => $valid]);
    }
}
