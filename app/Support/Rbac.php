<?php

namespace App\Support;

/**
 * Phiên đăng nhập & phân quyền theo vai trò (RBAC) – lưu trong session của Laravel.
 * Việc chặn truy cập theo vai trò thực hiện ở middleware App\Http\Middleware\RoleMiddleware.
 */
final class Rbac
{
    const RESIDENT = ['chu_ho', 'khach_thue'];
    const STAFF = ['le_tan', 'ke_toan', 'ke_toan_truong', 'dieu_phoi_vien', 'ky_thuat_vien', 'admin'];

    /** Trang mặc định sau khi đăng nhập theo vai trò */
    const HOME = ['chu_ho' => 'home', 'khach_thue' => 'home', 'le_tan' => 'admin.apartments', 'ke_toan' => 'admin.billing',
        'ke_toan_truong' => 'admin.dashboard', 'dieu_phoi_vien' => 'admin.tickets', 'ky_thuat_vien' => 'admin.tickets',
        'admin' => 'admin.dashboard'];

    public static function user(): ?array { return session('user'); }
    public static function id(): ?int { $u = self::user(); return $u ? (int) $u['ID_tai_khoan'] : null; }
    public static function role(): ?string { return self::user()['vai_tro'] ?? null; }
    public static function is(string ...$roles): bool { return in_array(self::role(), $roles, true); }
    public static function isResident(): bool { return self::is(...self::RESIDENT); }

    public static function login(array $u): void
    {
        session()->regenerate();
        session()->put('user', $u);
    }

    public static function logout(): void
    {
        session()->invalidate();
        session()->regenerateToken();
    }
}
