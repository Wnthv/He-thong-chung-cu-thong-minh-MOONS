@php
$me = $me ?? \App\Http\Controllers\ResidentController::me();
$r = request()->route()?->getName() ?? 'home';
$bell = $bell ?? 0;
$initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(explode(' ', $me['ho_ten']), -2))));
$tabs = ['home' => ['home', 'Trang chủ'], 'residents' => ['users', 'Cư dân'], 'invoices' => ['file', 'Hóa đơn'],
    'facilities' => ['calendar', 'Tiện ích'], 'tickets' => ['alert', 'Báo hỏng']];
$active = ['vehicles' => 'residents', 'vnpay.return' => 'invoices', 'auth.password' => 'home'][$r] ?? $r;
@endphp<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Moons – Cổng thông tin Cư dân</title>
<link rel="stylesheet" href="{!! asset('assets/css/app.css') !!}">
<meta name="csrf" content="{!! csrf_token() !!}">
</head>
<body class="res">
<div class="phone">
  <header class="r-head">
    <div class="av">{{ $initials }}</div>
    <div class="who"><b>{{ $me['ho_ten'] }}</b><span>Căn hộ {{ $me['ma_can_ho'] }} • {{ lbl('loai_cu_dan', $me['loai_cu_dan']) }}</span></div>
    <a href="{!! route('invoices') !!}" title="Thông báo">{!! icon('bell') !!}@if (!empty($bell))<span class="dot">{!! (int)$bell !!}</span>@endif</a>
    <a href="{!! route('auth.logout') !!}" title="Đăng xuất">{!! icon('logout') !!}</a>
  </header>
  <main class="r-body">
    @foreach (flashes() as [$t, $m])<div class="flash {!! $t !!}">{!! icon($t === 'success' ? 'check' : 'info') !!}<span>{{ $m }}</span></div>@endforeach
    @yield('content')
  </main>
  <button class="fab" data-open-chat>{!! icon('bot') !!} Trợ lý AI</button>
  <nav class="nav">
    @foreach ($tabs as $k => [$ic, $t])
      <a href="{{ route($k) }}" class="{!! $active === $k ? 'on' : '' !!}">{!! icon($ic) !!}{!! $t !!}</a>
    @endforeach
  </nav>
</div>

<div class="chat" id="chat" role="dialog" aria-label="Trợ lý AI Moons Bot">
  <div class="chat-in">
    <div class="chat-h">
      {!! icon('bot') !!}
      <div class="grow"><b>Trợ lý AI Moons Bot</b><div class="on">Trực tuyến 24/7 • {!! cfg('gemini')['api_key'] ? 'Gemini 2.5 Flash + RAG' : 'Chế độ tra cứu nội quy' !!}</div></div>
      <button class="x" data-close-chat aria-label="Đóng">{!! icon('x') !!}</button>
    </div>
    <div class="chat-b" id="chatBody">
      <div class="msg bot">Xin chào {{ explode(' ', $me['ho_ten'])[count(explode(' ', $me['ho_ten'])) - 1] }} (căn hộ {{ $me['ma_can_ho'] }})! Tôi là trợ lý ảo Moons Bot. Tôi có thể trả lời về nội quy, biểu phí, thủ tục, và hóa đơn của riêng bạn.</div>
    </div>
    <div class="chips">
      @foreach (['Giờ mở cửa hồ bơi?', 'Phí quản lý bao nhiêu một mét vuông?', 'Thủ tục đăng ký xe', 'Hóa đơn tháng này của tôi', 'Có được nuôi thú cưng không?'] as $c)
        <button type="button" data-chip>{{ $c }}</button>
      @endforeach
    </div>
    <form class="chat-f" id="chatForm"><input class="inp" name="q" placeholder="Nhập câu hỏi cho AI..." autocomplete="off" maxlength="500"><button aria-label="Gửi">{!! icon('send') !!}</button></form>
  </div>
</div>
<script>window.APP={ask:"{!! route('ai.ask') !!}",csrf:"{!! csrf_token() !!}"};</script>
<script src="{!! asset('assets/vendor/qrcode.js') !!}"></script>
<script src="{!! asset('assets/js/app.js') !!}"></script>
</body>
</html>
