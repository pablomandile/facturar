<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * El ticket de acceso (TA) que entrega WSAA: token y sign, válidos 12 horas.
 *
 * Vive acá y no en el cache porque ARCA **niega un ticket nuevo mientras el
 * anterior está vigente**: un `cache:clear` en un deploy dejaría al emisor
 * sin poder facturar hasta que venza el que se perdió. Por eso esta fila no
 * se borra nunca; se pisa con el ticket nuevo.
 *
 * El ticket es del certificado que lo pidió (`certificado_id`): al renovar
 * el certificado se puede pedir otro aunque el anterior siga vigente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets_de_acceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores')->cascadeOnDelete();
            $table->foreignId('certificado_id')->nullable()->constrained('certificados')->nullOnDelete();
            $table->string('ambiente', 20);
            $table->string('servicio', 20);
            $table->text('token')->nullable();
            $table->text('sign')->nullable();
            $table->string('unique_id', 20)->nullable();
            $table->timestamp('generado_en')->nullable();
            $table->timestamp('expira_en')->nullable();
            // Cuando ARCA dijo que ya había uno vigente que no tenemos: no se
            // le vuelve a pedir hasta esta hora.
            $table->timestamp('bloqueado_hasta')->nullable();
            $table->timestamps();

            $table->unique(['emisor_id', 'ambiente', 'servicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets_de_acceso');
    }
};
