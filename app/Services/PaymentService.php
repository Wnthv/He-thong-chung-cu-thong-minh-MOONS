<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Support\Db;
use App\Support\Rbac;
use PDOException;

/* ======================= THANH TOÁN VNPay & GẠCH NỢ (UC-RES06, UC-ACC03, UC-ACC04) ======================= */
final class PaymentService
{
    public static function mockMode(): bool { return cfg('vnpay')['tmn_code'] === ''; }

    /** Chuỗi ký theo đặc tả VNPay 2.1.0: sắp xếp khóa, urlencode, HMAC-SHA512 */
    public static function sign(array $params): string
    {
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
        ksort($params);
        $data = [];
        foreach ($params as $k => $v) if ($v !== '' && $v !== null) $data[] = urlencode($k) . '=' . urlencode((string)$v);
        return hash_hmac('sha512', implode('&', $data), cfg('vnpay')['hash_secret']);
    }

    /** Tạo giao dịch và URL thanh toán */
    public static function create(array $me, int $invoiceId, string $returnUrl): string
    {
        $h = BillingService::findVisible($me, $invoiceId);
        if (!$h) throw new BusinessException('Không tìm thấy hóa đơn hoặc hóa đơn ngoài thời hạn hợp đồng thuê.');
        if ($h['trang_thai_thanh_toan'] !== 'chua_thanh_toan') throw new BusinessException('Hóa đơn không ở trạng thái chưa thanh toán.');
        $ref = 'VNP' . date('ymdHis') . random_int(1000, 9999);
        Db::q("INSERT INTO giao_dich_thanh_toan(ma_giao_dich_ref, hinh_thuc, so_tien, ID_hoa_don) VALUES (?, 'vnpay', ?, ?)",
            [$ref, $h['tong_tien'], $invoiceId]);
        $c = cfg('vnpay');
        $p = [
            'vnp_Version' => $c['version'], 'vnp_Command' => 'pay', 'vnp_TmnCode' => $c['tmn_code'] ?: 'MOONSDEMO',
            'vnp_Amount' => (int)round($h['tong_tien'] * 100), 'vnp_CurrCode' => 'VND', 'vnp_TxnRef' => $ref,
            'vnp_OrderInfo' => 'Thanh toan hoa don ' . $h['ma_hoa_don'], 'vnp_OrderType' => 'billpayment',
            'vnp_Locale' => 'vn', 'vnp_ReturnUrl' => $returnUrl, 'vnp_IpAddr' => client_ip(),
            'vnp_CreateDate' => date('YmdHis'), 'vnp_ExpireDate' => date('YmdHis', time() + 900),
        ];
        $p['vnp_SecureHash'] = self::sign($p);
        AuditService::log('tao_giao_dich_vnpay', 'giao_dich_thanh_toan', $ref, money($h['tong_tien']));
        return self::mockMode() ? route('vnpay.sandbox', $p) : $c['pay_url'] . '?' . http_build_query($p);
    }

