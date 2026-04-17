<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'total_price')) {
                $table->text('total_price')->nullable()->after('final_price');
            }

            if (!Schema::hasColumn('orders', 'net_profit')) {
                $table->text('net_profit')->nullable()->after('total_price');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('orders', 'total_price')) {
                $columns[] = 'total_price';
            }

            if (Schema::hasColumn('orders', 'net_profit')) {
                $columns[] = 'net_profit';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
