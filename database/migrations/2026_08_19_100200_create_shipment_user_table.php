<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Relación N:N entre envíos y usuarios finales: un usuario sigue varios
     * pedidos y un pedido puede seguirlo más de un usuario.
     *
     * No sustituye a `sender_id`, que es otra cosa: el agente que registró el
     * envío. Aquí van los usuarios a los que el pedido les aparece en su lista.
     *
     * El nombre `shipment_user` es la convención de Laravel para la pivote
     * (modelos en singular, orden alfabético), así las relaciones la infieren
     * sin tener que declararla.
     */
    public function up(): void
    {
        Schema::create('shipment_user', function (Blueprint $table) {
            $table->id();
            // Ambas en cascada: el vínculo no significa nada si desaparece
            // cualquiera de los dos extremos.
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Un usuario se vincula una sola vez a un mismo envío: sin esto,
            // un attach() repetido duplicaría el pedido en su lista.
            $table->unique(['shipment_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_user');
    }
};
