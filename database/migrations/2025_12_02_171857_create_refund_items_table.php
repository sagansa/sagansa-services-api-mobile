<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refund_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('refund_id');
            $table->uuid('order_item_id');
            $table->integer('quantity_refunded');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_refund_amount', 15, 2);
            $table->text('reason')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('refund_id')->references('id')->on('refunds')->onDelete('cascade');
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('restrict');

            // Indexes
            $table->index('refund_id');
            $table->index('order_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_items');
    }
};
