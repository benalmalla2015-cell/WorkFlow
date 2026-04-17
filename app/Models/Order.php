<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_name',
        'sales_user_id',
        'factory_user_id',
        'customer_notes',
        'product_name',
        'quantity',
        'specifications',
        'supplier_name',
        'product_code',
        'factory_cost',
        'production_days',
        'selling_price',
        'profit_margin_percentage',
        'final_price',
        'total_price',
        'net_profit',
        'status',
        'pending_changes',
        'pending_change_requested_by',
        'pending_change_requested_at',
        'pending_change_original_status',
        'customer_approval',
        'payment_confirmed',
        'manager_approval',
        'quotation_path',
        'invoice_path',
        'qr_code_path',
    ];

    protected $casts = [
        'profit_margin_percentage' => 'decimal:2',
        'pending_changes' => 'array',
        'pending_change_requested_at' => 'datetime',
        'customer_approval' => 'boolean',
        'payment_confirmed' => 'boolean',
        'manager_approval' => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope('privacy', function ($builder) {
            $user = Auth::user();

            if (!$user) {
                return $builder;
            }

            if ($user->isSales()) {
                $builder->select([
                    'id',
                    'order_number',
                    'customer_id',
                    'customer_name',
                    'sales_user_id',
                    'customer_notes',
                    'product_name',
                    'quantity',
                    'specifications',
                    'selling_price',
                    'final_price',
                    'status',
                    'pending_changes',
                    'pending_change_requested_by',
                    'pending_change_requested_at',
                    'pending_change_original_status',
                    'customer_approval',
                    'payment_confirmed',
                    'manager_approval',
                    'quotation_path',
                    'invoice_path',
                    'qr_code_path',
                    'created_at',
                    'updated_at',
                ]);
            }

            if ($user->isFactory()) {
                $builder->select([
                    'id',
                    'order_number',
                    'sales_user_id',
                    'factory_user_id',
                    'product_name',
                    'quantity',
                    'specifications',
                    'supplier_name',
                    'product_code',
                    'factory_cost',
                    'production_days',
                    'status',
                    'pending_changes',
                    'pending_change_requested_by',
                    'pending_change_requested_at',
                    'pending_change_original_status',
                    'created_at',
                    'updated_at',
                ]);
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function factoryUser()
    {
        return $this->belongsTo(User::class, 'factory_user_id');
    }

    public function pendingChangeRequester()
    {
        return $this->belongsTo(User::class, 'pending_change_requested_by');
    }

    public function adjustmentLogs()
    {
        return $this->hasMany(AdjustmentLog::class)->latest('created_at');
    }

    public function pendingAdjustmentLog()
    {
        return $this->hasOne(AdjustmentLog::class)
            ->where('status', 'pending')
            ->latestOfMany();
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function salesAttachments()
    {
        return $this->attachments()->where('type', 'sales_upload');
    }

    public function factoryAttachments()
    {
        return $this->attachments()->where('type', 'factory_upload');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function setSupplierNameAttribute($value)
    {
        $this->attributes['supplier_name'] = Crypt::encrypt($value);
    }

    public function getSupplierNameAttribute($value)
    {
        return $value ? Crypt::decrypt($value) : null;
    }

    public function setFactoryCostAttribute($value)
    {
        $this->attributes['factory_cost'] = $value ? Crypt::encrypt($value) : null;
    }

    public function getFactoryCostAttribute($value)
    {
        return $value ? Crypt::decrypt($value) : null;
    }

    public function setSellingPriceAttribute($value)
    {
        $this->attributes['selling_price'] = $value ? Crypt::encrypt($value) : null;
    }

    public function getSellingPriceAttribute($value)
    {
        return $value ? Crypt::decrypt($value) : null;
    }

    public function setFinalPriceAttribute($value)
    {
        $this->attributes['final_price'] = $value ? Crypt::encrypt($value) : null;
    }

    public function getFinalPriceAttribute($value)
    {
        return $value ? Crypt::decrypt($value) : null;
    }

    public function setTotalPriceAttribute($value)
    {
        $this->attributes['total_price'] = $value ? Crypt::encrypt($value) : null;
    }

    public function getTotalPriceAttribute($value)
    {
        return $value ? Crypt::decrypt($value) : null;
    }

    public function setNetProfitAttribute($value)
    {
        $this->attributes['net_profit'] = $value ? Crypt::encrypt($value) : null;
    }

    public function getNetProfitAttribute($value)
    {
        return $value ? Crypt::decrypt($value) : null;
    }

    public function setCustomerNotesAttribute($value)
    {
        $this->attributes['customer_notes'] = $value ? Crypt::encrypt($value) : null;
    }

    public function getCustomerNotesAttribute($value)
    {
        return $value ? Crypt::decrypt($value) : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'جديد',
            'sent_to_factory' => 'تم الإرسال إلى المصنع',
            'factory_pricing' => 'عاد لتعديل تشغيلي',
            'manager_review' => 'قيد مراجعة المدير',
            'pending_approval' => 'طلب تعديل بانتظار الاعتماد',
            'approved' => 'معتمد',
            'customer_approved' => 'موافقة العميل مسجلة',
            'payment_confirmed' => 'تم تأكيد الدفع',
            'completed' => 'مكتمل',
            default => ucwords(str_replace('_', ' ', $this->status)),
        };
    }

    public function getWorkflowStageAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'new',
            'sent_to_factory', 'factory_pricing', 'manager_review', 'pending_approval' => 'processing',
            'approved', 'customer_approved', 'payment_confirmed' => 'ready',
            'completed' => 'completed',
            default => 'new',
        };
    }

    public function resolvedCustomerName(): ?string
    {
        if ($this->customer_name) {
            return $this->customer_name;
        }

        if ($this->relationLoaded('customer')) {
            return optional($this->customer)->full_name;
        }

        return optional($this->customer)->full_name;
    }

    public function resolvedItems()
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($items->isNotEmpty()) {
            return $items;
        }

        return collect([
            new OrderItem([
                'item_name' => $this->product_name,
                'quantity' => $this->quantity,
                'description' => $this->specifications,
            ]),
        ])->filter(fn ($item) => filled($item->item_name));
    }

    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function isFactoryPricing()
    {
        return in_array($this->status, ['factory_pricing', 'sent_to_factory'], true);
    }

    public function isManagerReview()
    {
        return $this->status === 'manager_review';
    }

    public function isPendingApproval()
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isCustomerApproved()
    {
        return $this->status === 'customer_approved';
    }

    public function isPaymentConfirmed()
    {
        return in_array($this->status, ['payment_confirmed', 'completed'], true);
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function hasPendingChanges(): bool
    {
        if (!empty($this->pending_changes)) {
            return true;
        }

        if ($this->relationLoaded('pendingAdjustmentLog')) {
            return $this->pendingAdjustmentLog !== null;
        }

        return $this->pendingAdjustmentLog()->exists();
    }

    public function isSentToFactory(): bool
    {
        return in_array($this->status, [
            'sent_to_factory',
            'factory_pricing',
            'manager_review',
            'pending_approval',
            'approved',
            'customer_approved',
            'payment_confirmed',
            'completed',
        ], true);
    }

    public function isLockedForNonAdmin(): bool
    {
        return $this->isSentToFactory();
    }

    public function canRequestAdjustmentBy($user): bool
    {
        if ($user->isAdmin() || $this->hasPendingChanges()) {
            return false;
        }

        if ($user->isSales()) {
            return $this->sales_user_id === $user->id && !$this->isDraft();
        }

        if ($user->isFactory()) {
            return !$this->isDraft()
                && $this->status !== 'sent_to_factory'
                && ($this->factory_user_id === $user->id || $this->status === 'factory_pricing');
        }

        return false;
    }

    public function canBeEditedBy($user)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->hasPendingChanges()) {
            return false;
        }

        if ($user->isFactory() && $this->status === 'sent_to_factory') {
            return true;
        }

        if ($this->isLockedForNonAdmin()) {
            return false;
        }

        if ($user->isSales() && $this->sales_user_id === $user->id) {
            return true;
        }

        if ($user->isFactory()) {
            return $this->factory_user_id === $user->id || $this->status === 'factory_pricing';
        }

        return false;
    }
}
