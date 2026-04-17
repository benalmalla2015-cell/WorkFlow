<?php

namespace App\Models;

use App\Traits\BuildsArabicAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;
    use BuildsArabicAuditTrail;

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
        return $this->belongsTo(User::class)->withTrashed();
    }
}
