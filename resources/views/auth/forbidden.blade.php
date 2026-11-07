@extends(\App\Support\Rbac::isResident() ? 'layouts.resident' : 'layouts.admin')

@section('content')
<div class="{!! \App\Support\Rbac::isResident() ? 'card' : 'panel' !!} center">
  <h2>Không có quyền truy cập</h2>
  <p class="muted">Vai trò <b>{{ lbl('vai_tro', \App\Support\Rbac::role()) }}</b> không được phép truy cập chức năng này. Thao tác đã được ghi vào nhật ký hệ thống.</p>
  <a class="btn" href="{!! route('root') !!}">Về trang chính</a>
</div>
@endsection
