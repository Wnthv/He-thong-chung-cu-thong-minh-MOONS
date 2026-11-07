<div class="seg">
  <a href="<?= url('residents') ?>" class="<?= $tab !== 'cards' ? 'on' : '' ?>">Nhân khẩu lưu trú</a>
  <a href="<?= url('residents', ['tab' => 'cards']) ?>" class="<?= $tab === 'cards' ? 'on' : '' ?>">Thẻ từ &amp; Vân tay</a>
</div>
<?php if ($tab !== 'cards'): ?>
  <div class="row between mb0"><h2 class="card-t mb0">Thành viên (<?= count($members) ?>)</h2>
    <button class="btn sm" data-modal="declareModal"><?= icon('plus') ?> Thêm thành viên</button></div>
  <div class="mt12"></div>
  <?php foreach ($members as $m):
    $b = ['da_duyet' => 'bg-green', 'cho_duyet' => 'bg-amber', 'tu_choi' => 'bg-red'][$m['trang_thai_duyet']]; ?>
    <div class="person">
      <div class="row between"><b><?= e($m['ho_ten']) ?></b><span class="badge <?= $b ?>"><?= e(lbl('duyet', $m['trang_thai_duyet'])) ?></span></div>
      <div class="small muted">CCCD: <?= $m['so_cccd'] ? e(substr($m['so_cccd'], 0, 6) . 'xxx' . substr($m['so_cccd'], -3)) : 'Chưa có CCCD' ?></div>
      <div class="xs muted">Quan hệ: <?= e($m['quan_he_chu_ho']) ?> • Loại cư dân: <b><?= e(lbl('loai_cu_dan', $m['loai_cu_dan'])) ?></b>
        <?php if ($m['loai_cu_dan'] === 'khach_thue'): ?> • HĐ đến <?= vn_date($m['ngay_ket_thuc_hop_dong']) ?><?php endif ?></div>
      <?php if ($m['trang_thai_duyet'] === 'tu_choi'): ?><div class="xs" style="color:var(--red-ink)">Lý do: <?= e($m['ly_do_tu_choi']) ?></div><?php endif ?>
      <?php if ($me['loai_cu_dan'] === 'chu_ho' && $m['loai_cu_dan'] === 'thanh_vien' && $m['trang_thai_duyet'] === 'da_duyet'): ?>
        <form method="post" action="<?= url('residents/remove') ?>" class="mt8" onsubmit="return confirm('Xác nhận thành viên này đã chuyển đi?')"><?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= $m['ID_nhan_khau'] ?>"><button class="link small">Báo chuyển đi</button></form>
      <?php endif ?>
    </div>
  <?php endforeach ?>
  <p class="center"><a class="link small" href="<?= url('vehicles') ?>">Quản lý và đăng ký thẻ xe →</a></p>
<?php else: ?>
  <h2 class="card-t">Thẻ đã cấp (<?= count($cards) ?>)</h2>
  <?php foreach ($cards as $c): $on = $c['trang_thai_the'] === 'kich_hoat'; ?>
    <div class="person">
      <div class="row between"><b><?= icon($c['loai_the'] === 'van_tay' ? 'finger' : 'card') ?> <?= e(lbl('loai_the', $c['loai_the'])) ?></b>
        <span class="badge <?= $on ? 'bg-green' : 'bg-grey' ?>"><?= e(lbl('the', $c['trang_thai_the'])) ?></span></div>
      <div class="small muted mono"><?= e($c['ma_chip_rfid'] ?? $c['ma_id_van_tay']) ?> · <?= e($c['ho_ten']) ?></div>
      <?php if ($on): ?><form method="post" action="<?= url('cards/report-lost') ?>" class="mt8" onsubmit="return confirm('Khóa thẻ này vì bị mất?')"><?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= $c['ID_the'] ?>"><button class="btn sm ghost">Yêu cầu báo mất thẻ</button></form><?php endif ?>
    </div>
  <?php endforeach ?>
  <p class="small muted">Hệ thống chỉ lưu mã ID vân tay đã mã hóa từ thiết bị đọc, không lưu ảnh vân tay.</p>
<?php endif ?>

<div class="modal" id="declareModal"><div class="sheet">
  <div class="sheet-h"><h3>Khai báo nhân khẩu</h3><button class="x" data-close><?= icon('x') ?></button></div>
  <form method="post" action="<?= url('residents/declare') ?>" enctype="multipart/form-data"><?= Csrf::field() ?>
    <label class="lb">Mã căn hộ tiếp nhận</label><input class="inp" value="<?= e($me['ma_can_ho']) ?>" disabled>
    <label class="lb">Loại khai báo</label>
    <select class="inp" name="loai_cu_dan" onchange="document.getElementById('hdFields').classList.toggle('hidden', this.value!=='khach_thue')">
      <option value="thanh_vien">Thành viên hộ gia đình</option>
      <?php if ($me['loai_cu_dan'] === 'chu_ho'): ?><option value="khach_thue">Khách thuê (cấp tài khoản)</option><?php endif ?>
    </select>
    <label class="lb">Họ và tên *</label><input class="inp" name="ho_ten" required>
    <div class="grid2">
      <div><label class="lb">Ngày sinh *</label><input class="inp" type="date" name="ngay_sinh" required max="<?= today() ?>"></div>
      <div><label class="lb">Giới tính</label><select class="inp" name="gioi_tinh"><option value="nam">Nam</option><option value="nu">Nữ</option></select></div>
    </div>
    <div class="grid2">
      <div><label class="lb">Số CCCD <small>(bắt buộc từ 14 tuổi)</small></label><input class="inp" name="so_cccd" inputmode="numeric" pattern="\d{12}" maxlength="12"></div>
      <div><label class="lb">Quan hệ với chủ hộ *</label><select class="inp" name="quan_he_chu_ho">
        <?php foreach (['Vợ/Chồng', 'Con', 'Cha/Mẹ', 'Anh/Chị/Em', 'Người thân khác'] as $q): ?><option><?= $q ?></option><?php endforeach ?></select></div>
    </div>
    <div class="grid2">
      <div><label class="lb">Số điện thoại</label><input class="inp" name="so_dien_thoai" inputmode="tel"></div>
      <div><label class="lb">Email</label><input class="inp" type="email" name="email"></div>
    </div>
    <div id="hdFields" class="hidden">
      <label class="lb">Số hợp đồng thuê *</label><input class="inp" name="so_hop_dong">
      <div class="grid2">
        <div><label class="lb">Ngày bắt đầu *</label><input class="inp" type="date" name="ngay_bat_dau_hop_dong"></div>
        <div><label class="lb">Ngày kết thúc *</label><input class="inp" type="date" name="ngay_ket_thuc_hop_dong"></div>
      </div>
    </div>
    <label class="lb">Ảnh CCCD hai mặt / hợp đồng thuê</label>
    <label class="drop"><?= icon('camera') ?><span class="drop-t">Chụp ảnh hoặc chọn từ thư viện (tối đa 5MB)</span><input type="file" name="anh_giay_to" accept="image/*"></label>
    <button class="btn block mt16">Gửi khai báo</button>
  </form>
</div></div>
