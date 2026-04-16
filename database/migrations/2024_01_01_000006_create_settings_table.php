<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->string('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        
        // Insert default settings
        DB::table('settings')->insert([
            [
                'key' => 'default_profit_margin',
                'value' => '20',
                'description' => 'Default profit margin percentage',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'company_name',
                'value' => 'مؤسسة مدحت رشاد للحلول التقنية',
                'description' => 'Company name for documents',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'company_address',
                'value' => 'العنوان يحدد من إعدادات النظام',
                'description' => 'Company address for documents',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'company_phone',
                'value' => '+967000000000',
                'description' => 'Company phone for documents',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
