<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un nombre corto para el trato ("Ministerio" en vez de la razón
        // social entera). Lo usa el marcador {alias} del email de las facturas.
        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('alias', 60)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn('alias');
        });
    }
};
