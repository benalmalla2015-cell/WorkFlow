<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
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
            
            // Only factory and admin can see supplier data
            if ($user && !$user->canViewFactoryData()) {
                $builder->whereRaw('1 = 0'); // Return empty result
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
