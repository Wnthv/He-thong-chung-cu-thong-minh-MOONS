<?php
$me = $me ?? ResidentController::me();
$r = $_GET['r'] ?? 'home';
$initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(explode(' ', $me['ho_ten']), -2))));
$tabs = ['home' => ['home', 'Trang chủ'], 'residents' => ['users', 'Cư dân'], 'invoices' => ['file', 'Hóa đơn'],
    'facilities' => ['calendar', 'Tiện ích'], 'tickets' => ['alert', 'Báo hỏng']];
$active = ['vehicles' => 'residents', 'vnpay/return' => 'invoices', 'auth/password' => 'home'][$r] ?? $r;
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Moons – Cổng thông tin Cư dân</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<meta name="csrf" content="<?= Csrf::token() ?>">
</head>
<body class="res">
<div class="phone">
  <header class="r-head">
    <div class="av"><?= e($initials) ?></div>
    <div class="who"><b><?= e($me['ho_ten']) ?></b><span>Căn hộ <?= e($me['ma_can_ho']) ?> • <?= e(lbl('loai_cu_dan', $me['loai_cu_dan'])) ?></span></div>
    <a href="<?= url('invoices') ?>" title="Thông báo"><?= icon('bell') ?><?php if (!empty($bell)): ?><span class="dot"><?= (int)$bell ?></span><?php endif ?></a>
    <a href="<?= url('auth/logout') ?>" title="Đăng xuất"><?= icon('logout') ?></a>
  </header>
  <main class="r-body">
    <?php foreach (flashes() as [$t, $m]): ?><div class="flash <?= $t ?>"><?= icon($t === 'success' ? 'check' : 'info') ?><span><?= e($m) ?></span></div><?php endforeach ?>
    <?= $content ?>
  </main>
  <button class="fab" data-open-chat><?= icon('bot') ?> Trợ lý AI</button>
  <nav class="nav">
    <?php foreach ($tabs as $k => [$ic, $t]): ?>
      <a href="<?= url($k) ?>" class="<?= $active === $k ? 'on' : '' ?>"><?= icon($ic) ?><?= $t ?></a>
    <?php endforeach ?>
  </nav>
</div>

<div class="chat" id="chat" role="dialog" aria-label="Trợ lý AI Moons Bot">
  <div class="chat-in">
    <div class="chat-h">
      <?= icon('bot') ?>
      <div class="grow"><b>Trợ lý AI Moons Bot</b><div class="on">Trực tuyến 24/7 • <?= cfg('gemini')['api_key'] ? 'Gemini 2.5 Flash + RAG' : 'Chế độ tra cứu nội quy' ?></div></div>
      <button class="x" data-close-chat aria-label="Đóng"><?= icon('x') ?></button>
    </div>
    <div class="chat-b" id="chatBody">
      <div class="msg bot">Xin chào <?= e(explode(' ', $me['ho_ten'])[count(explode(' ', $me['ho_ten'])) - 1]) ?> (căn hộ <?= e($me['ma_can_ho']) ?>)! Tôi là trợ lý ảo Moons Bot. Tôi có thể trả lời về nội quy, biểu phí, thủ tục, và hóa đơn của riêng bạn.</div>
    </div>
    <div class="chips">
      <?php foreach (['Giờ mở cửa hồ bơi?', 'Phí quản lý bao nhiêu một mét vuông?', 'Thủ tục đăng ký xe', 'Hóa đơn tháng này của tôi', 'Có được nuôi thú cưng không?'] as $c): ?>
        <button type="button" data-chip><?= e($c) ?></button>
      <?php endforeach ?>
    </div>
    <form class="chat-f" id="chatForm"><input class="inp" name="q" placeholder="Nhập câu hỏi cho AI..." autocomplete="off" maxlength="500"><button aria-label="Gửi"><?= icon('send') ?></button></form>
  </div>
</div>
<script>window.APP={ask:"<?= url('ai/ask') ?>",csrf:"<?= Csrf::token() ?>"};</script>
<script src="<?= asset('vendor/qrcode.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
