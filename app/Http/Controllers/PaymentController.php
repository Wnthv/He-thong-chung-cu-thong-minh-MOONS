<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Support\Db;
use Illuminate\Http\Request;

/** Thanh toán VNPay: tạo giao dịch, IPN, ReturnUrl, cổng mô phỏng – UC-RES06, UC-ACC03 */
class PaymentController extends Controller
{
    public function create()
    {
        $me = ResidentController::me();
        $url = PaymentService::create($me, (int) input('id'), route('vnpay.return'));
        return response()->json(['ok' => true, 'url' => $url]);
    }

    /** Endpoint IPN: VNPay gọi ngầm (server-to-server), phản hồi JSON {RspCode, Message} */
    public function ipn(Request $request)
    {
        return response()->json(PaymentService::ipn($request->query()));
    }

    public function returnPage(Request $request)
    {
        $q = $request->query();
        $valid = hash_equals(PaymentService::sign($q), (string) ($q['vnp_SecureHash'] ?? ''));
        $gd = Db::one("SELECT g.*, h.ma_hoa_don, h.ky_cuoc, h.trang_thai_thanh_toan FROM giao_dich_thanh_toan g
                        JOIN hoa_don h ON h.ID_hoa_don=g.ID_hoa_don WHERE ma_giao_dich_ref=?", [$q['vnp_TxnRef'] ?? '']);
        return view('resident.payment_result', ['valid' => $valid, 'gd' => $gd, 'code' => $q['vnp_ResponseCode'] ?? '',
            'me' => ResidentController::me(), 'bell' => 0]);
    }

    /**
     * Cổng VNPay MÔ PHỎNG (chỉ dùng khi chưa cấu hình TMN code): kiểm tra chữ ký yêu cầu,
     * cho người dùng chọn Thanh toán/Hủy, sau đó gửi IPN có ký HMAC SHA512 rồi chuyển về ReturnUrl – đúng trình tự của VNPay thật.
     */
    public function sandbox(Request $request)
    {
        abort_unless(PaymentService::mockMode(), 404);
        $q = $request->query();
        $valid = hash_equals(PaymentService::sign($q), (string) ($q['vnp_SecureHash'] ?? ''));
        if ($request->isMethod('post') && $valid) {
            $ok = $request->input('action') === 'pay';
            $res = ['vnp_Amount' => $q['vnp_Amount'], 'vnp_BankCode' => $request->input('bank', 'NCB'), 'vnp_OrderInfo' => $q['vnp_OrderInfo'],
                'vnp_PayDate' => date('YmdHis'), 'vnp_ResponseCode' => $ok ? '00' : '24', 'vnp_TmnCode' => $q['vnp_TmnCode'],
                'vnp_TransactionNo' => $ok ? (string) random_int(14000000, 14999999) : '0', 'vnp_TransactionStatus' => $ok ? '00' : '02',
                'vnp_TxnRef' => $q['vnp_TxnRef']];
            $res['vnp_SecureHash'] = PaymentService::sign($res);
            PaymentService::ipn($res);                                   // 1) tín hiệu ngầm IPN
            $sep = str_contains($q['vnp_ReturnUrl'], '?') ? '&' : '?';
            return redirect()->away($q['vnp_ReturnUrl'] . $sep . http_build_query($res));   // 2) chuyển hướng ReturnUrl
        }
        return view('resident.vnpay_sandbox', ['q' => $q, 'valid' => $valid]);
    }
}
