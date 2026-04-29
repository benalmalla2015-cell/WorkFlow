@extends('layouts.app')

@section('title', __('إدارة المستخدمين') . ' | WorkFlow')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ __('إدارة المستخدمين') }}</h1>
            <div class="text-muted">{{ __('إضافة وتعديل وتعطيل المستخدمين وربطهم بالأدوار.') }}</div>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">{{ __('بحث') }}</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="{{ __('الاسم أو البريد') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('الدور') }}</label>
                    <select name="role" class="form-select">
                        <option value="">{{ __('الكل') }}</option>
                        <option value="sales" @selected(($filters['role'] ?? '') === 'sales')>{{ __('مبيعات') }}</option>
                        <option value="factory" @selected(($filters['role'] ?? '') === 'factory')>{{ __('مصنع') }}</option>
                        <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>{{ __('مدير') }}</option>
                    </select>
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-dark">{{ __('تصفية') }}</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('إعادة') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">{{ $editingUser ? __('تعديل مستخدم') : __('إضافة مستخدم جديد') }}</h2>
                    <form method="POST" action="{{ $editingUser ? route('admin.users.update', $editingUser) : route('admin.users.store') }}" class="row g-3">
                        @csrf
                        @if ($editingUser)
                            @method('PUT')
                        @endif
                        <div class="col-12">
                            <label class="form-label">{{ __('الاسم') }}</label>
                            <input type="text" name="name" value="{{ old('name', $editingUser?->name) }}" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('البريد الإلكتروني') }}</label>
                            <input type="email" name="email" value="{{ old('email', $editingUser?->email) }}" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('الهاتف') }}</label>
                            <input type="text" name="phone" value="{{ old('phone', $editingUser?->phone) }}" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('الدور') }}</label>
                            <select name="role" class="form-select" required>
                                <option value="sales" @selected(old('role', $editingUser?->role) === 'sales')>{{ __('مبيعات') }}</option>
                                <option value="factory" @selected(old('role', $editingUser?->role) === 'factory')>{{ __('مصنع') }}</option>
                                <option value="admin" @selected(old('role', $editingUser?->role) === 'admin')>{{ __('مدير') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('كلمة المرور') }} {{ $editingUser ? '(' . __('اختياري') . ')' : '' }}</label>
                            <input type="password" name="password" class="form-control" {{ $editingUser ? '' : 'required' }}>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $editingUser?->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">{{ __('الحساب نشط') }}</label>
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">{{ $editingUser ? __('حفظ التعديلات') : __('إضافة المستخدم') }}</button>
                            @if ($editingUser)
                                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('إلغاء') }}</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card page-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('الاسم') }}</th>
                                    <th>{{ __('البريد الإلكتروني') }}</th>
                                    <th>{{ __('الدور') }}</th>
                                    <th>{{ __('الحالة') }}</th>
                                    <th>{{ __('إجراءات') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $user)
                                    <tr>
                                        <td class="fw-semibold">{{ $user->name }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td><span class="badge text-bg-dark text-uppercase">{{ $user->role }}</span></td>
                                        <td>
                                            @if ($user->is_active)
                                                <span class="badge text-bg-success">{{ __('نشط') }}</span>
                                            @else
                                                <span class="badge text-bg-danger">{{ __('معطل') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="{{ route('admin.users.index', array_merge(request()->query(), ['edit' => $user->id])) }}" class="btn btn-sm btn-outline-primary">{{ __('تعديل') }}</a>
                                                <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">{{ $user->is_active ? __('تعطيل') : __('تفعيل') }}</button>
                                                </form>
                                                @if (auth()->id() !== $user->id)
                                                    <form method="POST" action="{{ route('admin.users.delete', $user) }}" onsubmit="return confirm('{{ __('هل أنت متأكد من حذف المستخدم؟') }}');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('حذف') }}</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">{{ __('لا يوجد مستخدمون.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection
