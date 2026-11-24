<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El asunto del email de las facturas, con los mismos marcadores que
        // el texto. Null: TextoDelEmail::ASUNTO_POR_DEFECTO.
        Schema::table('emisores', function (Blueprint $table): void {
            $table->string('asunto_email', 200)->nullable()->after('plantilla_email');
        });

        // Con qué asunto salió cada envío (puede retocarse antes de mandar).
        Schema::table('envios_de_comprobantes', function (Blueprint $table): void {
            $table->string('asunto', 200)->nullable()->after('con_copia');
        });
    }

    public function down(): void
    {
        Schema::table('envios_de_comprobantes', function (Blueprint $table): void {
            $table->dropColumn('asunto');
        });

        Schema::table('emisores', function (Blueprint $table): void {
            $table->dropColumn('asunto_email');
        });
    }
};
