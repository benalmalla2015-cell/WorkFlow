<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function ordersAsSales()
    {
        return $this->hasMany(Order::class, 'sales_user_id');
    }

    public function ordersAsFactory()
    {
        return $this->hasMany(Order::class, 'factory_user_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'created_by');
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'uploaded_by');
    }

    public function notificationTokens()
    {
        return $this->hasMany(NotificationToken::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isSales()
    {
        return $this->role === 'sales';
    }

    public function isFactory()
    {
        return $this->role === 'factory';
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function canViewCustomerData()
    {
        return $this->isSales() || $this->isAdmin();
    }

    public function canViewFactoryData()
    {
        return $this->isFactory() || $this->isAdmin();
    }

    public function canManageUsers()
    {
        return $this->isAdmin();
    }

    public function canApproveOrders()
    {
        return $this->isAdmin();
    }
}
