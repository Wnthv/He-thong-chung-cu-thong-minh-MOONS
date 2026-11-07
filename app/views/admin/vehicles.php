<div class="cols" style="grid-template-columns:1.7fr 1fr">
<div class="panel">
  <div class="panel-h"><div><h2>Đơn đăng ký thẻ xe chờ duyệt (<?= count($list) ?>)</h2><div class="small muted">Định mức: 2 ô tô và 4 xe máy mỗi căn hộ · Tổng <?= num($total) ?> phương tiện đã duyệt</div></div></div>
  <div class="scroll"><table class="t">
    <thead><tr><th>Căn hộ</th><th>Cư dân</th><th>Biển số</th><th>Loại</th><th>Đã duyệt</th><th>Ảnh cà vẹt</th><th>Thao tác</th></tr></thead>
    <?php foreach ($list as $x): $over = $x['loai_xe'] === 'o_to' ? $x['oto_da_duyet'] >= 2 : $x['xm_da_duyet'] >= 4; ?>
      <tr><td class="apt-code"><?= e($x['ma_can_ho']) ?></td><td><?= e($x['ho_ten']) ?><div class="xs muted"><?= e($x['nhan_hieu']) ?> · <?= e($x['mau_xe']) ?> · <?= vn_date($x['ngay_gui']) ?></div></td>
        <td class="mono b"><?= e($x['bien_so_xe']) ?></td><td><span class="badge <?= $x['loai_xe'] === 'o_to' ? 'bg-blue' : 'bg-grey' ?>"><?= e(lbl('loai_xe', $x['loai_xe'])) ?></span></td>
        <td class="<?= $over ? 'b' : '' ?>" style="<?= $over ? 'color:var(--red-ink)' : '' ?>"><?= $x['oto_da_duyet'] ?>/2 ô tô · <?= $x['xm_da_duyet'] ?>/4 xe máy</td>
        <td><button class="btn light sm" data-modal="imgModal" data-fill='<?= e(json_encode(['src' => upload_url($x['anh_ca_vet_xe']), 'plate' => $x['bien_so_xe']])) ?>' onclick="document.getElementById('imgView').src=JSON.parse(this.dataset.fill).src">Xem ảnh</button></td>
        <td class="nowrap">
          <form method="post" action="<?= url('admin/vehicle-approve') ?>" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= $x['ID_the_xe'] ?>"><button class="btn success sm" <?= $over ? 'title="Căn hộ đã đạt định mức"' : '' ?>>Duyệt</button></form>
          <button class="btn danger sm" data-modal="declineModal" data-fill='{"id":"<?= $x['ID_the_xe'] ?>","plate":"<?= e($x['bien_so_xe']) ?>"}'>Từ chối</button></td></tr>
    <?php endforeach ?>
    <?php if (!$list): ?><tr><td colspan="7" class="center muted">Không có hồ sơ chờ duyệt.</td></tr><?php endif ?>
  </table></div>
</div>
<div>
  <div class="panel"><h2 style="margin:0 0 6px;font-size:15px">Nạp dữ liệu Access Control</h2>
    <form method="post" action="<?= url('admin/card-issue') ?>"><?= Csrf::field() ?>
      <label class="lb">Mã căn hộ</label><input class="inp" name="ma_can_ho" placeholder="VD: A-1001" required>
      <label class="lb">Loại thẻ</label><select class="inp" name="loai_the"><?php foreach (LABELS['loai_the'] as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach ?></select>
      <label class="lb">Mã chip RFID (từ máy đọc thẻ)</label><input class="inp mono" name="rfid" placeholder="Đưa thẻ vào máy đọc...">
      <label class="lb">Mã ID vân tay (đã mã hóa)</label><input class="inp mono" name="van_tay" placeholder="FP-......">
      <button class="btn block mt12"><?= icon('idcard') ?> Cấp mới thẻ</button></form></div>
  <div class="panel"><h2 style="margin:0 0 10px;font-size:15px">Thẻ đã cấp (<?= num($nCards) ?>)</h2>
    <div class="scroll" style="max-height:330px">
    <?php foreach ($cards as $c): $on = $c['trang_thai_the'] === 'kich_hoat'; ?>
      <div class="mini row between" style="<?= $on ? '' : 'background:#FEF2F2' ?>"><div><b><?= e($c['ma_can_ho']) ?></b> · <span class="mono small"><?= e($c['ma_chip_rfid'] ?? $c['ma_id_van_tay']) ?></span><div class="xs muted"><?= e(lbl('loai_the', $c['loai_the'])) ?> · <?= e($c['ho_ten']) ?> · <?= e(lbl('the', $c['trang_thai_the'])) ?></div></div>
        <form method="post" action="<?= url('admin/card-state') ?>"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= $c['ID_the'] ?>"><input type="hidden" name="state" value="<?= $on ? 'tam_khoa' : 'kich_hoat' ?>">
          <button class="btn sm <?= $on ? 'danger' : 'success' ?>"><?= $on ? 'Tạm khóa' : 'Mở khóa' ?></button></form></div>
    <?php endforeach ?></div></div>
</div>
</div>
<div class="modal center" id="imgModal"><div class="sheet"><div class="sheet-h"><h3>Ảnh cà vẹt xe <span data-f="plate"></span></h3><button class="x" data-close><?= icon('x') ?></button></div><img id="imgView" alt="Ảnh cà vẹt" style="width:100%;border-radius:10px"></div></div>
<div class="modal center" id="declineModal"><div class="sheet"><div class="sheet-h"><h3>Xử lý hồ sơ <span data-f="plate"></span></h3><button class="x" data-close><?= icon('x') ?></button></div>
  <form method="post" action="<?= url('admin/vehicle-decline') ?>"><?= Csrf::field() ?><input type="hidden" name="id" data-f="id">
    <label class="lb">Hình thức</label><select class="inp" name="state"><option value="yeu_cau_bo_sung">Yêu cầu bổ sung hồ sơ</option><option value="tu_choi">Từ chối</option></select>
    <label class="lb">Lý do (gửi tới cư dân)</label><input class="inp" name="reason" required minlength="5" placeholder="VD: Ảnh cà vẹt xe bị mờ, không rõ biển số">
    <button class="btn danger block mt12">Xác nhận</button></form></div></div>
