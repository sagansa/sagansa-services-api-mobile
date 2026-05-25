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
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('order_id');
            $table->string('refund_number', 50)->unique();
            $table->enum('refund_type', ['full', 'partial']);
            $table->decimal('total_amount', 15, 2);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('refunded_by');
            $table->timestamp('refunded_at');
            $table->string('payment_method', 50)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('completed');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('refunded_by')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('tenant_id');
            $table->index('order_id');
            $table->index('refund_number');
            $table->index('refunded_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
