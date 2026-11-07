<div class="cols" style="grid-template-columns:1fr 2.4fr">
<div>
  <div class="panel"><h2 style="margin:0;font-size:15px">Kiểm tra mã QR vé điện tử</h2>
    <?php if (Auth::is('le_tan')): ?>
    <form method="post" action="<?= url('admin/ticket-verify') ?>"><?= Csrf::field() ?>
      <input class="inp mono mt12" name="code" placeholder="Quét mã hoặc nhập: MOONS-BBQ-..." required autofocus>
      <button class="btn block mt8"><?= icon('scan') ?> Xác thực vé</button></form>
    <?php else: ?><p class="small muted">Lễ tân thực hiện xác thực vé tại quầy.</p><?php endif ?>
    <?php if ($verified): ?><div class="callout mt12"><b><?= e($verified['ten_tien_ich']) ?></b><br><?= e($verified['ma_can_ho']) ?> · <?= e($verified['ho_ten']) ?><br><?= substr($verified['khung_gio_bat_dau'], 0, 5) ?>–<?= substr($verified['khung_gio_ket_thuc'], 0, 5) ?> · <?= $verified['so_nguoi'] ?> người</div><?php endif ?>
  </div>
  <?php if (Auth::is('ke_toan_truong', 'admin')): ?>
  <div class="panel"><h2 style="margin:0;font-size:15px">Khóa lịch bảo trì</h2>
    <form method="post" action="<?= url('admin/maintenance') ?>" onsubmit="return confirm('Khóa lịch sẽ tự động hủy các lịch đặt trùng. Tiếp tục?')"><?= Csrf::field() ?>
      <label class="lb">Tiện ích</label><select class="inp" name="f"><?php foreach ($fs as $f): ?><option value="<?= $f['ID_tien_ich'] ?>"><?= e($f['ten_tien_ich']) ?></option><?php endforeach ?></select>
      <label class="lb">Ngày</label><input class="inp" type="date" name="date" value="<?= e($date) ?>" min="<?= today() ?>">
      <div class="grid2"><div><label class="lb">Từ</label><input class="inp" type="time" name="start" value="08:00"></div><div><label class="lb">Đến</label><input class="inp" type="time" name="end" value="12:00"></div></div>
      <button class="btn warn block mt12"><?= icon('lock') ?> Khóa lịch bảo trì</button></form></div>
  <?php endif ?>
</div>
<div class="panel">
  <div class="panel-h"><h2>Lịch tổng tiện ích (Master Calendar)</h2>
    <form method="get"><input type="hidden" name="r" value="admin/facilities"><input class="inp" type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"></form></div>
  <?php $times = BookingService::slotList(['gio_mo_cua' => '06:00:00', 'gio_dong_cua' => '22:00:00', 'thoi_luong_khung' => 120]); ?>
  <div class="cal" style="grid-template-columns:110px repeat(<?= count($fs) ?>,1fr)">
    <div class="head">Khung giờ</div><?php foreach ($fs as $f): ?><div class="head"><?= e($f['ten_tien_ich']) ?></div><?php endforeach ?>
    <?php foreach ($times as [$s, $e2]): ?>
      <div class="time"><?= substr($s, 0, 5) ?> - <?= substr($e2, 0, 5) ?></div>
      <?php foreach ($fs as $f): $cell = null; foreach ($grid[$f['ID_tien_ich']] as $g) if ($g['start'] === $s) $cell = $g; ?>
        <?php if (!$cell): ?><div class="cell" style="background:#fff;border-style:dashed">Ngoài giờ</div>
        <?php elseif ($cell['state'] === 'bao_tri'): ?><div class="cell maint">Bảo trì<span class="xs">Khóa hệ thống</span></div>
        <?php elseif (!$f['doc_quyen']): ?><div class="cell <?= $cell['people'] ? ($cell['state'] === 'het_cho' ? 'full' : 'booked') : '' ?>"><?= $cell['people'] ?>/<?= $f['suc_chua_toi_da'] ?> người</div>
        <?php elseif ($cell['booking']): $b = $cell['booking']; ?><div class="cell <?= $b['trang_thai_dat'] === 'da_su_dung' ? 'used' : 'booked' ?>"><b><?= e($b['ma_can_ho']) ?></b><span class="xs"><?= e($b['ho_ten']) ?> · <?= e(lbl('ve', $b['trang_thai_dat'])) ?></span></div>
        <?php else: ?><div class="cell"><?= $cell['state'] === 'qua_gio' ? 'Đã qua' : 'Trống' ?></div><?php endif ?>
      <?php endforeach ?>
    <?php endforeach ?>
  </div>
</div></div>
<div class="panel"><div class="panel-h"><h2>Danh sách đặt lịch gần nhất</h2></div>
  <div class="scroll" style="max-height:360px"><table class="t"><thead><tr><th>Mã QR</th><th>Căn hộ</th><th>Người đặt</th><th>Tiện ích</th><th>Ngày</th><th>Khung giờ</th><th>Số người</th><th>Trạng thái</th></tr></thead>
  <?php foreach ($recent as $b): ?><tr><td class="mono small"><?= e($b['ma_ve']) ?></td><td class="apt-code"><?= e($b['ma_can_ho']) ?></td><td><?= e($b['ho_ten']) ?></td><td><?= e($b['ten_tien_ich']) ?></td>
    <td><?= vn_date($b['ngay_dat']) ?></td><td><?= substr($b['khung_gio_bat_dau'], 0, 5) ?>-<?= substr($b['khung_gio_ket_thuc'], 0, 5) ?></td><td><?= $b['so_nguoi'] ?></td>
    <td><span class="badge <?= ['da_xac_nhan' => 'bg-green', 'da_su_dung' => 'bg-blue', 'da_huy' => 'bg-grey', 'huy_muon' => 'bg-amber'][$b['trang_thai_dat']] ?>"><?= e(lbl('ve', $b['trang_thai_dat'])) ?></span></td></tr><?php endforeach ?>
  </table></div></div>
