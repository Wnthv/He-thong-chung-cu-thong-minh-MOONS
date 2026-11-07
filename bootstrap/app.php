<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => RoleMiddleware::class]);
        $middleware->web(append: [SecurityHeaders::class]);
        // Giữ nguyên dữ liệu người dùng nhập (chuỗi rỗng, khoảng trắng trong mật khẩu) – các Service tự kiểm tra hợp lệ
        $middleware->remove([ConvertEmptyStringsToNull::class, TrimStrings::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Lỗi nghiệp vụ: không ghi log, hiển thị thông điệp cho người dùng
        $exceptions->dontReport(BusinessException::class);
        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            flash('error', $e->getMessage());
            return redirect()->back();
        });
    })->create();
