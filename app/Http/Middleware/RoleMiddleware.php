<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use App\Support\Rbac;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware phân quyền (RBAC): chặn truy cập trái phép đường dẫn ngoài phạm vi vai trò.
 * Dùng trong routes/web.php: ->middleware('role:le_tan,admin'). Không truyền vai trò = chỉ cần đăng nhập.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Rbac::user()) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => 'Phiên đăng nhập đã hết hạn.'], 401)
                : to_route('auth.login');
        }
        if ($roles && !in_array(Rbac::role(), $roles, true)) {
            AuditService::log('truy_cap_trai_phep', 'route', $request->path(), 'Vai trò ' . Rbac::role());
            return response()->view('auth.forbidden', [], 403);
        }
        if (!empty(Rbac::user()['phai_doi_mat_khau']) && !$request->routeIs('auth.password')) {
            return to_route('auth.password');
        }
        return $next($request);
    }
}
