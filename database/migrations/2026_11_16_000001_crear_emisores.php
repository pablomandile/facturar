<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * El perfil fiscal de cada cuenta: quién factura. Uno por usuario.
 *
 * Las columnas con casos fijos (ambiente) son `string` y no ENUM de MySQL:
 * sqlite no valida ENUM, así que un caso nuevo en el enum de PHP pasaría los
 * tests y reventaría recién en producción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emisores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->char('cuit', 11)->unique();
            $table->string('razon_social');
            $table->string('domicilio_fiscal');
            // Número de Ingresos Brutos o "Exento": va impreso en la factura.
            $table->string('ingresos_brutos')->nullable();
            $table->date('inicio_actividades');
            $table->string('ambiente', 20)->default('homologacion');
            // En centavos. Null: el de config/arca.php.
            $table->unsignedBigInteger('tope_consumidor_final')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emisores');
    }
};
