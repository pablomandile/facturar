<?php

declare(strict_types=1);

namespace Tests\Support\Arca;

use App\Services\Arca\RelojFiscal;

/**
 * Respuestas de ARCA con la forma exacta que entrega SoapClient: objetos
 * anidados con los nombres de nodo de ARCA. Las formas salen de llamadas
 * reales a homologación (ver tests/Fixtures/arca/).
 */
final class RespuestasArca
{
    /** La respuesta de loginCms: el ticket viene como XML escapado adentro de un string. */
    public static function ticket(string $token = 'TOKEN-DE-PRUEBA', string $sign = 'SIGN-DE-PRUEBA', int $horas = 12): object
    {
        $ahora = (new RelojFiscal)->ahora();
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<loginTicketResponse version="1.0"><header>'
            .'<source>CN=wsaahomo, O=AFIP, C=AR, SERIALNUMBER=CUIT 33693450239</source>'
            .'<destination>SERIALNUMBER=CUIT 20111111112, CN=facturar</destination>'
            .'<uniqueId>123456789</uniqueId>'
            .'<generationTime>'.$ahora->format('Y-m-d\TH:i:s.vP').'</generationTime>'
            .'<expirationTime>'.$ahora->addHours($horas)->format('Y-m-d\TH:i:s.vP').'</expirationTime>'
            .'</header><credentials>'
            ."<token>{$token}</token><sign>{$sign}</sign>"
            .'</credentials></loginTicketResponse>';

        return (object) ['loginCmsReturn' => $xml];
    }

    public static function dummy(string $estado = 'OK'): object
    {
        return (object) ['FEDummyResult' => (object) ['AppServer' => $estado, 'DbServer' => $estado, 'AuthServer' => $estado]];
    }

    public static function ultimoAutorizado(int $numero, int $puntoDeVenta = 1, int $tipo = 11): object
    {
        return (object) ['FECompUltimoAutorizadoResult' => (object) [
            'PtoVta' => $puntoDeVenta,
            'CbteTipo' => $tipo,
            'CbteNro' => $numero,
        ]];
    }

    /**
     * Una respuesta con Errors. Con `$comoArray = false` el Err viene suelto,
     * como lo arma SoapClient sin SOAP_SINGLE_ELEMENT_ARRAYS.
     *
     * @param  list<array{int, string}>  $errores
     */
    public static function conErrores(string $metodo, array $errores, bool $comoArray = true): object
    {
        $err = array_map(fn (array $e): object => (object) ['Code' => $e[0], 'Msg' => $e[1]], $errores);

        return (object) ["{$metodo}Result" => (object) [
            'Errors' => (object) ['Err' => $comoArray || count($err) > 1 ? $err : $err[0]],
        ]];
    }

    public static function sinResultados(string $metodo): object
    {
        return self::conErrores($metodo, [[602, 'Sin Resultados: - Metodo '.$metodo]]);
    }

    /** @param  array<string, mixed>  $cambios */
    public static function comprobante(array $cambios = []): object
    {
        return (object) ['FECompConsultarResult' => (object) ['ResultGet' => (object) [
            'Concepto' => 1,
            'DocTipo' => 99,
            'DocNro' => 0,
            'CbteDesde' => 8,
            'CbteHasta' => 8,
            'CbteFch' => '20261116',
            'ImpTotal' => 1500.0,
            'ImpNeto' => 1500.0,
            'MonId' => 'PES',
            'MonCotiz' => 1.0,
            'Resultado' => 'A',
            'CodAutorizacion' => '76461234567890',
            'EmisionTipo' => 'CAE',
            'FchVto' => '20261126',
            'FchProceso' => '20261116101500',
            'PtoVta' => 1,
            'CbteTipo' => 11,
            ...$cambios,
        ]]];
    }

    /** @param  list<array{int, string, string}>  $puntos  número, tipo, bloqueado (S/N) */
    public static function puntosDeVenta(array $puntos): object
    {
        return (object) ['FEParamGetPtosVentaResult' => (object) ['ResultGet' => (object) [
            'PtoVenta' => array_map(fn (array $p): object => (object) [
                'Nro' => $p[0], 'EmisionTipo' => $p[1], 'Bloqueado' => $p[2], 'FchBaja' => 'NULL',
            ], $puntos),
        ]]];
    }
}
