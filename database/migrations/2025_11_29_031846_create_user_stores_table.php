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
        Schema::create('user_stores', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('store_id');
            $table->timestamps();

            // user_id points to sagansa_user.users.uuid, which lives outside sagansa_ops.
            $table->index('user_id');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');

            // Composite primary key
            $table->primary(['user_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_stores');
    }
};
