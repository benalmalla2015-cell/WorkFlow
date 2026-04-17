<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->text('supplier_name')->nullable()->after('description');
            $table->string('product_code', 500)->nullable()->after('supplier_name');
            $table->text('unit_cost')->nullable()->after('product_code');
        });

        $orders = DB::table('orders')
            ->select(['id', 'supplier_name', 'product_code', 'factory_cost'])
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id');
            })
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            DB::table('order_items')
                ->where('order_id', $order->id)
                ->update([
                    'supplier_name' => $order->supplier_name,
                    'product_code' => $order->product_code,
                    'unit_cost' => $order->factory_cost,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'product_code', 'unit_cost']);
        });
    }
};
