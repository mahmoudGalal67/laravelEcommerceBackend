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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('gateway'); // stripe, paypal
            $table->string('gateway_payment_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['initiated', 'succeeded', 'failed', 'refunded'])->default('initiated');
            $table->json('meta')->nullable(); // store gateway response
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
