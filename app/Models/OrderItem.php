<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'item_name',
        'quantity',
        'description',
        'supplier_name',
        'product_code',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function setSupplierNameAttribute($value)
    {
        $this->attributes['supplier_name'] = $value ? Crypt::encrypt($value) : null;
    }

    public function getSupplierNameAttribute($value)
    {
        return $value ? Crypt::decrypt($value) : null;
    }

    public function setUnitCostAttribute($value)
    {
        $this->attributes['unit_cost'] = $value !== null && $value !== '' ? Crypt::encrypt($value) : null;
    }

    public function getUnitCostAttribute($value)
    {
        return $value ? Crypt::decrypt($value) : null;
    }
}
