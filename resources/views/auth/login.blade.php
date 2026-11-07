<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Đăng nhập – Moons Complex</title>
<link rel="stylesheet" href="{!! asset('assets/css/app.css') !!}">
</head>
<body class="res">
<div class="otp-banner" id="otpBanner">{!! icon('info') !!} Tin nhắn demo: mã OTP của bạn là <b></b></div>
<div class="phone" style="padding-bottom:30px">
  <div class="login-top">
    <div class="logo-sq">MC</div>
    <h1>Moons Complex</h1>
    <p>Cổng thông tin Cư dân &amp; Ban Quản lý</p>
    <p class="hello">Chào mừng bạn trở về nhà!</p>
  </div>
  <div class="r-body">
    @foreach (flashes() as [$t, $m])<div class="flash {!! $t !!}">{!! icon('info') !!}<span>{{ $m }}</span></div>@endforeach
    <form method="post" action="{!! route('auth.login') !!}" autocomplete="on">
      @csrf
      <label class="lb" for="login">Số điện thoại hoặc Email</label>
      <input class="inp" id="login" name="login" value="{{ $old }}" placeholder="Đã đăng ký với Ban Quản lý" required>
      <label class="lb" for="pw">Mật khẩu</label>
      <div style="position:relative">
        <input class="inp" id="pw" name="password" type="password" required style="padding-right:44px">
        <button type="button" class="x" data-toggle-pw style="position:absolute;right:8px;top:8px" aria-label="Hiện mật khẩu">{!! icon('eye') !!}</button>
      </div>
      <button class="btn block mt16" style="font-size:15px">Đăng nhập</button>
    </form>
    <p class="center mt16"><button type="button" class="link" id="otpLink">Đăng nhập nhanh bằng mã OTP</button></p>
    <p class="center small muted">Nhân viên Ban Quản lý đăng nhập bằng tên đăng nhập được cấp.</p>
  </div>
</div>
<div class="modal" id="otpModal"><div class="sheet">
  <div class="sheet-h"><h3>Xác thực mã OTP</h3><button class="x" data-close>{!! icon('x') !!}</button></div>
  <form id="otpForm" action="{!! route('auth.otp-verify') !!}" data-send="{!! route('auth.otp-send') !!}">
    <p class="mt0">Mã gồm 6 chữ số đã gửi tới <b id="otpTo"></b>.</p>
    <div class="otp-boxes">@for ($i = 0; $i < 6; $i++)<input inputmode="numeric" maxlength="1" aria-label="Số {!! $i + 1 !!}">@endfor</div>
    <p class="center muted">Mã hết hiệu lực sau <b id="otpLeft">60</b>s</p>
    <p class="center small" id="otpErr" style="color:var(--red-ink)"></p>
    <button class="btn block">Xác nhận</button>
    <p class="center"><button type="button" class="link" id="otpResend" style="color:#9CA3AF">Gửi lại mã</button></p>
  </form>
</div></div>
<script>window.APP={csrf:"{!! csrf_token() !!}"};</script>
<script src="{!! asset('assets/js/app.js') !!}"></script>
</body>
</html>
