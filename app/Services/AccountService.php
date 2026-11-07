<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\TaiKhoan;
use App\Support\Db;
use App\Support\Rbac;

/* ======================= TÀI KHOẢN & PHÂN QUYỀN (UC-ADM01) ======================= */
final class AccountService
{
    public static function tempPassword(): string
    {
        return 'Mn' . random_int(1000, 9999) . substr(str_shuffle('abcdefghjkmnpqrstuvwxyz'), 0, 3) . '!';
    }
    public static function validatePassword(string $p): void
    {
        if (mb_strlen($p) < 8 || !preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) {
            throw new BusinessException('Mật khẩu phải có ít nhất 8 ký tự, gồm cả chữ và số.');
        }
    }
    public static function createStaff(string $name, string $email, string $phone, string $role): array
    {
        if (!in_array($role, Rbac::STAFF, true)) throw new BusinessException('Vai trò không hợp lệ.');
        if (mb_strlen($name) < 3) throw new BusinessException('Vui lòng nhập họ tên.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new BusinessException('Email không hợp lệ.');
        if (!preg_match('/^0\d{9}$/', $phone)) throw new BusinessException('Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.');
        $username = strtolower(strstr($email, '@', true));
        $pw = self::tempPassword();
        $id = TaiKhoan::create(['ten_dang_nhap' => $username, 'mat_khau_bam' => password_hash($pw, PASSWORD_BCRYPT), 'ho_ten' => $name,
            'email' => $email, 'so_dien_thoai' => $phone, 'vai_tro' => $role, 'phai_doi_mat_khau' => 1])->ID_tai_khoan;
        AuditService::log('cap_tai_khoan', 'tai_khoan', $id, "$username - $role");
        return ['id' => $id, 'username' => $username, 'password' => $pw];
    }
    public static function toggleLock(int $id): string
    {
        if ($id === Rbac::id()) throw new BusinessException('Không thể tự khóa tài khoản đang đăng nhập.');
        $u = TaiKhoan::find($id);
        if (!$u || !in_array($u->vai_tro, Rbac::STAFF, true)) throw new BusinessException('Không tìm thấy tài khoản nhân viên.');
        $new = (int)$u->trang_thai === 1 ? 0 : 1;
        $u->update(['trang_thai' => $new]);
        AuditService::log($new ? 'mo_khoa_tai_khoan' : 'khoa_tai_khoan', 'tai_khoan', $id);
        return $new ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.';
    }
    public static function changePassword(int $uid, string $old, string $new, string $confirm): void
    {
        $u = TaiKhoan::findOrFail($uid);
        if (!password_verify($old, $u->mat_khau_bam)) throw new BusinessException('Mật khẩu hiện tại không đúng.');
        if ($new !== $confirm) throw new BusinessException('Xác nhận mật khẩu không khớp.');
        if ($new === $old) throw new BusinessException('Mật khẩu mới phải khác mật khẩu cũ.');
        self::validatePassword($new);
        $u->update(['mat_khau_bam' => password_hash($new, PASSWORD_BCRYPT), 'phai_doi_mat_khau' => 0]);
        AuditService::log('doi_mat_khau', 'tai_khoan', $uid);
    }
}
