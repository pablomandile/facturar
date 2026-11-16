<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Los comprobantes y sus ítems.
 *
 * - Importes en **centavos**, enteros: ARCA valida ImpTotal = ImpNeto +
 *   ImpTrib y un flotante lo rompe.
 * - El receptor se **copia** al comprobante: editar o borrar el cliente no
 *   cambia una factura emitida.
 * - `numero` se asigna recién al emitir. El UNIQUE por punto de venta, tipo
 *   y número es la última defensa contra numerar dos veces (MySQL admite
 *   varios NULL en un UNIQUE: los borradores no chocan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores')->cascadeOnDelete();
            $table->foreignId('punto_de_venta_id')->constrained('puntos_de_venta')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('ambiente', 20);
            // Códigos de ARCA.
            $table->unsignedSmallInteger('tipo');
            $table->unsignedSmallInteger('concepto');
            $table->unsignedInteger('numero')->nullable();
            $table->date('fecha_emision');
            $table->date('fecha_servicio_desde')->nullable();
            $table->date('fecha_servicio_hasta')->nullable();
            $table->date('fecha_vto_pago')->nullable();

            $table->unsignedSmallInteger('receptor_tipo_documento');
            $table->string('receptor_numero_documento', 11);
            $table->string('receptor_nombre', 120);
            $table->unsignedSmallInteger('receptor_condicion_iva');
            $table->string('receptor_domicilio', 200)->nullable();

            $table->unsignedBigInteger('importe_neto');
            $table->unsignedBigInteger('importe_tributos')->default(0);
            $table->unsignedBigInteger('importe_total');
            $table->char('moneda', 3)->default('PES');
            $table->decimal('cotizacion', 12, 6)->default(1);

            $table->string('estado', 20)->default('borrador');
            $table->string('cae', 14)->nullable();
            $table->date('cae_vencimiento')->nullable();
            // Observaciones, errores y eventos de ARCA de la última emisión.
            $table->json('respuesta_arca')->nullable();
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->timestamp('enviado_en')->nullable();
            $table->timestamp('autorizado_en')->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['punto_de_venta_id', 'tipo', 'numero']);
            $table->index(['emisor_id', 'estado']);
            $table->index(['emisor_id', 'fecha_emision']);
        });

        Schema::create('comprobante_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('descripcion', 250);
            $table->decimal('cantidad', 12, 4);
            $table->string('unidad', 20)->nullable();
            $table->unsignedBigInteger('precio_unitario');
            $table->unsignedBigInteger('importe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_items');
        Schema::dropIfExists('comprobantes');
    }
};
