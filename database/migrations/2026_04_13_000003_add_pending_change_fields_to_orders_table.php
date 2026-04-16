<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('pending_changes')->nullable()->after('status');
            $table->foreignId('pending_change_requested_by')->nullable()->after('pending_changes')->constrained('users')->nullOnDelete();
            $table->timestamp('pending_change_requested_at')->nullable()->after('pending_change_requested_by');
            $table->string('pending_change_original_status')->nullable()->after('pending_change_requested_at');
        });

        DB::statement("ALTER TABLE orders MODIFY status ENUM('draft', 'sent_to_factory', 'factory_pricing', 'manager_review', 'pending_approval', 'approved', 'customer_approved', 'payment_confirmed', 'completed') DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY status ENUM('draft', 'sent_to_factory', 'factory_pricing', 'manager_review', 'approved', 'customer_approved', 'payment_confirmed', 'completed') DEFAULT 'draft'");

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['pending_change_requested_by']);
            $table->dropColumn([
                'pending_changes',
                'pending_change_requested_by',
                'pending_change_requested_at',
                'pending_change_original_status',
            ]);
        });
    }
};
