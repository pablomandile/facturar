<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * La clave privada y el certificado con que cada emisor se autentica ante
 * ARCA, uno por ambiente (más los reemplazados, como historial).
 *
 * `clave_privada` va cifrada con el cast `encrypted`: por eso es `text`. El
 * ciphertext de una clave RSA de 2048 bits ronda los 2,5 KB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores')->cascadeOnDelete();
            $table->string('ambiente', 20);
            // El CN del pedido: el "nombre simbólico" que ARCA muestra en sus pantallas.
            $table->string('alias', 50);
            $table->text('clave_privada');
            $table->text('csr');
            $table->text('certificado')->nullable();
            $table->timestamp('valido_desde')->nullable();
            $table->timestamp('valido_hasta')->nullable();
            $table->string('estado', 30);
            $table->timestamps();

            $table->index(['emisor_id', 'ambiente', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados');
    }
};
