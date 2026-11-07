<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\NhanKhau;
use App\Models\TaiKhoan;
use App\Support\Db;
use App\Support\Rbac;

/* ======================= XÁC THỰC (UC-ALL01, UC-ALL02) ======================= */
final class AuthService
{
    const MAX_FAIL = 5;
    const LOCK_MINUTES = 15;

    private static function findByLogin(string $login): ?array
    {
        return TaiKhoan::where('ten_dang_nhap', $login)->orWhere('email', $login)->orWhere('so_dien_thoai', $login)
            ->first()?->getAttributes();
    }

    /** Kiểm tra các điều kiện chung trước khi cho đăng nhập (khóa, hợp đồng thuê) */
    private static function assertCanLogin(array $u): void
    {
        if ((int)$u['trang_thai'] === 0) {
            throw new BusinessException('Tài khoản của bạn đã bị khóa, vui lòng liên hệ Ban Quản lý.');
        }
        if ($u['khoa_den'] && strtotime($u['khoa_den']) > time()) {
            $m = (int)ceil((strtotime($u['khoa_den']) - time()) / 60);
            throw new BusinessException("Bạn đã nhập sai 5 lần. Tài khoản tạm khóa, thử lại sau {$m} phút.");
        }
        if ($u['vai_tro'] === 'khach_thue') {
            $nk = NhanKhau::where('ID_tai_khoan', $u['ID_tai_khoan'])->first();
            if ($nk && $nk['ngay_ket_thuc_hop_dong'] < app_today()) {
                ResidentService::expireTenant((int)$nk['ID_nhan_khau']);
                throw new BusinessException('Hợp đồng thuê đã hết hạn. Tài khoản khách thuê đã được thu hồi.');
            }
        }
    }

    public static function attempt(string $login, string $password): array
    {
        $u = self::findByLogin($login);
        if (!$u) {
            AuditService::log('dang_nhap_that_bai', 'tai_khoan', null, "Không tồn tại: $login", null);
            throw new BusinessException('Tên đăng nhập hoặc mật khẩu không chính xác.');
        }
        self::assertCanLogin($u);
        if (!password_verify($password, $u['mat_khau_bam'])) {
            $fail = min(self::MAX_FAIL, (int)$u['so_lan_sai'] + 1);
            $lock = $fail >= self::MAX_FAIL ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60) : null;
            TaiKhoan::whereKey($u['ID_tai_khoan'])->update(['so_lan_sai' => $lock ? 0 : $fail, 'khoa_den' => $lock]);
            AuditService::log('dang_nhap_that_bai', 'tai_khoan', $u['ID_tai_khoan'], "Sai mật khẩu lần $fail", (int)$u['ID_tai_khoan']);
            if ($lock) throw new BusinessException('Bạn đã nhập sai 5 lần. Tài khoản tạm khóa 15 phút.');
            throw new BusinessException('Tên đăng nhập hoặc mật khẩu không chính xác.');
        }
        TaiKhoan::whereKey($u['ID_tai_khoan'])->update(['so_lan_sai' => 0, 'khoa_den' => null]);
        AuditService::log('dang_nhap', 'tai_khoan', $u['ID_tai_khoan'], 'Mật khẩu', (int)$u['ID_tai_khoan']);
        return $u;
    }

    /** Gửi OTP 6 chữ số (bản demo hiển thị trên màn hình vì chưa tích hợp nhà mạng SMS) */
    public static function sendOtp(string $login): array
    {
        $u = self::findByLogin($login);
        if (!$u || !in_array($u['vai_tro'], Rbac::RESIDENT, true)) {
            throw new BusinessException('Không tìm thấy cư dân với số điện thoại hoặc email này.');
        }
        self::assertCanLogin($u);
        $otp = (string)random_int(100000, 999999);
        session()->put('otp', ['uid' => (int)$u['ID_tai_khoan'], 'hash' => password_hash($otp, PASSWORD_DEFAULT),
            'exp' => time() + cfg('otp_ttl'), 'tries' => 0]);
        AuditService::log('gui_otp', 'tai_khoan', $u['ID_tai_khoan'], null, (int)$u['ID_tai_khoan']);
        $masked = $u['so_dien_thoai'] ? substr($u['so_dien_thoai'], 0, 6) . 'xxxx' : $u['email'];
        return ['otp' => cfg('otp_demo') ? $otp : null, 'to' => $masked];
    }

    public static function verifyOtp(string $code): array
    {
        $o = session('otp');
        if (!$o) throw new BusinessException('Vui lòng yêu cầu gửi mã OTP.');
        if (time() > $o['exp']) { session()->forget('otp'); throw new BusinessException('Mã OTP đã hết hiệu lực. Vui lòng gửi lại mã.'); }
        session()->put('otp.tries', ++$o['tries']);
        if ($o['tries'] > 5) { session()->forget('otp'); throw new BusinessException('Nhập sai OTP quá nhiều lần.'); }
        if (!password_verify($code, $o['hash'])) throw new BusinessException('Mã OTP không đúng.');
        session()->forget('otp');
        $u = TaiKhoan::findOrFail($o['uid'])->getAttributes();
        self::assertCanLogin($u);
        AuditService::log('dang_nhap', 'tai_khoan', $u['ID_tai_khoan'], 'OTP', (int)$u['ID_tai_khoan']);
        return $u;
    }
}
