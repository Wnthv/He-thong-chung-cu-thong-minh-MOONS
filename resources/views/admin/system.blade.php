@extends('layouts.admin')

@section('content')
<div class="tabs2"><a class="{!! $tab === 'accounts' ? 'on' : '' !!}" href="{!! route('admin.system') !!}">Quản lý Tài khoản và Phân quyền</a><a class="{!! $tab === 'ai' ? 'on' : '' !!}" href="{!! route('admin.system', ['tab' => 'ai']) !!}">Quản lý Dữ liệu AI</a></div>
@if ($tab === 'accounts') @php $colors = ['admin' => 'bg-purple', 'ke_toan' => 'bg-amber', 'ke_toan_truong' => 'bg-amber', 'le_tan' => 'bg-blue', 'dieu_phoi_vien' => 'bg-pink', 'ky_thuat_vien' => 'bg-teal']; @endphp
<div class="cols" style="grid-template-columns:2fr 1fr">
  <div class="panel"><div class="panel-h"><h2>Danh sách tài khoản nhân viên Ban Quản lý ({!! count($staff) !!})</h2>
    <span class="small muted">Đang hoạt động: {!! count(array_filter($staff, fn($s) => $s['trang_thai'])) !!} · Đã khóa: {!! count(array_filter($staff, fn($s) => !$s['trang_thai'])) !!}</span></div>
    <table class="t"><tr><th>Họ tên</th><th>Tên đăng nhập</th><th>Email / SĐT</th><th>Vai trò</th><th>Trạng thái</th><th>Thao tác</th></tr>
    @foreach ($staff as $s)<tr><td class="b">{{ $s['ho_ten'] }}</td><td class="mono small">{{ $s['ten_dang_nhap'] }}</td><td class="small">{{ $s['email'] }}<br>{{ $s['so_dien_thoai'] }}</td>
      <td><span class="badge {!! $colors[$s['vai_tro']] !!}">{{ lbl('vai_tro', $s['vai_tro']) }}</span></td>
      <td class="b" style="color:{!! $s['trang_thai'] ? 'var(--green-ink)' : 'var(--red-ink)' !!}">{!! $s['trang_thai'] ? 'Hoạt động' : 'Đã khóa' !!}{!! $s['phai_doi_mat_khau'] ? '<div class="xs muted">Chưa đổi MK tạm</div>' : '' !!}</td>
      <td>@if ($s['ID_tai_khoan'] != \App\Support\Rbac::id())<form method="post" action="{!! route('admin.account-lock') !!}">@csrf<input type="hidden" name="id" value="{!! $s['ID_tai_khoan'] !!}"><button class="btn sm {!! $s['trang_thai'] ? 'danger' : 'success' !!}">{!! icon($s['trang_thai'] ? 'lock' : 'unlock') !!} {!! $s['trang_thai'] ? 'Khóa tài khoản' : 'Mở khóa' !!}</button></form>@endif</td></tr>@endforeach
    </table></div>
  <div class="panel"><h2 style="margin:0;font-size:15px">Cấp tài khoản mới</h2>
    @if ($created)<div class="callout mt12">Tên đăng nhập: <b class="mono">{{ $created['username'] }}</b><br>Mật khẩu tạm: <b class="mono">{{ $created['password'] }}</b><br><span class="small muted">Chỉ hiển thị một lần. Nhân viên phải đổi mật khẩu ở lần đăng nhập đầu tiên.</span></div>@endif
    <form method="post" action="{!! route('admin.account-create') !!}">@csrf
      <label class="lb">Họ tên</label><input class="inp" name="ho_ten" required><label class="lb">Email</label><input class="inp" type="email" name="email" required>
      <label class="lb">Số điện thoại</label><input class="inp" name="sdt" required pattern="0\d{9}">
      <label class="lb">Vai trò chuyên môn</label><select class="inp" name="vai_tro">@foreach (\App\Support\Rbac::STAFF as $r)<option value="{!! $r !!}">{{ lbl('vai_tro', $r) }}</option>@endforeach</select>
      <button class="btn block mt12">{!! icon('userplus') !!} Cấp tài khoản</button></form>
    <p class="note">Hệ thống tạo mật khẩu tạm, nhân viên phải đổi ở lần đăng nhập đầu tiên.</p></div>