    /** Xử lý tín hiệu ngầm IPN. Trả về mã phản hồi theo chuẩn VNPay */
    public static function ipn(array $q): array
    {
        $ref = $q['vnp_TxnRef'] ?? '';
        $gd = $ref ? Db::one("SELECT * FROM giao_dich_thanh_toan WHERE ma_giao_dich_ref=?", [$ref]) : null;
        if (!hash_equals(self::sign($q), (string)($q['vnp_SecureHash'] ?? ''))) {
            if ($gd && $gd['trang_thai'] === 'khoi_tao') {
                Db::q("UPDATE giao_dich_thanh_toan SET trang_thai='ngoai_le', ghi_chu='Sai chữ ký HMAC SHA512', chuoi_bam_secure_hash=?
                       WHERE ID_giao_dich=?", [substr((string)($q['vnp_SecureHash'] ?? ''), 0, 256), $gd['ID_giao_dich']]);
            }
            AuditService::log('ipn_sai_chu_ky', 'giao_dich_thanh_toan', $ref, null, null);
            return ['RspCode' => '97', 'Message' => 'Invalid signature'];
        }
        if (!$gd) return ['RspCode' => '01', 'Message' => 'Order not found'];
        if ((int)($q['vnp_Amount'] ?? 0) !== (int)round($gd['so_tien'] * 100)) {
            if ($gd['trang_thai'] === 'khoi_tao')
                Db::q("UPDATE giao_dich_thanh_toan SET trang_thai='ngoai_le', ghi_chu='Sai số tiền IPN' WHERE ID_giao_dich=?", [$gd['ID_giao_dich']]);
            return ['RspCode' => '04', 'Message' => 'Invalid amount'];
        }
        if ($gd['trang_thai'] !== 'khoi_tao') return ['RspCode' => '02', 'Message' => 'Order already confirmed'];

        $ok = ($q['vnp_ResponseCode'] ?? '') === '00' && ($q['vnp_TransactionStatus'] ?? '00') === '00';
        try {
            Db::tx(function () use ($gd, $q, $ok) {
                Db::q("UPDATE giao_dich_thanh_toan SET trang_thai=?, ma_ngan_hang=?, ma_gd_ngan_hang=?, ma_phan_hoi=?, chuoi_bam_secure_hash=?,
                       ngay_giao_dich=? WHERE ID_giao_dich=? AND trang_thai='khoi_tao'",
                    [$ok ? 'thanh_cong' : 'that_bai', $q['vnp_BankCode'] ?? null, $q['vnp_TransactionNo'] ?? null,
                     $q['vnp_ResponseCode'] ?? null, $q['vnp_SecureHash'], app_now(), $gd['ID_giao_dich']]);
                if ($ok) {
                    Db::q("UPDATE hoa_don SET trang_thai_thanh_toan='da_thanh_toan', ngay_thanh_toan=?, phuong_thuc_thanh_toan='vnpay'
                           WHERE ID_hoa_don=?", [app_now(), $gd['ID_hoa_don']]);
                }
            });
        } catch (PDOException $e) {
            // VD: hóa đơn đã được gạch nợ bằng tiền mặt trong lúc chờ VNPay => đưa vào danh sách ngoại lệ đối soát
            Db::q("UPDATE giao_dich_thanh_toan SET trang_thai='ngoai_le', ghi_chu=? WHERE ID_giao_dich=? AND trang_thai='khoi_tao'",
                [mb_substr(db_error_message($e), 0, 250), $gd['ID_giao_dich']]);
            AuditService::log('ipn_ngoai_le', 'giao_dich_thanh_toan', $gd['ma_giao_dich_ref'], db_error_message($e), null);
            return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
        }
        AuditService::log($ok ? 'gach_no_tu_dong' : 'thanh_toan_that_bai', 'giao_dich_thanh_toan', $gd['ma_giao_dich_ref'],
            'ResponseCode ' . ($q['vnp_ResponseCode'] ?? ''), null);
        return ['RspCode' => '00', 'Message' => 'Confirm Success'];
    }

    /** Kế toán gạch nợ thủ công (tiền mặt/chuyển khoản tại quầy) – phải có số chứng từ, số tiền khớp hóa đơn */
    public static function manual(int $invoiceId, string $method, float $amount, string $payer, string $voucher): void
    {
        if (!Rbac::is('ke_toan')) throw new BusinessException('Chỉ Kế toán được gạch nợ thủ công.');
        if (!in_array($method, ['tien_mat', 'chuyen_khoan'], true)) throw new BusinessException('Hình thức không hợp lệ.');
        if (mb_strlen($payer) < 3) throw new BusinessException('Vui lòng nhập người nộp tiền.');
        if (mb_strlen($voucher) < 3) throw new BusinessException('Vui lòng nhập số phiếu thu/biên lai để lưu vết.');
        Db::tx(function () use ($invoiceId, $method, $amount, $payer, $voucher) {
            $h = Db::one("SELECT * FROM hoa_don WHERE ID_hoa_don=? FOR UPDATE", [$invoiceId]);
            if (!$h || $h['trang_thai_thanh_toan'] !== 'chua_thanh_toan') throw new BusinessException('Hóa đơn không ở trạng thái chưa thanh toán.');
            if (abs($amount - (float)$h['tong_tien']) > 0.001)
                throw new BusinessException('Số tiền thu (' . money($amount) . ') phải bằng tổng tiền hóa đơn (' . money($h['tong_tien']) . ').');
            try {
                Db::q("INSERT INTO giao_dich_thanh_toan(ma_giao_dich_ref, hinh_thuc, so_tien, trang_thai, so_chung_tu, nguoi_nop,
                         ID_hoa_don, ID_nguoi_thuc_hien) VALUES (?,?,?,'thanh_cong',?,?,?,?)",
                    ['PT' . date('ymdHis') . random_int(1000, 9999), $method, $amount, $voucher, $payer, $invoiceId, Rbac::id()]);
                Db::q("UPDATE hoa_don SET trang_thai_thanh_toan='da_thanh_toan', ngay_thanh_toan=?, phuong_thuc_thanh_toan=? WHERE ID_hoa_don=?",
                    [app_now(), $method, $invoiceId]);
            } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
            AuditService::log('gach_no_thu_cong', 'hoa_don', $h['ma_hoa_don'], "$voucher - $payer - " . money($amount));
        });
    }
}
