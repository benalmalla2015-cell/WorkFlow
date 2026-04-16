<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdjustmentLog extends Model
{
    use HasFactory;

    protected $table = 'order_adjustments';

    protected $fillable = [
        'order_id',
        'requester_id',
        'reviewer_id',
        'requester_role',
        'type',
        'status',
        'previous_status',
        'target_status',
        'current_payload',
        'proposed_payload',
        'changed_fields',
        'review_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'current_payload' => 'array',
        'proposed_payload' => 'array',
        'changed_fields' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
