<?php

/*
|--------------------------------------------------------------------------
| ARCA (ex AFIP): web services de autenticación y facturación electrónica
|--------------------------------------------------------------------------
|
| Lo que es de cada emisor (CUIT, certificado, ambiente) vive en la base.
| Acá solo va lo que es igual para todos.
|
*/

return [

    /*
     * Endpoints por ambiente. Los WSDL viajan con la app (`wsdl`): pedirlos
     * a ARCA en cada request desde un hosting compartido suma un viaje lento
     * que además puede estar caído. El endpoint real se fija con `location`.
     */
    'ambientes' => [
        'homologacion' => [
            'wsaa' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
            'wsfe' => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx',
        ],
        'produccion' => [
            'wsaa' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
            'wsfe' => 'https://servicios1.afip.gov.ar/wsfev1/service.asmx',
        ],
    ],

    'wsdl' => [
        'wsaa' => resource_path('arca/wsdl/wsaa.wsdl'),
        'wsfe' => resource_path('arca/wsdl/wsfev1.wsdl'),
    ],

    // Ver el comentario en el propio archivo.
    'openssl_cnf' => resource_path('arca/openssl.cnf'),

    /*
     * Cifrados TLS para hablar con ARCA, y SOLO con ARCA (va en el contexto de
     * cada SoapClient, no en la config global de PHP).
     *
     * servicios1.afip.gov.ar (WSFE de producción) negocia una clave
     * Diffie-Hellman que OpenSSL 3 rechaza con su nivel de seguridad por
     * defecto: el handshake falla y la app dice "no se pudo conectar". Medido
     * en Hostinger (OpenSSL 3.5) el 16/11/2026: sin esto, ninguna factura de
     * producción sale; con SECLEVEL=1, FEDummy responde OK. En Laragon no
     * fallaba, así que no se ve en local.
     */
    'cifrados_tls' => env('ARCA_CIFRADOS_TLS', 'DEFAULT@SECLEVEL=1'),

    /*
     * Segundos. La conexión corta rápido (si no hay salida a ARCA, no tiene
     * sentido esperar); la lectura no, porque FECAESolicitar tarda.
     */
    'timeout_conexion' => (int) env('ARCA_TIMEOUT_CONEXION', 10),
    'timeout_lectura' => (int) env('ARCA_TIMEOUT_LECTURA', 25),

    /*
     * Minutos antes del vencimiento del ticket de acceso en que ya se lo
     * renueva, para que no venza en medio de una emisión.
     */
    'margen_ticket_minutos' => 10,

    /*
     * Segundos que una emisión espera a que termine otra del mismo punto de
     * venta antes de avisar que está ocupado.
     */
    'espera_lock_emision_segundos' => 10,

    /*
     * Tope, en centavos, a partir del cual hay que identificar al
     * consumidor final (RG 1415, texto según RG 5866/2026: $10.000.000).
     * Cada emisor lo puede pisar si cambia la norma antes que el código.
     */
    'tope_consumidor_final' => 1_000_000_000,

    // Días que se guardan los request/response de cada llamada a ARCA.
    'retencion_llamadas_dias' => 90,

    // Días antes del vencimiento del certificado en que se empieza a avisar.
    'aviso_vencimiento_certificado_dias' => 30,

    /*
     * Directorio para los archivos temporales de la firma del TRA:
     * openssl_pkcs7_sign() solo acepta rutas. Se borran en el mismo request.
     */
    'directorio_temporal' => storage_path('app/private/arca/tmp'),

];
