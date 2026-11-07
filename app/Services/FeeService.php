<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\BieuPhi;
use App\Support\Db;
use App\Support\Rbac;

/* ======================= BIỂU PHÍ (UC-ADM02) ======================= */
final class FeeService
{
    public static function all(): array
    {
        return BieuPhi::orderBy('ID_bieu_phi')->get()->keyBy('ma_phi')->map->getAttributes()->all();
    }
    public static function update(array $prices): void
    {
        if (!Rbac::is('ke_toan_truong')) throw new BusinessException('Chỉ Kế toán trưởng được phê duyệt thay đổi biểu phí.');
        Db::tx(function () use ($prices) {
            foreach ($prices as $code => $v) {
                $v = (float)str_replace(['.', ','], ['', '.'], (string)$v);
                if ($v < 0) throw new BusinessException('Đơn giá không được âm.');
                $old = Db::val("SELECT don_gia FROM bieu_phi WHERE ma_phi=?", [$code]);
                if ($old === null || (float)$old === $v) continue;
                Db::q("UPDATE bieu_phi SET don_gia=?, ngay_cap_nhat=?, ID_nguoi_cap_nhat=? WHERE ma_phi=?", [$v, app_now(), Rbac::id(), $code]);
                AuditService::log('cap_nhat_bieu_phi', 'bieu_phi', $code, num($old) . ' -> ' . num($v));
            }
        });
    }
    /** Tách tiền điện theo bậc thang: mỗi bậc là 1 dòng chi tiết (so_luong × don_gia) */
    public static function electricTiers(int $kwh, ?array $fees = null): array
    {
        $fees = $fees ?? self::all();
        $lines = [];
        $remain = $kwh;
        $prevCap = 0;   // bậc n bao gồm (den_muc bậc n-1, den_muc bậc n]
        for ($b = 1; $b <= 6 && $remain > 0; $b++) {
            $f = $fees["DIEN_B$b"];
            $cap = $f['den_muc'] === null ? PHP_INT_MAX : (int)$f['den_muc'];
            $q = min($remain, $cap - $prevCap);
            $lines[] = ['loai_cuoc' => 'dien', 'dien_giai' => "Điện bậc $b ({$f['tu_muc']}" . ($f['den_muc'] ? "–{$f['den_muc']}" : '+') . " kWh)",
                'so_luong' => $q, 'don_gia' => (float)$f['don_gia'], 'thanh_tien' => round($q * (float)$f['don_gia'])];
            $remain -= $q;
            $prevCap = $cap;
        }
        return $lines;
    }
}
