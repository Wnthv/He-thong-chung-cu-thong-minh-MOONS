<?php
$u = Auth::user();
$r = $_GET['r'] ?? '';
$menu = [
    'admin/dashboard'  => ['pie', 'Dashboard KPI', ['admin', 'ke_toan_truong', 'ke_toan']],
    'admin/apartments' => ['building', 'Căn hộ và Nhân khẩu', ['le_tan', 'admin', 'ke_toan_truong']],
    'admin/vehicles'   => ['car', 'Duyệt Thẻ xe', ['le_tan', 'admin']],
    'admin/billing'    => ['file', 'Chỉ số và Hóa đơn', ['ke_toan', 'ke_toan_truong', 'admin']],
    'admin/payments'   => ['money', 'Đối soát Giao dịch', ['ke_toan', 'ke_toan_truong', 'admin']],
    'admin/facilities' => ['calendar', 'Lịch Tiện ích', ['le_tan', 'ke_toan_truong', 'admin']],
    'admin/tickets'    => ['wrench', 'Xử lí Sự cố', ['dieu_phoi_vien', 'ky_thuat_vien', 'le_tan', 'admin']],
    'admin/system'     => ['user-cog', 'Phân Quyền và Dữ liệu AI', ['admin']],
];
$initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(explode(' ', $u['ho_ten']), -2))));
$help = [
    'admin' => 'Quản trị tài khoản, phân quyền, dữ liệu ngữ cảnh AI; xem toàn bộ báo cáo; khóa lịch bảo trì. Không sửa trực tiếp dữ liệu tài chính đã chốt sổ.',
    'ke_toan' => 'Nhập chỉ số điện nước, lập hóa đơn (trạng thái Chờ duyệt), gạch nợ thủ công có số chứng từ, đối soát giao dịch, xuất báo cáo công nợ.',
    'ke_toan_truong' => 'Phê duyệt phát hành hóa đơn (không duyệt hóa đơn do chính mình lập), hủy hóa đơn kèm lý do, cập nhật biểu phí, khóa lịch bảo trì.',
    'le_tan' => 'Duyệt nhân khẩu và thẻ xe, cấp/khóa thẻ RFID và vân tay, xác thực vé QR tiện ích, theo dõi phiếu sự cố.',
    'dieu_phoi_vien' => 'Tiếp nhận, phân công hoặc từ chối phiếu sự cố; theo dõi SLA phiếu khẩn cấp (phân công trong 15 phút).',
    'ky_thuat_vien' => 'Cập nhật tiến độ phiếu được giao, ghi vật tư, gửi đề xuất nghiệm thu kèm ảnh kết quả.',
];
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Quản trị') ?> – Chung cư Moons</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="adm">
<aside class="side">
  <div class="brand"><span class="mc">MC</span> Chung cư Moons</div>
  <div class="sec">BẢNG ĐIỀU KHIỂN</div>
  <nav>
    <?php foreach ($menu as $k => [$ic, $t, $roles]): if (!in_array($u['vai_tro'], $roles, true)) continue; ?>
      <a href="<?= url($k) ?>" class="<?= $r === $k ? 'on' : '' ?>"><?= icon($ic) ?><?= $t ?></a>
    <?php endforeach ?>
  </nav>
  <div class="me">
    <div class="av"><?= e($initials) ?></div>
    <div><b><?= e($u['ho_ten']) ?></b><span><?= e(lbl('vai_tro', $u['vai_tro'])) ?> · Ban Quản lý</span></div>
    <a href="<?= url('auth/logout') ?>" title="Đăng xuất"><?= icon('logout') ?></a>
  </div>
</aside>
<div class="main">
  <header class="top">
    <h1><?= e($title ?? '') ?></h1>
    <span class="bell" title="Việc đang chờ xử lý"><?= icon('bell') ?><?php if (!empty($bell)): ?><span class="dot"><?= (int)$bell ?></span><?php endif ?></span>
    <button class="help" data-modal="helpModal">Trợ giúp hệ thống</button>
  </header>
  <div class="content">
    <?php foreach (flashes() as [$t, $m]): ?><div class="flash <?= $t ?>"><?= icon($t === 'success' ? 'check' : 'info') ?><span><?= e($m) ?></span></div><?php endforeach ?>
    <?= $content ?>
  </div>
</div>
<div class="modal center" id="helpModal"><div class="sheet">
  <div class="sheet-h"><h3>Quyền hạn của vai trò <?= e(lbl('vai_tro', $u['vai_tro'])) ?></h3><button class="x" data-close><?= icon('x') ?></button></div>
  <p><?= e($help[$u['vai_tro']] ?? '') ?></p>
  <p class="small muted">Mọi thao tác duyệt, hủy, gạch nợ, khóa đều được ghi vào nhật ký thao tác (chỉ ghi thêm, không sửa/xóa được).</p>
  <a class="btn ghost" href="<?= url('auth/password') ?>">Đổi mật khẩu</a>
</div></div>
<script>window.APP={csrf:"<?= Csrf::token() ?>",move:"<?= url('admin/ticket-move') ?>",unpaid:"<?= url('admin/unpaid') ?>"};</script>
<script src="<?= asset('vendor/qrcode.js') ?>"></script>
<script src="<?= asset('vendor/chart.umd.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
