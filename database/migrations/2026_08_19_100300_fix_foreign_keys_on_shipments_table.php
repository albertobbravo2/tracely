<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Las dos claves foráneas de `shipments` borraban el envío en cascada, y un
     * envío es un registro de negocio: debe sobrevivir a la baja tanto de la
     * cuenta que lo registró como de la empresa a la que pertenecía.
     *
     * `sender_id` arrastraba además un error de declaración en
     * `create_shipments_table`:
     *
     *     $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete()->nullable();
     *
     * El `->nullable()` va después de `constrained()`, así que se aplicó al
     * índice y no a la columna: quedó NOT NULL. Para poder pasar a NULL ON
     * DELETE hay que corregir antes la nulabilidad.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->dropForeign(['company_id']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('sender_id')->nullable()->change();

            $table->foreign('sender_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Ojo: si al revertir hay envíos con `sender_id` a NULL (porque se borró la
     * cuenta que los registró), volver a NOT NULL falla. Habría que asignarles
     * un usuario antes de revertir.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->dropForeign(['company_id']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('sender_id')->nullable(false)->change();

            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }
};
