<?php $isTech = Auth::is('ky_thuat_vien'); $isCoord = Auth::is('dieu_phoi_vien'); ?>
<p class="muted" style="margin-top:0"><?= $isTech ? 'Kéo thả thẻ của bạn giữa các cột để cập nhật tiến độ. Kéo sang "Chờ nghiệm thu" để gửi ảnh kết quả.' : ($isCoord ? 'Phân công kỹ thuật viên cho phiếu mới. Phiếu khẩn cấp phải được phân công trong 15 phút.' : 'Chế độ xem: chỉ Điều phối viên phân công và Kỹ thuật viên cập nhật phiếu.') ?></p>
<div class="kanban">
<?php foreach ($cols as $c => $list): ?>
  <section class="kcol" data-col="<?= $isTech ? $c : '' ?>">
    <h3><?= e(lbl('kanban', $c)) ?><span><?= count($list) ?></span></h3>
    <div class="list">
    <?php foreach ($list as $t): $sla = TicketService::slaBreached($t); ?>
      <article class="kcard <?= $t['muc_do_uu_tien'] === 'khan_cap' ? 'urgent' : '' ?>" data-id="<?= $t['ID_phieu'] ?>" data-title="<?= e($t['tieu_de']) ?>"
        <?= $isTech && in_array($c, ['da_phan_cong', 'dang_xu_ly', 'cho_linh_kien']) ? 'draggable="true"' : '' ?>>
        <div class="row between"><span class="badge <?= $t['muc_do_uu_tien'] === 'khan_cap' ? 'bg-red' : 'bg-grey' ?>"><?= e(lbl('muc_do', $t['muc_do_uu_tien'])) ?></span>
          <span class="xs muted">#<?= e($t['ma_phieu']) ?> · <?= $t['pham_vi'] === 'can_ho' ? e($t['ma_can_ho']) : 'Khu vực công cộng' ?></span></div>
        <b><?= e($t['tieu_de']) ?></b>
        <div class="meta">Người gửi: <?= e($t['nguoi_gui']) ?><?= $t['ktv'] ? '<br>KTV: ' . e($t['ktv']) : '' ?><br><?= vn_date($t['ngay_tao'], true) ?></div>
        <?php if ($sla): ?><div class="badge bg-red mt4">Quá SLA 15 phút</div><?php endif ?>
        <?php if ($c === 'cho_linh_kien' && $t['ghi_chu_ky_thuat']): ?><div class="xs mt4">Chờ: <?= e($t['ghi_chu_ky_thuat']) ?></div><?php endif ?>
        <?php if ($t['so_lan_mo_lai']): ?><div class="badge bg-amber mt4">Mở lại <?= $t['so_lan_mo_lai'] ?> lần</div><?php endif ?>
        <?php if ($c === 'hoan_tat' && $t['diem_danh_gia']): ?><div class="xs mt4" style="color:#B45309"><?= str_repeat('★', (int)$t['diem_danh_gia']) ?></div><?php endif ?>
        <?php if ($c === 'cho_tiep_nhan' && $isCoord): ?>
          <div class="grid2 mt8"><button class="btn light sm" data-modal="assignModal" data-fill='<?= e(json_encode(['id' => $t['ID_phieu'], 'title' => $t['tieu_de']], JSON_UNESCAPED_UNICODE)) ?>'>Phân công</button>
          <button class="btn ghost sm" data-modal="rejectModal" data-fill='<?= e(json_encode(['id' => $t['ID_phieu'], 'title' => $t['tieu_de']], JSON_UNESCAPED_UNICODE)) ?>'>Từ chối</button></div>
        <?php endif ?>
        <a class="xs link" href="<?= upload_url($t['anh_hien_truong']) ?>" target="_blank">Ảnh hiện trường</a>
      </article>
    <?php endforeach ?>
    </div>
  </section>
<?php endforeach ?>
</div>
<div class="modal center" id="assignModal"><div class="sheet"><div class="sheet-h"><h3>Phân công: <span data-f="title"></span></h3><button class="x" data-close><?= icon('x') ?></button></div>
  <form method="post" action="<?= url('admin/ticket-assign') ?>"><?= Csrf::field() ?><input type="hidden" name="id" data-f="id">
    <label class="lb">Kỹ thuật viên trực ca</label><select class="inp" name="tech"><?php foreach ($techs as $k): ?><option value="<?= $k['ID_tai_khoan'] ?>"><?= e($k['ho_ten']) ?> – đang xử lý <?= $k['dang_lam'] ?> phiếu</option><?php endforeach ?></select>
    <button class="btn block mt12">Phân công</button></form></div></div>
<div class="modal center" id="rejectModal"><div class="sheet"><div class="sheet-h"><h3>Từ chối: <span data-f="title"></span></h3><button class="x" data-close><?= icon('x') ?></button></div>
  <form method="post" action="<?= url('admin/ticket-reject') ?>"><?= Csrf::field() ?><input type="hidden" name="id" data-f="id">
    <label class="lb">Lý do (phiếu trùng lặp, sai phạm vi...)</label><input class="inp" name="reason" required minlength="5"><button class="btn danger block mt12">Từ chối phiếu</button></form></div></div>
<div class="modal center" id="acceptModal"><div class="sheet"><div class="sheet-h"><h3>Nghiệm thu: <span data-f="title"></span></h3><button class="x" data-close><?= icon('x') ?></button></div>
  <form method="post" action="<?= url('admin/ticket-accept') ?>" enctype="multipart/form-data"><?= Csrf::field() ?><input type="hidden" name="id" data-f="id">
    <label class="lb">Vật tư tiêu hao</label><input class="inp" name="vat_tu" placeholder="VD: 01 gioăng cao su phi 21">
    <label class="lb">Ghi chú kỹ thuật *</label><textarea class="inp" name="ghi_chu" required minlength="5"></textarea>
    <label class="lb">Ảnh kết quả hiện trường * <small>(bắt buộc ít nhất 01 ảnh)</small></label>
    <label class="drop"><?= icon('camera') ?><span class="drop-t">Chụp ảnh kết quả sau khi sửa</span><input type="file" name="anh_nghiem_thu" accept="image/*"></label>
    <button class="btn block mt12">Gửi đề xuất nghiệm thu</button></form></div></div>
