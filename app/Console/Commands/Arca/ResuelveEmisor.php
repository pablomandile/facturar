<?php

declare(strict_types=1);

namespace App\Console\Commands\Arca;

use App\Exceptions\Arca\ExcepcionDeArca;
use App\Exceptions\Arca\FallaDeConexion;
use App\Models\Emisor;
use App\Support\Arca\Cuit;
use Closure;
use Symfony\Component\Console\Exception\InvalidArgumentException;

/** Los comandos arca:* reciben el emisor por id o por CUIT (con o sin guiones). */
trait ResuelveEmisor
{
    protected function emisor(): Emisor
    {
        $valor = (string) $this->argument('emisor');
        $cuit = Cuit::normalizar($valor);

        $emisor = strlen($cuit) === 11
            ? Emisor::query()->where('cuit', $cuit)->first()
            : Emisor::query()->find((int) $valor);

        return $emisor ?? throw new InvalidArgumentException("No hay un emisor con id o CUIT {$valor}.");
    }

    protected function encabezado(Emisor $emisor): void
    {
        $this->line("<info>{$emisor->razon_social}</info> · CUIT {$emisor->cuitFormateado()} · {$emisor->ambiente->etiqueta()}");
    }

    /**
     * Corre el comando mostrando los errores de ARCA como mensaje y no como
     * traza: son errores esperables (certificado, ticket, rechazo), no bugs.
     *
     * @param  Closure(): int  $comando
     */
    protected function conArca(Closure $comando): int
    {
        try {
            return $comando();
        } catch (ExcepcionDeArca $e) {
            $this->error($e->getMessage());

            if ($e instanceof FallaDeConexion) {
                $this->line("Detalle técnico: {$e->mensajeTecnico}");
            }

            return self::FAILURE;
        }
    }
}
