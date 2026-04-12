<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
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
        'status',
        'customer_approval',
        'payment_confirmed',
        'manager_approval',
        'quotation_path',
        'invoice_path',
        'qr_code_path',
    ];

    protected $casts = [
        'profit_margin_percentage' => 'decimal:2',
        'customer_approval' => 'boolean',
        'payment_confirmed' => 'boolean',
        'manager_approval' => 'boolean',
    ];
    // Note: factory_cost, selling_price, final_price, customer_notes, supplier_name
    // are handled via encrypt/decrypt mutators below - do NOT cast them here

    protected static function booted()
    {
        static::addGlobalScope('privacy', function ($builder) {
            $user = Auth::user();
            
            if (!$user) {
                return $builder;
            }

            // Sales users can't see factory data
            if ($user->isSales()) {
                $builder->select([
                    'id', 'order_number', 'customer_id', 'sales_user_id', 
                    'customer_notes', 'product_name', 'quantity', 'specifications',
                    'selling_price', 'final_price', 'status', 'customer_approval',
                    'payment_confirmed', 'manager_approval', 'quotation_path',
                    'invoice_path', 'qr_code_path', 'created_at', 'updated_at'
                ]);
            }

            // Factory users can't see customer data
            if ($user->isFactory()) {
                $builder->select([
                    'id', 'order_number', 'sales_user_id', 'factory_user_id',
                    'product_name', 'quantity', 'specifications', 'supplier_name',
                    'product_code', 'factory_cost', 'production_days', 'status',
                    'created_at', 'updated_at'
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

    // Encryption methods for sensitive data
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

    public function setCustomerNotesAttribute($value)
    {
        $this->attributes['customer_notes'] = $value ? Crypt::encrypt($value) : null;
    }

    public function getCustomerNotesAttribute($value)
    {
        return $value ? Crypt::decrypt($value) : null;
    }

    // Status methods
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function isFactoryPricing()
    {
        return $this->status === 'factory_pricing';
    }

    public function isManagerReview()
    {
        return $this->status === 'manager_review';
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

    public function canBeEditedBy($user)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSales() && $this->sales_user_id === $user->id) {
            return $this->status === 'draft';
        }

        if ($user->isFactory()) {
            return $this->status === 'factory_pricing';
        }

        return false;
    }
}
