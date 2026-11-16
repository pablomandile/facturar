<?php

declare(strict_types=1);

namespace App\Enums\Arca;

/**
 * Los web services de ARCA con que habla la app.
 *
 * El `value` es lo que va en el `<service>` del pedido de ticket: para
 * facturación es **`wsfe`**, no `wsfev1` (ese es el nombre del WSDL). Con
 * `wsfev1` WSAA contesta `coe.notAuthorized`, que hace pensar que falta
 * autorizar el certificado cuando el error es otro.
 */
enum ServicioArca: string
{
    /** Autenticación: entrega el ticket de acceso. */
    case Wsaa = 'wsaa';

    /** Factura electrónica (WSFEv1). */
    case Wsfe = 'wsfe';
}
