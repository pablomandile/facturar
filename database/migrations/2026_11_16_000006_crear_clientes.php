<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Los clientes del emisor. Consumidor final no se guarda: es "sin
 * identificar". Los comprobantes copian los datos del receptor al emitirse,
 * así que editar o borrar un cliente no cambia ninguna factura ya hecha.
 *
 * El UNIQUE incluye a los borrados: volver a cargar a alguien que se borró
 * lo restaura en vez de chocar (ver ClienteController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores')->cascadeOnDelete();
            // Códigos de ARCA (DocTipo y CondicionIVAReceptorId).
            $table->unsignedSmallInteger('tipo_documento');
            $table->string('numero_documento', 11);
            $table->string('nombre', 120);
            $table->unsignedSmallInteger('condicion_iva');
            $table->string('domicilio', 200)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['emisor_id', 'tipo_documento', 'numero_documento']);
            $table->index(['emisor_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
