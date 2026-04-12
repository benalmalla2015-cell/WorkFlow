@extends('layouts.app')

@section('title', 'السجلات التدقيقية | WorkFlow')

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">السجلات التدقيقية</h1>
        <div class="text-muted">تتبع العمليات الحساسة في النظام لحظيًا.</div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">الإجراء</label>
                    <input type="text" name="action" value="{{ $filters['action'] ?? '' }}" class="form-control" placeholder="login, order_created ...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">المستخدم</label>
                    <select name="user_id" class="form-select">
                        <option value="">الكل</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-dark flex-fill">تصفية</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card page-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>الوقت</th>
                            <th>المستخدم</th>
                            <th>الإجراء</th>
                            <th>النموذج</th>
                            <th>المعرف</th>
                            <th>IP</th>
                            <th>تفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                                <td>{{ optional($log->user)->name ?: '—' }}</td>
                                <td><span class="badge text-bg-dark">{{ $log->action }}</span></td>
                                <td>{{ $log->model_type ? class_basename($log->model_type) : '—' }}</td>
                                <td>{{ $log->model_id ?: '—' }}</td>
                                <td>{{ $log->ip_address ?: '—' }}</td>
                                <td>
                                    @if ($log->new_values)
                                        <pre class="small mb-0" style="white-space: pre-wrap;">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">لا توجد سجلات.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
@endsection
