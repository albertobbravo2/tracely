<?php

use App\Enums\ShipmentStatus;
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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete()->nullable(); // borrar ->nullable() en producción
            $table->string('receiver_name');
            $table->string('origin');
            $table->string('destination');
            $table->date('estimated_delivery_date');
            $table->string('status')->default(ShipmentStatus::Pendiente->value);

            $table->timestamps();

            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
