<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'full_name',
        'address',
        'phone',
        'email',
        'notes',
        'created_by',
    ];

    protected static function booted()
    {
        static::addGlobalScope('privacy', function ($builder) {
            $user = Auth::user();
            
            // Only sales and admin can see customer data
            if ($user && !$user->canViewCustomerData()) {
                $builder->whereRaw('1 = 0'); // Return empty result
            }
        });
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