</div>
@else
@php $draft = count(array_filter($docs, fn($d) => $d['trang_thai'] === 'nhap')); @endphp
<div class="cols" style="grid-template-columns:2fr 1fr">
  <div class="panel"><div class="panel-h"><h2>Danh mục tài liệu ngữ cảnh cho Trợ lý Gemini RAG ({!! count($docs) !!})</h2><span class="small muted">{!! $draft !!} tài liệu nháp chưa đưa vào sử dụng</span></div>
    <div class="scroll" style="max-height:600px"><table class="t"><thead><tr><th>Tiêu đề</th><th>Loại</th><th>Cập nhật</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    @foreach ($docs as $d)<tr><td><b>{{ $d['tieu_de_tai_lieu'] }}</b><div class="xs muted">{{ $d['ho_ten'] }} · {!! mb_strlen($d['noi_dung_ngu_canh']) !!} ký tự</div></td><td class="small">{{ $d['loai_tai_lieu'] }}</td>
      <td class="small">{!! date('d-m-Y', strtotime($d['ngay_cap_nhat'])) !!}</td><td><span class="badge {!! $d['trang_thai'] === 'dang_su_dung' ? 'bg-green' : 'bg-amber' !!}">{!! $d['trang_thai'] === 'dang_su_dung' ? 'Đang sử dụng' : 'Nháp' !!}</span></td>
      <td class="nowrap"><a class="link small" href="{!! route('admin.system', ['tab' => 'ai', 'edit' => $d['ID_ngu_canh']]) !!}">Sửa</a>
        <form method="post" action="{!! route('admin.ai-delete') !!}" style="display:inline" onsubmit="return confirm('Xóa tài liệu này?')">@csrf<input type="hidden" name="id" value="{!! $d['ID_ngu_canh'] !!}"><button class="link small" style="color:var(--red)">Xóa</button></form></td></tr>@endforeach
    </table></div></div>
  <div class="panel"><h2 style="margin:0;font-size:15px">{!! $edit ? 'Sửa tài liệu' : 'Thêm tài liệu mới' !!}</h2>
    <form method="post" action="{!! route('admin.ai-save') !!}" enctype="multipart/form-data">@csrf<input type="hidden" name="id" value="{!! $edit['ID_ngu_canh'] ?? '' !!}">
      <label class="drop mt12">{!! icon('upload') !!}<span class="drop-t">Tải lên tệp văn bản (.txt, .md)</span><input type="file" name="tep" accept=".txt,.md"></label>
      <label class="lb">Tiêu đề tài liệu</label><input class="inp" name="tieu_de" value="{{ $edit['tieu_de_tai_lieu'] ?? '' }}" required>
      <label class="lb">Loại tài liệu</label><select class="inp" name="loai">@foreach (['Nội quy tòa nhà', 'Nội quy tiện ích', 'Quy trình', 'Quy định tài chính', 'Biểu phí', 'Bảo mật'] as $l)<option {!! ($edit['loai_tai_lieu'] ?? '') === $l ? 'selected' : '' !!}>{!! $l !!}</option>@endforeach</select>
      <label class="lb">Nội dung ngữ cảnh (Context Data)</label><textarea class="inp" name="noi_dung" style="min-height:160px">{{ $edit['noi_dung_ngu_canh'] ?? '' }}</textarea>
      <button class="btn ghost block mt12">{!! $edit ? 'Lưu thay đổi' : 'Thêm tài liệu' !!}</button></form>
    <form method="post" action="{!! route('admin.ai-publish') !!}" class="mt8">@csrf<button class="btn block">{!! icon('brain') !!} Đóng gói và cập nhật ngữ cảnh AI</button></form>
    <p class="note">Tài liệu được chia đoạn và truy xuất theo từ khóa khi cư dân hỏi (Retrieval). Chỉ các đoạn liên quan nhất được gửi kèm câu hỏi tới Gemini. Hệ thống không huấn luyện lại mô hình.</p></div>
</div>
@endif
@endsection
