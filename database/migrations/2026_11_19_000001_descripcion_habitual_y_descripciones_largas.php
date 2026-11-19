<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * La descripción habitual del cliente: un texto con marcadores
         * ({desde}, {importe_en_letras}…) que el formulario de la factura
         * completa solo. Ver resources/js/lib/plantillaDescripcion.ts.
         */
        Schema::table('clientes', function (Blueprint $table): void {
            $table->text('plantilla_descripcion')->nullable()->after('email');
        });

        // Descripciones de varias líneas: 250 caracteres no alcanzaban para
        // un período, una cuenta bancaria y el importe en letras.
        Schema::table('comprobante_items', function (Blueprint $table): void {
            $table->text('descripcion')->change();
        });
    }

    public function down(): void
    {
        Schema::table('comprobante_items', function (Blueprint $table): void {
            $table->string('descripcion', 250)->change();
        });

        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn('plantilla_descripcion');
        });
    }
};
