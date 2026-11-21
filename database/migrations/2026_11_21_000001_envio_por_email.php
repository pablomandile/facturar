<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El texto del email de las facturas, con marcadores ({numero},
        // {importe}…). Null: el texto por defecto de TextoDelEmail.
        Schema::table('emisores', function (Blueprint $table): void {
            $table->text('plantilla_email')->nullable()->after('tope_consumidor_final');
        });

        // La casilla "Enviarla al cliente cuando ARCA la autorice": la
        // autorización puede llegar después (un Pendiente que se resuelve
        // por el cron), así que la intención queda guardada.
        Schema::table('comprobantes', function (Blueprint $table): void {
            $table->boolean('enviar_al_autorizar')->default(false)->after('observaciones');
        });

        Schema::create('envios_de_comprobantes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->json('destinatarios');
            $table->boolean('con_copia')->default(false);
            $table->text('texto');
            $table->boolean('enviado');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_de_comprobantes');

        Schema::table('comprobantes', function (Blueprint $table): void {
            $table->dropColumn('enviar_al_autorizar');
        });

        Schema::table('emisores', function (Blueprint $table): void {
            $table->dropColumn('plantilla_email');
        });
    }
};
