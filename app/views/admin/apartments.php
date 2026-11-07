<div class="panel"><form class="filters" method="get"><input type="hidden" name="r" value="admin/apartments">
  <select class="inp" name="tower" onchange="this.form.submit()"><?php foreach (['A', 'B', 'C', 'D'] as $t): ?><option <?= $tower === $t ? 'selected' : '' ?> value="<?= $t ?>">Tháp <?= $t ?></option><?php endforeach ?></select>
  <select class="inp" name="floor" onchange="this.form.submit()"><option value="">Tất cả tầng</option><?php for ($i = 1; $i <= 28; $i++): ?><option <?= $floor == $i ? 'selected' : '' ?> value="<?= $i ?>">Tầng <?= $i ?></option><?php endfor ?></select>
  <select class="inp" name="state" onchange="this.form.submit()"><option value="">Tất cả trạng thái</option><?php foreach (LABELS['trang_thai_o'] as $k => $v): ?><option <?= $state === $k ? 'selected' : '' ?> value="<?= $k ?>"><?= $v ?></option><?php endforeach ?></select>
  <input class="inp" name="q" value="<?= e($q) ?>" placeholder="Tìm mã căn hộ hoặc tên cư dân..." style="min-width:260px">
  <span class="badge bg-red"><?= $pending ?> hồ sơ chờ duyệt</span>
  <div class="legend"><span><i style="background:var(--tile-green)"></i>Chủ hộ</span><span><i style="background:var(--tile-blue)"></i>Khách thuê</span><span><i style="background:var(--tile-grey)"></i>Trống</span></div>
</form></div>
<?php foreach ($byFloor as $fl => $apts): ?>
  <section class="floor"><h3>Tầng <?= $fl ?> - Tháp <?= e($tower) ?><?= $fl == 1 ? ' (Shophouse)' : '' ?></h3>
    <div class="tiles">
      <?php foreach ($apts as $a): ?>
        <a class="tile <?= $a['trang_thai_o'] ?> <?= $detail && $detail['apt']['ID_can_ho'] == $a['ID_can_ho'] ? 'sel' : '' ?>" href="<?= url('admin/apartments', ['tower' => $tower, 'floor' => $floor, 'state' => $state, 'q' => $q, 'apt' => $a['ma_can_ho']]) ?>">
          <?php if ($a['cho_duyet']): ?><span class="new">Mới</span><?php endif ?>
          <b><?= e($a['ma_can_ho']) ?></b><span><?= e($a['dai_dien'] ?? 'Trống') ?></span><span><?= $a['so_nguoi'] ?> người · <?= num($a['dien_tich']) ?>m²</span></a>
      <?php endforeach ?>
    </div></section>
<?php endforeach ?>
<?php if (!$byFloor): ?><div class="panel muted">Không có căn hộ phù hợp bộ lọc.</div><?php endif ?>

