<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Los puntos de venta "Web Services" que el emisor dio de alta en ARCA. No
 * llevan contador propio: el próximo número de comprobante lo dice ARCA
 * (FECompUltimoAutorizado) en el momento de emitir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puntos_de_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores')->cascadeOnDelete();
            $table->string('ambiente', 20);
            $table->unsignedInteger('numero');
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['emisor_id', 'ambiente', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puntos_de_venta');
    }
};
