<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cada llamada a ARCA con su pedido y su respuesta: para entender un rechazo
 * días después, y para responder "¿ARCA lo recibió?" ante un corte. El token
 * y el sign se guardan tachados. Se purga a los 90 días.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llamadas_arca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores')->cascadeOnDelete();
            // Sin FK todavía: la tabla de comprobantes llega en la etapa de emisión.
            $table->unsignedBigInteger('comprobante_id')->nullable()->index();
            $table->string('ambiente', 20);
            $table->string('servicio', 20);
            $table->string('metodo', 60);
            $table->mediumText('pedido')->nullable();
            $table->mediumText('respuesta')->nullable();
            $table->string('resultado', 20);
            $table->string('codigo', 60)->nullable();
            $table->text('mensaje')->nullable();
            $table->unsignedInteger('duracion_ms');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llamadas_arca');
    }
};
