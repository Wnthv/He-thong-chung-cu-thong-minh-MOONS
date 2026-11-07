<div class="warnbox"><b><?= icon('alert') ?> Định mức:</b> tối đa 2 ô tô và 4 xe máy trên một căn hộ (tính cả hồ sơ đang chờ duyệt). Hiện có <?= $used['o_to'] ?>/2 ô tô, <?= $used['xe_may'] ?>/4 xe máy.</div>
<?php $full = $used['o_to'] >= 2 && $used['xe_may'] >= 4; ?>
<section class="card">
  <h2 class="card-t">Thông tin phương tiện</h2>
  <form method="post" action="<?= url('vehicles/register') ?>" enctype="multipart/form-data"><?= Csrf::field() ?>
    <label class="lb">Biển số xe *</label><input class="inp mono" name="bien_so_xe" placeholder="VD: 51G-102.24" required pattern="\d{2}[A-Za-z]{1,2}\d?-(\d{3}\.?\d{2}|\d{4})">
    <div class="grid2">
      <div><label class="lb">Loại xe *</label><select class="inp" name="loai_xe">
        <option value="o_to" <?= $used['o_to'] >= 2 ? 'disabled' : '' ?>>Ô tô<?= $used['o_to'] >= 2 ? ' (đã đủ)' : '' ?></option>
        <option value="xe_may" <?= $used['xe_may'] >= 4 ? 'disabled' : '' ?> <?= $used['o_to'] >= 2 ? 'selected' : '' ?>>Xe máy<?= $used['xe_may'] >= 4 ? ' (đã đủ)' : '' ?></option></select></div>
      <div><label class="lb">Màu xe *</label><input class="inp" name="mau_xe" required></div>
    </div>
    <label class="lb">Nhãn hiệu xe *</label><input class="inp" name="nhan_hieu" placeholder="VD: Mazda CX-5" required>
    <label class="lb">Ảnh cà vẹt xe *</label>
    <label class="drop"><?= icon('camera') ?><span class="drop-t">Chụp ảnh hoặc chọn từ thư viện (tối đa 5MB)</span><input type="file" name="anh_ca_vet_xe" accept="image/*" required></label>
    <button class="btn block mt12" <?= $full ? 'disabled' : '' ?>><?= $full ? 'Đã đạt định mức tối đa' : 'Gửi đơn đăng ký' ?></button>
  </form>
</section>
<section class="card">
  <h2 class="card-t">Danh sách thẻ xe (<?= count($list) ?>)</h2>
  <?php foreach ($list as $x):
    $b = ['da_duyet' => 'bg-green', 'cho_duyet' => 'bg-amber', 'tu_choi' => 'bg-red', 'yeu_cau_bo_sung' => 'bg-blue'][$x['trang_thai_duyet']]; ?>
    <div class="mini">
      <div class="row between"><span><b class="mono"><?= e($x['bien_so_xe']) ?></b> <span class="badge <?= $x['loai_xe'] === 'o_to' ? 'bg-blue' : 'bg-grey' ?>"><?= e(lbl('loai_xe', $x['loai_xe'])) ?></span></span>
        <span class="badge <?= $b ?>"><?= e(lbl('duyet', $x['trang_thai_duyet'])) ?></span></div>
      <div class="xs muted"><?= e($x['nhan_hieu']) ?> • <?= e($x['mau_xe']) ?></div>
      <?php if ($x['ly_do'] && in_array($x['trang_thai_duyet'], ['tu_choi', 'yeu_cau_bo_sung'])): ?><div class="xs" style="color:var(--red-ink)">Lý do: <?= e($x['ly_do']) ?></div><?php endif ?>
      <?php if ($x['trang_thai_duyet'] === 'yeu_cau_bo_sung'): ?>
        <form method="post" action="<?= url('vehicles/resubmit') ?>" enctype="multipart/form-data" class="mt8"><?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= $x['ID_the_xe'] ?>">
          <label class="drop"><?= icon('upload') ?><span class="drop-t">Tải lại ảnh cà vẹt rõ nét</span><input type="file" name="anh_ca_vet_xe" accept="image/*"></label>
          <button class="btn sm mt8">Gửi bổ sung</button></form>
      <?php endif ?>
    </div>
  <?php endforeach ?>
  <?php if (!$list): ?><p class="muted small">Căn hộ chưa đăng ký phương tiện nào.</p><?php endif ?>
</section>
