{{--
    Factura C para dompdf, copiada de la que arma ARCA en "Comprobantes en
    línea": mismas medidas (en pt, sobre A4 de 595 × 842), mismos rótulos y la
    misma letra (Helvetica, la Arial de ARCA). Maquetada
    con tablas: dompdf no soporta flexbox ni grid.

    Los totales y el pie van fijos al fondo de la hoja, como en ARCA; el margen
    inferior de la página les deja el lugar. Las posiciones fijas se miden
    desde el margen (top 0 = 23pt de la hoja, left 0 = 15pt).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura C {{ $numero }}</title>
    <style>
        @page { margin: 23pt 14pt 330pt 15pt; }
        /* Helvetica no se embebe y solo tiene Windows-1252 (acentos, eñes, °, º, €):
           lo que no esté ahí (→, ł) dompdf lo toma de DejaVu, con subsetting. */
        * { font-family: Helvetica, 'DejaVu Sans'; }
        body { font-size: 9pt; color: #000; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        b { font-weight: bold; }

        .cabecera { border: 0.25pt solid #000; }
        .original { height: 27pt; border-bottom: 0.25pt solid #000; text-align: center; vertical-align: middle; font-size: 14pt; font-weight: bold; }
        .cabecera .izq { width: 248pt; height: 118pt; padding: 0 6pt; }
        .cabecera .centro { width: 47pt; }
        .cabecera .der { padding: 0 6pt 0 19pt; }
        .letra { height: 40pt; border: 0.5pt solid #000; border-top: 0; text-align: center; }
        .letra .c { font-size: 24pt; font-weight: bold; line-height: 26pt; padding-top: 2pt; }
        .letra .cod { font-size: 8pt; font-weight: bold; }
        .linea { width: 23.5pt; height: 77pt; border-right: 0.25pt solid #000; }
        .razon { font-size: 10pt; font-weight: bold; text-align: center; margin-top: 18pt; height: 42pt; }
        .tipo { font-size: 18pt; font-weight: bold; margin-top: 12pt; height: 29pt; }
        .renglon { line-height: 12pt; }
        .grande { font-size: 10pt; }
        .izq .datos td.rotulo { white-space: nowrap; padding-right: 4pt; font-weight: bold; }

        .periodo { margin-top: 1pt; border: 0.25pt solid #000; font-size: 10pt; }
        .periodo td { height: 22pt; vertical-align: middle; }

        .receptor { margin-top: 2pt; border: 0.5pt solid #000; font-size: 8pt; }
        .receptor td { padding-top: 3pt; }
        .receptor .rotulo { font-weight: bold; }
        .receptor .derecha { text-align: right; padding-right: 2pt; }

        .items { margin-top: 4pt; font-size: 8pt; }
        .items th { background: #ccc; border: 0.25pt solid #000; height: 18pt; vertical-align: middle; font-weight: bold; text-align: center; }
        .items th.chica { font-size: 7pt; }
        .items td { padding-top: 5pt; line-height: 9.2pt; }
        .num { text-align: right; white-space: nowrap; }
        .unidad { font-size: 7pt; text-align: center; line-height: 8pt; }

        .totales { position: fixed; top: 494pt; left: 0; right: 0; height: 92pt; border: 1pt solid #000; }
        .totales table { margin-top: 35pt; }
        .totales td { height: 18pt; font-weight: bold; text-align: right; white-space: nowrap; }
        .totales .total { font-size: 10pt; }
        .observaciones { position: absolute; top: 6pt; left: 6pt; width: 340pt; font-size: 8pt; }

        .fijo { position: fixed; }
        .qr { top: 630pt; left: 5pt; width: 80pt; height: 80pt; }
        .logo { top: 630pt; left: 98pt; width: 55pt; height: 24pt; }
        .autorizado { top: 664pt; left: 98pt; font-size: 9pt; font-weight: bold; font-style: italic; }
        .leyenda { top: 685pt; left: 98pt; width: 260pt; font-size: 6pt; font-weight: bold; font-style: italic; }
        .cae { top: 623pt; left: 300pt; width: 266pt; font-size: 10pt; }
        .cae td { height: 15pt; }
        .cae .rotulo { width: 158pt; text-align: right; padding-right: 5pt; font-weight: bold; }

        .marca { position: fixed; top: 30%; left: 0; right: 0; text-align: center; font-size: 34pt; color: #c00; opacity: 0.18; transform: rotate(-30deg); font-weight: bold; }
    </style>
</head>
<body>
    @if ($sinValidez)
        <div class="marca">SIN VALIDEZ FISCAL<br>HOMOLOGACIÓN</div>
    @endif

    <table class="cabecera">
        <tr>
            <td colspan="3" class="original">ORIGINAL</td>
        </tr>
        <tr>
            <td class="izq">
                <div class="razon">{{ $emisor['razon_social'] }}</div>
                <table class="datos" style="margin-bottom: 13pt">
                    <tr><td class="rotulo" style="width: 1%">Razón Social:</td><td>{{ $emisor['razon_social'] }}</td></tr>
                </table>
                <table class="datos" style="margin-bottom: 4pt">
                    <tr><td class="rotulo" style="width: 1%">Domicilio Comercial:</td><td>{{ $emisor['domicilio'] }}</td></tr>
                </table>
                <table class="datos">
                    <tr><td class="rotulo" style="width: 1%">Condición frente al IVA:</td><td><b>Responsable Monotributo</b></td></tr>
                </table>
            </td>
            <td class="centro">
                <div class="letra">
                    <div class="c">C</div>
                    <div class="cod">COD. {{ str_pad((string) $comprobante->tipo->value, 3, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="linea"></div>
            </td>
            <td class="der">
                <div class="tipo">FACTURA</div>
                <div class="renglon"><b>Punto de Venta:</b> <b class="grande">{{ $punto }}</b> &nbsp;&nbsp;&nbsp;&nbsp; <b>Comp. Nro:</b> <b class="grande">{{ $numeroSolo }}</b></div>
                <div class="renglon" style="margin-top: 4pt"><b>Fecha de Emisión:</b> <b class="grande">{{ $comprobante->fecha_emision->format('d/m/Y') }}</b></div>
                <div class="renglon" style="margin-top: 11pt"><b>CUIT:</b> {{ $emisor['cuit'] }}</div>
                @if ($emisor['ingresos_brutos'])
                    <div class="renglon"><b>Ingresos Brutos:</b> {{ $emisor['ingresos_brutos'] }}</div>
                @endif
                <div class="renglon"><b>Fecha de Inicio de Actividades:</b> {{ $emisor['inicio_actividades'] }}</div>
            </td>
        </tr>
    </table>

    @if ($comprobante->concepto->exigeFechasDeServicio())
        <table class="periodo">
            <tr>
                <td style="width: 211pt; padding-left: 6pt"><b>Período Facturado Desde:</b> &nbsp;&nbsp;&nbsp;&nbsp;{{ $comprobante->fecha_servicio_desde?->format('d/m/Y') }}</td>
                <td style="width: 131pt"><b>Hasta:</b> {{ $comprobante->fecha_servicio_hasta?->format('d/m/Y') }}</td>
                <td><b>Fecha de Vto. para el pago:</b> {{ $comprobante->fecha_vto_pago?->format('d/m/Y') }}</td>
            </tr>
        </table>
    @endif

    <table class="receptor">
        <tr>
            <td colspan="2" style="padding-left: 6pt; font-size: 9pt">
                @if ($receptor['tipo_documento'])
                    <b>{{ $receptor['tipo_documento'] }}:</b> {{ $receptor['numero_documento'] }}
                @else
                    <b>A CONSUMIDOR FINAL</b>
                @endif
            </td>
            <td class="rotulo derecha" style="width: 133pt">Apellido y Nombre / Razón Social:</td>
            <td style="width: 228pt">{{ $receptor['nombre'] }}</td>
        </tr>
        <tr>
            <td class="rotulo" style="width: 104pt; padding-left: 6pt; padding-top: 8pt">Condición frente al IVA:</td>
            <td style="padding-top: 8pt">{{ $receptor['condicion_iva'] }}</td>
            <td class="rotulo derecha" style="padding-top: 8pt">@if ($receptor['domicilio'])Domicilio:@endif</td>
            <td style="padding-top: 8pt; padding-bottom: 8pt">{{ $receptor['domicilio'] }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 36pt; text-align: left; padding-left: 4pt">Código</th>
                <th style="width: 136pt; text-align: left; padding-left: 5pt">Producto / Servicio</th>
                <th style="width: 65pt">Cantidad</th>
                <th style="width: 42pt">U. Medida</th>
                <th class="chica" style="width: 81pt">Precio Unit.</th>
                <th class="chica" style="width: 32pt">% Bonif</th>
                <th class="chica" style="width: 72pt">Imp. Bonif.</th>
                <th class="chica">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td></td>
                    <td style="padding-left: 2pt; padding-right: 1pt">{!! nl2br(e($item['descripcion'])) !!}</td>
                    <td class="num" style="padding-right: 2pt">{{ $item['cantidad'] }}</td>
                    <td class="unidad">{{ $item['unidad'] ?? 'unidades' }}</td>
                    <td class="num" style="padding-right: 2pt">{{ $item['precio'] }}</td>
                    <td class="num" style="padding-right: 8pt">0,00</td>
                    <td class="num" style="padding-right: 2pt">0,00</td>
                    <td class="num" style="padding-right: 2pt">{{ $item['importe'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totales">
        @if ($comprobante->observaciones)
            <div class="observaciones"><b>Observaciones:</b> {{ $comprobante->observaciones }}</div>
        @endif
        <table>
            <tr><td>Subtotal: $</td><td style="width: 82pt; padding-right: 8pt">{{ $subtotal }}</td></tr>
            <tr><td>Importe Otros Tributos: $</td><td style="padding-right: 8pt">{{ $tributos }}</td></tr>
            <tr><td class="total">Importe Total: $</td><td class="total" style="padding-right: 8pt">{{ $total }}</td></tr>
        </table>
    </div>

    <img class="fijo qr" src="{{ $qr }}" alt="Código QR de ARCA">
    <img class="fijo logo" src="{{ $logoArca }}" alt="ARCA">
    <div class="fijo autorizado">Comprobante Autorizado</div>
    <div class="fijo leyenda">Esta factura puede verificarse escaneando el código QR en el sitio de ARCA.</div>
    {{-- "Pág. 1/1" lo escribe GeneradorDePdf: CSS no tiene el total de páginas. --}}
    <table class="fijo cae">
        <tr><td class="rotulo">CAE N°:</td><td>{{ $comprobante->cae }}</td></tr>
        <tr><td class="rotulo">Fecha de Vto. de CAE:</td><td>{{ $comprobante->cae_vencimiento?->format('d/m/Y') }}</td></tr>
    </table>
</body>
</html>
