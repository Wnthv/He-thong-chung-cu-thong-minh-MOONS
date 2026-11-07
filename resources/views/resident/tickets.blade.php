@extends('layouts.resident')

@section('content')
<section class="card">
  <h2 class="card-t">Tạo phiếu báo hỏng</h2>
  <form method="post" action="{!! route('tickets.create') !!}" enctype="multipart/form-data">@csrf
    <label class="lb">Tiêu đề sự cố *</label><input class="inp" name="tieu_de" placeholder="VD: Rò rỉ ống nước nhà vệ sinh" required minlength="5">
    <div class="grid2">
      <div><label class="lb">Phạm vi</label><select class="inp" name="pham_vi"><option value="can_ho">Trong căn hộ</option><option value="khu_vuc_chung">Khu vực chung</option></select></div>
      <div><label class="lb">Mức độ</label><select class="inp" name="muc_do_uu_tien"><option value="binh_thuong">Bình thường</option><option value="khan_cap">Khẩn cấp</option></select></div>
    </div>
    <label class="lb">Vị trí cụ thể</label><input class="inp" name="vi_tri_su_co" placeholder="VD: Nhà vệ sinh phòng ngủ chính">
    <label class="lb">Mô tả chi tiết *</label><textarea class="inp" name="mo_ta_chi_tiet" required minlength="10"></textarea>
    <label class="lb">Ảnh hiện trường *</label>
    <label class="drop">{!! icon('camera') !!}<span class="drop-t">Tải ảnh chụp hiện trường bị hỏng</span><input type="file" name="anh_hien_truong" accept="image/*" required></label>
    <button class="btn block mt12">Gửi phiếu báo hỏng</button>
  </form>
</section>
<h2 class="card-t">Theo dõi tiến độ ({!! count($list) !!})</h2>
@php
$steps = ['cho_tiep_nhan' => 'Tiếp nhận phiếu', 'da_phan_cong' => 'Đã phân công Kỹ thuật viên', 'dang_xu_ly' => 'Đang tiến hành sửa chữa', 'cho_nghiem_thu' => 'Chờ cư dân nghiệm thu', 'hoan_tat' => 'Nghiệm thu hoàn tất'];
$order = ['cho_tiep_nhan' => 0, 'da_phan_cong' => 1, 'dang_xu_ly' => 2, 'cho_linh_kien' => 2, 'cho_nghiem_thu' => 3, 'hoan_tat' => 4];
@endphp
@foreach ($list as $t)
@php $pos = $order[$t['trang_thai_kanban']] ?? -1; @endphp
<section class="card">
  <div class="row between"><b>{{ $t['tieu_de'] }}</b>
    <span class="badge {!! $t['muc_do_uu_tien'] === 'khan_cap' ? 'bg-red' : 'bg-grey' !!}">{{ lbl('muc_do', $t['muc_do_uu_tien']) }}</span></div>
  <div class="xs muted">#{{ $t['ma_phieu'] }} • {{ lbl('pham_vi', $t['pham_vi']) }} • {!! vn_date($t['ngay_tao'], true) !!}</div>
  @if (in_array($t['trang_thai_kanban'], ['tu_choi', 'da_huy']))
    <p class="small" style="color:var(--red-ink)">{{ lbl('kanban', $t['trang_thai_kanban']) }}{!! $t['ly_do_tu_choi'] ? ': ' . e($t['ly_do_tu_choi']) : '' !!}</p>
  @else
    <ul class="timeline">
      @php $i = 0; @endphp
      @foreach ($steps as $k => $s) @php $cls = $i < $pos || ($i === $pos && $k === 'hoan_tat') ? 'done' : ($i === $pos ? 'now' : 'todo'); @endphp
        <li class="{!! $cls !!}">{!! $s !!}{!! $i === 2 && $t['trang_thai_kanban'] === 'cho_linh_kien' ? ' (chờ linh kiện)' : '' !!}</li>
      @php $i++; @endphp @endforeach
    </ul>
  @endif
  @if ($t['ktv_ten'])
    <div class="chip-row"><span>{!! icon('user') !!} <b>{{ $t['ktv_ten'] }}</b> · Kỹ thuật viên phụ trách</span><a href="tel:{{ $t['ktv_sdt'] }}">{!! icon('phone') !!}</a></div>
  @endif
  @if ($t['trang_thai_kanban'] === 'cho_nghiem_thu')
    <div class="callout mt8"><b>Kết quả sửa chữa:</b> {{ $t['ghi_chu_ky_thuat'] }}{!! $t['vat_tu_tieu_hao'] ? '<br><b>Vật tư:</b> ' . e($t['vat_tu_tieu_hao']) : '' !!}
      @if ($t['anh_nghiem_thu'])<img src="{!! upload_url($t['anh_nghiem_thu']) !!}" alt="Ảnh nghiệm thu" style="width:100%;border-radius:8px;margin-top:8px">@endif</div>
    <form method="post" action="{!! route('tickets.confirm') !!}" class="mt8">@csrf<input type="hidden" name="id" value="{!! $t['ID_phieu'] !!}">
      <label class="lb">Đánh giá chất lượng</label>
      <div class="stars">@for ($s = 5; $s >= 1; $s--)<input type="radio" name="stars" id="s{!! $t['ID_phieu'] . $s !!}" value="{!! $s !!}" {!! $s === 5 ? 'checked' : '' !!}><label for="s{!! $t['ID_phieu'] . $s !!}">{!! icon('star') !!}</label>@endfor</div>
      <button class="btn block mt8">Xác nhận hài lòng</button></form>
    <form method="post" action="{!! route('tickets.reopen') !!}" class="mt8">@csrf<input type="hidden" name="id" value="{!! $t['ID_phieu'] !!}">
      <input class="inp" name="reason" placeholder="Điểm chưa đạt, cần sửa lại..." required minlength="5"><button class="btn ghost block mt8">Yêu cầu sửa lại</button></form>
  @elseif ($t['trang_thai_kanban'] === 'hoan_tat' && $t['diem_danh_gia'])
    <p class="small muted mt8">Đã đánh giá {!! str_repeat('★', (int)$t['diem_danh_gia']) !!} · hoàn tất {!! vn_date($t['ngay_hoan_thanh'], true) !!}</p>
  @elseif ($t['trang_thai_kanban'] === 'cho_tiep_nhan')
    <form method="post" action="{!! route('tickets.cancel') !!}" class="mt8" onsubmit="return confirm('Hủy phiếu này?')">@csrf<input type="hidden" name="id" value="{!! $t['ID_phieu'] !!}"><button class="link small">Hủy phiếu (sự cố đã tự khắc phục)</button></form>
  @endif
</section>
@endforeach
@endsection
