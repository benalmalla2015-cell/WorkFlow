<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('sales_user_id')->constrained('users');
            $table->foreignId('factory_user_id')->nullable()->constrained('users');
            
            // Customer data (encrypted)
            $table->text('customer_notes')->nullable();
            
            // Product information
            $table->string('product_name');
            $table->integer('quantity');
            $table->text('specifications')->nullable();
            
            // Factory data (encrypted, hidden from sales)
            $table->string('supplier_name')->nullable();
            $table->string('product_code')->nullable();
            $table->decimal('factory_cost', 10, 2)->nullable();
            $table->integer('production_days')->nullable();
            
            // Pricing (encrypted)
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->decimal('profit_margin_percentage', 5, 2)->default(0);
            $table->decimal('final_price', 10, 2)->nullable();
            
            // Status tracking
            $table->enum('status', ['draft', 'factory_pricing', 'manager_review', 'approved', 'customer_approved', 'payment_confirmed', 'completed'])->default('draft');
            $table->boolean('customer_approval')->default(false);
            $table->boolean('payment_confirmed')->default(false);
            $table->boolean('manager_approval')->default(false);
            
            // Document paths
            $table->string('quotation_path')->nullable();
            $table->string('invoice_path')->nullable();
            $table->string('qr_code_path')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
