@extends('layouts.app')

@section('title', __('الإعدادات') . ' | WorkFlow')

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">{{ __('إعدادات النظام') }}</h1>
        <div class="text-muted">{{ __('ضبط هامش الربح الافتراضي وبيانات الشركة والبنك المستخدمة في الوثائق.') }}</div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="row g-4">
        @csrf
        @method('PUT')

        <div class="col-12">
            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">{{ __('هامش الربح') }}</h2>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('الهامش الافتراضي (%)') }}</label>
                            <input type="number" min="0" max="500" step="0.01" name="default_profit_margin" class="form-control form-control-lg" value="{{ old('default_profit_margin', $settingsData['default_profit_margin'] ?? 20) }}" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card form-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">{{ __('بيانات الشركة') }}</h2>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">{{ __('اسم الشركة') }}</label>
                            <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $settingsData['company_name'] ?? '') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('جهة الاتصال') }}</label>
                            <input type="text" name="company_attn" class="form-control" value="{{ old('company_attn', $settingsData['company_attn'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('الهاتف') }}</label>
                            <input type="text" name="company_phone" class="form-control" value="{{ old('company_phone', $settingsData['company_phone'] ?? '') }}" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">{{ __('البريد الإلكتروني') }}</label>
                            <input type="email" name="company_email" class="form-control" value="{{ old('company_email', $settingsData['company_email'] ?? '') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('العنوان') }}</label>
                            <textarea name="company_address" rows="4" class="form-control" required>{{ old('company_address', $settingsData['company_address'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card form-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">{{ __('البيانات البنكية') }}</h2>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">{{ __('اسم المستفيد') }}</label>
                            <input type="text" name="beneficiary_name" class="form-control" value="{{ old('beneficiary_name', $settingsData['beneficiary_name'] ?? '') }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">{{ __('اسم البنك') }}</label>
                            <input type="text" name="beneficiary_bank" class="form-control" value="{{ old('beneficiary_bank', $settingsData['beneficiary_bank'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('رقم الحساب') }}</label>
                            <input type="text" name="account_number" class="form-control" value="{{ old('account_number', $settingsData['account_number'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('SWIFT') }}</label>
                            <input type="text" name="swift_code" class="form-control" value="{{ old('swift_code', $settingsData['swift_code'] ?? '') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('عنوان المستفيد') }}</label>
                            <textarea name="beneficiary_address" rows="3" class="form-control">{{ old('beneficiary_address', $settingsData['beneficiary_address'] ?? '') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('عنوان البنك') }}</label>
                            <textarea name="bank_address" rows="3" class="form-control">{{ old('bank_address', $settingsData['bank_address'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 d-grid">
            <button type="submit" class="btn btn-primary btn-lg">{{ __('حفظ الإعدادات') }}</button>
        </div>
    </form>
@endsection
