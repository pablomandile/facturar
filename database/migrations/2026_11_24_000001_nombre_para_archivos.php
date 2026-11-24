<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cómo empieza el nombre del PDF de cada factura: APELLIDO_NOMBRE.
        // Null: se arma invirtiendo la razón social (Emisor::nombreParaArchivos).
        Schema::table('emisores', function (Blueprint $table): void {
            $table->string('nombre_archivos', 60)->nullable()->after('razon_social');
        });
    }

    public function down(): void
    {
        Schema::table('emisores', function (Blueprint $table): void {
            $table->dropColumn('nombre_archivos');
        });
    }
};
