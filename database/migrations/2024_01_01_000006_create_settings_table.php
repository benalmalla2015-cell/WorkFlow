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
                'value' => 'DAYANCO TRADING CO. LIMITED',
                'description' => 'Company name for documents',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'company_address',
                'value' => 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU, 511455, P.R. CHINA',
                'description' => 'Company address for documents',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'company_phone',
                'value' => '+86 188188 45411',
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
