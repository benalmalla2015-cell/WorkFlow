<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'changed_fields' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

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

    private static function resolveChangedFields($oldValues, $newValues): array
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

        return $changed;
    }
}
