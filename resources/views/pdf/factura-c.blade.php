{{--
    Factura C para dompdf. Maquetada con tablas: dompdf no soporta flexbox
    ni grid. Fuente DejaVu Sans: la única de las que trae dompdf con acentos
    y eñes.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura C {{ $numero }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 9.5pt; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .caja { border: 1px solid #111; }
        .original { text-align: center; font-weight: bold; font-size: 11pt; padding: 4px; border: 1px solid #111; border-bottom: 0; }
        .emisor td { padding: 8px 10px; }
        .razon { font-size: 15pt; font-weight: bold; margin-bottom: 6px; }
        .letra-celda { width: 64px; text-align: center; padding: 0; border-left: 1px solid #111; border-right: 1px solid #111; }
        .letra { font-size: 30pt; font-weight: bold; border-bottom: 1px solid #111; line-height: 1.1; padding: 4px 0; }
        .codigo { font-size: 7.5pt; font-weight: bold; padding: 3px 0; }
        .tipo { font-size: 16pt; font-weight: bold; margin-bottom: 6px; }
        .etiqueta { font-weight: bold; }
        .fila td { padding: 5px 10px; }
        .items th { background: #e5e5e5; border: 1px solid #111; padding: 5px; font-size: 8.5pt; text-align: left; }
        .items td { border-left: 1px solid #111; border-right: 1px solid #111; padding: 4px 5px; }
        .items tr:last-child td { border-bottom: 1px solid #111; }
        .num { text-align: right; white-space: nowrap; }
        .totales td { padding: 3px 10px; }
        .total { font-size: 12pt; font-weight: bold; }
        .pie td { padding: 8px 10px; vertical-align: middle; }
        .cae { font-size: 10.5pt; }
        .marca { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 34pt; color: #c00; opacity: 0.18; transform: rotate(-30deg); font-weight: bold; }
        .mt { margin-top: 6px; }
    </style>
</head>
<body>
    @if ($sinValidez)
        <div class="marca">SIN VALIDEZ FISCAL<br>HOMOLOGACIÓN</div>
    @endif

    <div class="original">ORIGINAL</div>

    <table class="caja emisor">
        <tr>
            <td style="width: 46%">
                <div class="razon">{{ $emisor['razon_social'] }}</div>
                <div><span class="etiqueta">Domicilio comercial:</span> {{ $emisor['domicilio'] }}</div>
                <div class="mt"><span class="etiqueta">Condición frente al IVA:</span> Responsable Monotributo</div>
            </td>
            <td class="letra-celda">
                <div class="letra">C</div>
                <div class="codigo">COD. {{ str_pad((string) $comprobante->tipo->value, 3, '0', STR_PAD_LEFT) }}</div>
            </td>
            <td>
                <div class="tipo">FACTURA</div>
                <div><span class="etiqueta">Punto de venta:</span> {{ $punto }} &nbsp; <span class="etiqueta">Comp. Nro:</span> {{ $numeroSolo }}</div>
                <div><span class="etiqueta">Fecha de emisión:</span> {{ $comprobante->fecha_emision->format('d/m/Y') }}</div>
                <div class="mt"><span class="etiqueta">CUIT:</span> {{ $emisor['cuit'] }}</div>
                <div><span class="etiqueta">Ingresos brutos:</span> {{ $emisor['ingresos_brutos'] }}</div>
                <div><span class="etiqueta">Inicio de actividades:</span> {{ $emisor['inicio_actividades'] }}</div>
            </td>
        </tr>
    </table>

    @if ($comprobante->concepto->exigeFechasDeServicio())
        <table class="caja fila" style="border-top: 0">
            <tr>
                <td><span class="etiqueta">Período facturado desde:</span> {{ $comprobante->fecha_servicio_desde?->format('d/m/Y') }}</td>
                <td><span class="etiqueta">Hasta:</span> {{ $comprobante->fecha_servicio_hasta?->format('d/m/Y') }}</td>
                <td><span class="etiqueta">Vencimiento para el pago:</span> {{ $comprobante->fecha_vto_pago?->format('d/m/Y') }}</td>
            </tr>
        </table>
    @endif

    <table class="caja fila" style="border-top: 0">
        <tr>
            <td style="width: 46%">
                @if ($receptor['documento'])
                    <span class="etiqueta">{{ $receptor['documento'] }}</span>
                @else
                    <span class="etiqueta">A CONSUMIDOR FINAL</span>
                @endif
            </td>
            <td><span class="etiqueta">Apellido y nombre / Razón social:</span> {{ $receptor['nombre'] }}</td>
        </tr>
        <tr>
            <td><span class="etiqueta">Condición frente al IVA:</span> {{ $receptor['condicion_iva'] }}</td>
            <td>@if ($receptor['domicilio'])<span class="etiqueta">Domicilio:</span> {{ $receptor['domicilio'] }}@endif</td>
        </tr>
    </table>

    <table class="items" style="margin-top: 8px">
        <thead>
            <tr>
                <th>Producto / Servicio</th>
                <th class="num" style="width: 60px">Cantidad</th>
                <th style="width: 60px">U. medida</th>
                <th class="num" style="width: 90px">Precio unit.</th>
                <th class="num" style="width: 95px">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item['descripcion'] }}</td>
                    <td class="num">{{ $item['cantidad'] }}</td>
                    <td>{{ $item['unidad'] ?? 'unidades' }}</td>
                    <td class="num">{{ $item['precio'] }}</td>
                    <td class="num">{{ $item['importe'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="caja totales" style="margin-top: 8px">
        <tr>
            <td style="width: 60%">@if ($comprobante->observaciones)<span class="etiqueta">Observaciones:</span> {{ $comprobante->observaciones }}@endif</td>
            <td class="num etiqueta">Subtotal: $</td>
            <td class="num" style="width: 100px">{{ $subtotal }}</td>
        </tr>
        <tr>
            <td></td>
            <td class="num etiqueta">Importe otros tributos: $</td>
            <td class="num">{{ $tributos }}</td>
        </tr>
        <tr>
            <td></td>
            <td class="num total">Importe total: $</td>
            <td class="num total">{{ $total }}</td>
        </tr>
    </table>

    <table class="pie" style="margin-top: 10px">
        <tr>
            <td style="width: 130px"><img src="{{ $qr }}" alt="Código QR de ARCA" style="width: 115px; height: 115px"></td>
            <td>
                <div class="etiqueta">Comprobante autorizado</div>
                <div style="font-size: 7.5pt">Esta factura puede verificarse escaneando el código QR en el sitio de ARCA.</div>
            </td>
            <td class="num cae">
                <div><span class="etiqueta">CAE N°:</span> {{ $comprobante->cae }}</div>
                <div><span class="etiqueta">Fecha de vto. de CAE:</span> {{ $comprobante->cae_vencimiento?->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
