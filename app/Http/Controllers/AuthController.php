<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Services\AccountService;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Support\Rbac;
use Illuminate\Http\Request;

/** Đăng nhập (mật khẩu / OTP), đăng xuất, đổi mật khẩu – UC-ALL01, UC-ALL02 */
class AuthController extends Controller
{
    /** Trang gốc "/": chuyển tới màn hình chính theo vai trò */
    public function root()
    {
        return Rbac::user() ? to_route(Rbac::HOME[Rbac::role()]) : to_route('auth.login');
    }

    public function login(Request $request)
    {
        if ($request->isMethod('post')) {
            try {
                Rbac::login(AuthService::attempt((string) input('login'), (string) $request->input('password', '')));
                return to_route('root');
            } catch (BusinessException $e) {
                flash('error', $e->getMessage());
                return to_route('auth.login')->withInput($request->only('login'));
            }
        }
        if (Rbac::user()) return to_route('root');
        return view('auth.login', ['old' => old('login', '')]);
    }

    public function otpSend()
    {
        return response()->json(['ok' => true] + AuthService::sendOtp((string) input('login')) + ['ttl' => cfg('otp_ttl')]);
    }

    public function otpVerify()
    {
        Rbac::login(AuthService::verifyOtp((string) input('otp')));
        return response()->json(['ok' => true, 'redirect' => route('root')]);
    }

    public function logout()
    {
        if (Rbac::user()) AuditService::log('dang_xuat', 'tai_khoan', Rbac::id());
        Rbac::logout();
        flash('success', 'Bạn đã đăng xuất.');
        return to_route('auth.login');
    }

    public function password(Request $request)
    {
        if ($request->isMethod('post')) {
            AccountService::changePassword(Rbac::id(), (string) $request->input('old'), (string) $request->input('new'), (string) $request->input('confirm'));
            session()->put('user.phai_doi_mat_khau', 0);
            flash('success', 'Đã đổi mật khẩu.');
            return to_route('root');
        }
        return view('auth.password');
    }
}
