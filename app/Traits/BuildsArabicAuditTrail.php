<?php

namespace App\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait BuildsArabicAuditTrail
{
    public static function log($action, $model = null, $oldValues = null, $newValues = null, ?array $changedFields = null)
    {
        $resolvedChangedFields = $changedFields ?? static::resolveChangedFields($oldValues, $newValues);

        return static::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $resolvedChangedFields,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function humanActionLabel(): string
    {
        return static::actionLabels()[$this->action] ?? str_replace('_', ' ', (string) $this->action);
    }

    public function humanSummary(): string
    {
        $changeLines = $this->humanChangeLines();

        if ($changeLines !== []) {
            return $changeLines[0] . (count($changeLines) > 1 ? ' وهناك تغييرات إضافية.' : '');
        }

        return sprintf(
            'قام %s بـ %s في %s.',
            $this->actorName(),
            $this->humanActionLabel(),
            $this->humanSubjectLabel()
        );
    }

    public function humanChangeLines(): array
    {
        $old = Arr::dot(is_array($this->old_values) ? $this->old_values : []);
        $new = Arr::dot(is_array($this->new_values) ? $this->new_values : []);

        $keys = collect($this->changed_fields ?: array_unique(array_merge(array_keys($old), array_keys($new))))
            ->filter(fn ($key) => filled($key))
            ->values();

        return $keys
            ->map(function ($key) use ($old, $new) {
                return sprintf(
                    'قام %s بتغيير %s من %s إلى %s في %s',
                    $this->actorName(),
                    $this->humanFieldLabel((string) $key),
                    $this->formatHumanValue($old[$key] ?? null),
                    $this->formatHumanValue($new[$key] ?? null),
                    $this->humanSubjectLabel()
                );
            })
            ->all();
    }

    protected static function resolveChangedFields($oldValues, $newValues): array
    {
        if (!is_array($oldValues) && !is_array($newValues)) {
            return [];
        }

        $old = Arr::dot(is_array($oldValues) ? $oldValues : []);
        $new = Arr::dot(is_array($newValues) ? $newValues : []);
        $changed = [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue != $newValue) {
                $changed[] = $key;
            }
        }

        return array_values(array_unique($changed));
    }

    protected function actorName(): string
    {
        return (string) ($this->user?->name ?: 'مستخدم غير معروف');
    }

    protected function humanSubjectLabel(): string
    {
        $model = class_basename((string) $this->model_type);
        $id = $this->model_id ?: 'غير محدد';

        return match ($model) {
            'Order' => 'الطلب رقم ' . $id,
            'User' => 'المستخدم رقم ' . $id,
            'Setting' => 'إعدادات النظام',
            default => $this->model_type ? ('السجل رقم ' . $id) : 'النظام',
        };
    }

    protected function humanFieldLabel(string $field): string
    {
        $labels = static::fieldLabels();

        if (isset($labels[$field])) {
            return $labels[$field];
        }

        if (preg_match('/^items\.\d+\./', $field)) {
            return 'عناصر الطلب';
        }

        if (preg_match('/^attachments\.\d+\./', $field)) {
            return 'المرفقات';
        }

        return Str::of($field)
            ->replace(['order.', 'customer.'], '')
            ->replace('_', ' ')
            ->trim()
            ->toString();
    }

    protected function formatHumanValue($value): string
    {
        if ($value === null || $value === '') {
            return 'فارغ';
        }

        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return (string) $value;
    }

    protected static function actionLabels(): array
    {
        return [
            'login' => 'تسجيل الدخول',
            'login_failed' => 'محاولة دخول فاشلة',
            'logout' => 'تسجيل الخروج',
            'order_created' => 'إنشاء الطلب',
            'order_updated' => 'تعديل الطلب',
            'order_approved' => 'اعتماد الطلب',
            'order_change_requested' => 'طلب تعديل على الطلب',
            'pending_change_approved' => 'اعتماد طلب التعديل',
            'pending_change_rejected' => 'رفض طلب التعديل',
            'pending_change_revision_requested' => 'طلب مراجعة التعديل',
            'workflow_stage_updated' => 'تحديث مرحلة العمل',
            'status_changed' => 'تغيير الحالة',
            'quotation_generated' => 'توليد عرض سعر',
            'invoice_generated' => 'توليد فاتورة',
            'customer_approval' => 'تأكيد موافقة العميل',
            'payment_confirmed' => 'تأكيد الدفع',
            'settings_updated' => 'تحديث الإعدادات',
            'user_created' => 'إنشاء مستخدم',
            'user_updated' => 'تعديل مستخدم',
            'user_deleted' => 'حذف مستخدم',
            'user_status_toggled' => 'تغيير حالة المستخدم',
            'financial_recalculated' => 'إعادة احتساب القيم المالية',
            'adjustment_approved' => 'اعتماد التعديل المالي',
            'adjustment_rejected' => 'رفض التعديل المالي',
        ];
    }

    protected static function fieldLabels(): array
    {
        return [
            'customer.full_name' => 'اسم العميل',
            'customer.address' => 'العنوان',
            'customer.phone' => 'رقم التواصل',
            'customer.email' => 'البريد الإلكتروني',
            'order.customer_name' => 'اسم العميل على الطلب',
            'order.product_name' => 'اسم المنتج',
            'order.quantity' => 'الكمية',
            'order.specifications' => 'المواصفات',
            'order.customer_notes' => 'ملاحظات العميل',
            'order.supplier_name' => 'اسم المورد',
            'order.product_code' => 'كود المنتج',
            'order.factory_cost' => 'سعر التكلفة',
            'order.production_days' => 'مدة الإنتاج',
            'order.selling_price' => 'سعر البيع',
            'order.profit_margin_percentage' => 'هامش الربح',
            'order.final_price' => 'السعر النهائي',
            'order.total_price' => 'إجمالي السعر',
            'order.net_profit' => 'صافي الربح',
            'order.status' => 'الحالة',
            'factory_cost' => 'سعر التكلفة',
            'selling_price' => 'سعر البيع',
            'profit_margin_percentage' => 'هامش الربح',
            'final_price' => 'السعر النهائي',
            'total_price' => 'إجمالي السعر',
            'net_profit' => 'صافي الربح',
            'quantity' => 'الكمية',
            'status' => 'الحالة',
        ];
    }
}
