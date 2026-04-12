<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'uploaded_by',
        'file_name',
        'original_name',
        'mime_type',
        'file_size',
        'path',
        'type',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    protected static function booted()
    {
        static::addGlobalScope('privacy', function ($builder) {
            $user = Auth::user();
            
            if (!$user) {
                return $builder;
            }

            // Sales users can only see sales uploads
            if ($user->isSales()) {
                $builder->where('type', 'sales_upload');
            }

            // Factory users can only see factory uploads
            if ($user->isFactory()) {
                $builder->where('type', 'factory_upload');
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFullUrlAttribute()
    {
        return Storage::url($this->path);
    }

    public function isImage()
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf()
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isExcel()
    {
        return in_array($this->mime_type, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }

    public function isWord()
    {
        return in_array($this->mime_type, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ]);
    }
}
