<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $timestamp = now();

        DB::table('orders')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($timestamp) {
                $rows = [];

                foreach ($orders as $order) {
                    if (!$order->product_name && !$order->quantity && !$order->specifications) {
                        continue;
                    }

                    $rows[] = [
                        'order_id' => $order->id,
                        'item_name' => $order->product_name ?: 'Item ' . $order->id,
                        'quantity' => max(1, (int) ($order->quantity ?: 1)),
                        'description' => $order->specifications,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($rows !== []) {
                    DB::table('order_items')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
