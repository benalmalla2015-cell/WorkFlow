<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY status ENUM('draft', 'sent_to_factory', 'factory_pricing', 'manager_review', 'pending_approval', 'approved', 'customer_approved', 'payment_confirmed', 'completed') DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY status ENUM('draft', 'factory_pricing', 'manager_review', 'pending_approval', 'approved', 'customer_approved', 'payment_confirmed', 'completed') DEFAULT 'draft'");
    }
};
