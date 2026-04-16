@extends('layouts.guest')

@section('title', 'تسجيل الدخول')

@section('content')
    <div class="card guest-card">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <div class="h3 fw-bold text-primary">DAYANCO TRADING CO. LIMITED</div>
                <div class="text-muted">نظام إدارة سير العمل | DAYANCO TRADING CO. LIMITED</div>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="row g-3">
                @csrf
                <div class="col-12">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg" required autofocus>
                </div>
                <div class="col-12">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" name="password" class="form-control form-control-lg" required>
                </div>
                <div class="col-12 form-check mt-2 ms-1">
                    <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                    <label class="form-check-label" for="remember">تذكرني</label>
                </div>
                <div class="col-12 d-grid mt-3">
                    <button type="submit" class="btn btn-primary btn-lg">دخول النظام</button>
                </div>
            </form>
        </div>
    </div>
@endsection
