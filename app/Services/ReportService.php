<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Support\Db;
use App\Support\Rbac;
use Symfony\Component\HttpFoundation\StreamedResponse;

/* ======================= BÁO CÁO & DASHBOARD (UC-ADM04, UC-ACC05) ======================= */
final class ReportService
{
    /** Mọi chỉ tiêu tài chính đều loại trừ hóa đơn "Đã hủy" và "Chờ duyệt" */
    public static function kpi(): array
    {
        $month = substr(app_today(), 0, 7);
        return [
            'occupied' => (int)Db::val("SELECT COUNT(*) FROM can_ho WHERE trang_thai_o<>'trong'"),
            'total' => (int)Db::val("SELECT COUNT(*) FROM can_ho"),
            'residents' => (int)Db::val("SELECT COUNT(*) FROM nhan_khau WHERE trang_thai_duyet='da_duyet'"),
            'vehicles' => (int)Db::val("SELECT COUNT(*) FROM the_xe WHERE trang_thai_duyet='da_duyet'"),
            'collected' => (float)Db::val("SELECT COALESCE(SUM(so_tien),0) FROM giao_dich_thanh_toan WHERE trang_thai='thanh_cong'
                                AND DATE_FORMAT(ngay_giao_dich,'%Y-%m')=?", [$month]),
            'debt' => (float)Db::val("SELECT COALESCE(SUM(tong_tien),0) FROM hoa_don WHERE trang_thai_thanh_toan='chua_thanh_toan'"),
            'tickets_done' => (int)Db::val("SELECT COUNT(*) FROM phieu_su_co WHERE trang_thai_kanban='hoan_tat'"),
            'tickets_total' => (int)Db::val("SELECT COUNT(*) FROM phieu_su_co WHERE trang_thai_kanban NOT IN ('tu_choi','da_huy')"),
            'sla' => Db::one("SELECT COUNT(*) n, SUM(TIMESTAMPDIFF(MINUTE, ngay_tao, ngay_phan_cong) <= 15) ok FROM phieu_su_co
                               WHERE muc_do_uu_tien='khan_cap' AND ngay_phan_cong IS NOT NULL"),
            'rating' => (float)Db::val("SELECT COALESCE(AVG(diem_danh_gia),0) FROM phieu_su_co WHERE diem_danh_gia IS NOT NULL"),
            'month' => $month,
        ];
    }
    /** Doanh thu thực thu 12 tháng gần nhất (theo ngày giao dịch thành công) */
    public static function revenue12(): array
    {
        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = date('Y-m', strtotime(substr(app_today(), 0, 7) . "-01 -$i month"));
            $out[$m] = 0.0;
        }
        foreach (Db::all("SELECT DATE_FORMAT(ngay_giao_dich,'%Y-%m') m, SUM(so_tien) s FROM giao_dich_thanh_toan
                          WHERE trang_thai='thanh_cong' GROUP BY m") as $r) if (isset($out[$r['m']])) $out[$r['m']] = (float)$r['s'];
        return $out;
    }
    public static function debtByTower(): array
    {
        return Db::all("SELECT ch.thap_nha, COALESCE(SUM(h.tong_tien),0) s, COUNT(h.ID_hoa_don) n FROM can_ho ch
                        LEFT JOIN hoa_don h ON h.ID_can_ho=ch.ID_can_ho AND h.trang_thai_thanh_toan='chua_thanh_toan'
                        GROUP BY ch.thap_nha ORDER BY ch.thap_nha");
    }
    public static function debtRows(): array
    {
        return Db::all("SELECT ch.thap_nha, ch.ma_can_ho, h.ma_hoa_don, h.ky_cuoc, h.tong_tien, h.han_thanh_toan,
                               DATEDIFF(?, h.han_thanh_toan) qua_han
                          FROM hoa_don h JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho
                         WHERE h.trang_thai_thanh_toan='chua_thanh_toan' ORDER BY ch.thap_nha, ch.ma_can_ho, h.ky_cuoc", [app_today()]);
    }
    /** Xuất CSV (UTF-8 có BOM để Excel đọc đúng tiếng Việt) */
    public static function csv(string $filename, array $header, array $rows): StreamedResponse
    {
        AuditService::log('xuat_bao_cao', 'bao_cao', $filename);
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $r) fputcsv($out, array_values($r));
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
