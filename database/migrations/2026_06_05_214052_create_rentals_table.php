<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('actual_return_date')->nullable();
            $table->integer('total_days');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_cost', 12, 2)->default(0);
            $table->decimal('late_fee_total', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('dp_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->enum('delivery_method', ['pickup', 'delivery'])->default('pickup');
            $table->text('delivery_address')->nullable();
            $table->enum('status', [
                'pending_payment',
                'pending_confirmation',
                'confirmed',
                'ready_pickup',
                'delivering',
                'rented',
                'returned',
                'completed',
                'cancelled'
            ])->default('pending_payment');
            $table->enum('return_condition', ['perfect', 'minor_damage', 'major_damage', 'lost'])->nullable();
            $table->text('return_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
