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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('batches')
                ->restrictOnDelete();

            $table->foreignId('reason_id')
                ->constrained('adjustment_reasons')
                ->restrictOnDelete();

            $table->unsignedInteger('old_quantity');
            $table->unsignedInteger('new_quantity');
            $table->bigInteger('quantity_difference');

            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