<?php if ($detail): $o = null; foreach ($detail['people'] as $p) if ($p['loai_cu_dan'] !== 'thanh_vien' && $p['trang_thai_duyet'] === 'da_duyet') { $o = $p; if ($p['loai_cu_dan'] === 'chu_ho') break; } ?>
<aside class="drawer">
  <div class="row between"><h2>Căn hộ <?= e($detail['apt']['ma_can_ho']) ?></h2><a class="x" href="<?= url('admin/apartments', ['tower' => $tower, 'floor' => $floor, 'state' => $state, 'q' => $q]) ?>"><?= icon('x') ?></a></div>
  <?php if ($o): ?><div class="owner"><small>HỒ SƠ <?= mb_strtoupper(lbl('loai_cu_dan', $o['loai_cu_dan'])) ?></small><div class="b" style="font-size:16px"><?= e($o['ho_ten']) ?></div>
    <div class="small muted">SĐT: <?= e(substr((string)$o['so_dien_thoai'], 0, 4)) ?>xxx<?= e(substr((string)$o['so_dien_thoai'], -3)) ?> · Diện tích <?= num($detail['apt']['dien_tich']) ?>m² · <?= e(lbl('trang_thai_o', $detail['apt']['trang_thai_o'])) ?></div></div><?php endif ?>
  <h3>Nhân khẩu (<?= count($detail['people']) ?>)</h3>
  <?php foreach ($detail['people'] as $p): ?>
    <div class="mini"><div class="row between"><b><?= e($p['ho_ten']) ?></b><span class="badge <?= $p['trang_thai_duyet'] === 'da_duyet' ? 'bg-green' : 'bg-amber' ?>"><?= e(lbl('duyet', $p['trang_thai_duyet'])) ?></span></div>
      <div class="xs muted"><?= e($p['quan_he_chu_ho']) ?> · <?= e(lbl('loai_cu_dan', $p['loai_cu_dan'])) ?><?= $p['ngay_sinh'] ? ' · Sinh ' . date('Y', strtotime($p['ngay_sinh'])) : '' ?> · CCCD: <?= $p['so_cccd'] ? e(substr($p['so_cccd'], 0, 3) . 'xxxxxx' . substr($p['so_cccd'], -3)) : '—' ?></div>
      <?php if ($p['loai_cu_dan'] === 'khach_thue'): ?><div class="xs muted">HĐ <?= e($p['so_hop_dong']) ?>: <?= vn_date($p['ngay_bat_dau_hop_dong']) ?> – <?= vn_date($p['ngay_ket_thuc_hop_dong']) ?></div><?php endif ?>
      <?php if ($p['anh_giay_to']): ?><a class="xs link" href="<?= upload_url($p['anh_giay_to']) ?>" target="_blank">Xem ảnh giấy tờ</a><?php endif ?>
      <?php if ($p['trang_thai_duyet'] === 'cho_duyet' && Auth::is('le_tan')): ?>
        <div class="grid2 mt8">
          <form method="post" action="<?= url('admin/resident-approve') ?>"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= $p['ID_nhan_khau'] ?>"><input type="hidden" name="tower" value="<?= e($tower) ?>"><input type="hidden" name="apt" value="<?= e($detail['apt']['ma_can_ho']) ?>"><button class="btn success sm" style="width:100%">Phê duyệt</button></form>
          <form method="post" action="<?= url('admin/resident-reject') ?>" onsubmit="this.reason.value=prompt('Lý do từ chối:')||'';return this.reason.value.length>=5"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= $p['ID_nhan_khau'] ?>"><input type="hidden" name="reason"><input type="hidden" name="tower" value="<?= e($tower) ?>"><input type="hidden" name="apt" value="<?= e($detail['apt']['ma_can_ho']) ?>"><button class="btn danger sm" style="width:100%">Từ chối</button></form>
        </div>
      <?php endif ?>
    </div>
  <?php endforeach ?>
  <h3>Phương tiện (<?= count($detail['vehicles']) ?>)</h3>
  <?php foreach ($detail['vehicles'] as $v): ?><div class="mini row between"><span class="mono b"><?= e($v['bien_so_xe']) ?></span><span><?= e(lbl('loai_xe', $v['loai_xe'])) ?></span><span class="badge <?= $v['trang_thai_duyet'] === 'da_duyet' ? 'bg-green' : 'bg-amber' ?>"><?= e(lbl('duyet', $v['trang_thai_duyet'])) ?></span></div><?php endforeach ?>
  <?php if ($detail['invoice']): $i = $detail['invoice']; ?>
    <h3>Hóa đơn <?= e(mb_strtolower(ky_label($i['ky_cuoc']))) ?></h3>
    <div class="mini row between"><b><?= money($i['tong_tien']) ?></b><span class="badge <?= $i['trang_thai_thanh_toan'] === 'da_thanh_toan' ? 'bg-green' : 'bg-red' ?>"><?= e(lbl('hd', $i['trang_thai_thanh_toan'])) ?></span></div>
  <?php endif ?>
</aside>
<?php endif ?>
