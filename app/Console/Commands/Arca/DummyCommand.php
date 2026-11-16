<?php

declare(strict_types=1);

namespace App\Console\Commands\Arca;

use App\Services\Arca\ClienteWsfe;
use Illuminate\Console\Command;

/** ¿Están arriba los servidores de WSFE en el ambiente del emisor? */
class DummyCommand extends Command
{
    use ResuelveEmisor;

    protected $signature = 'arca:dummy {emisor : id o CUIT}';

    protected $description = 'Consulta FEDummy: el estado de los servidores de facturación de ARCA';

    public function handle(ClienteWsfe $wsfe): int
    {
        return $this->conArca(function () use ($wsfe): int {
            $emisor = $this->emisor();
            $this->encabezado($emisor);
            $estado = $wsfe->dummy($emisor);

            $this->table(['Servidor', 'Estado'], [
                ['Aplicación', $estado->aplicacion],
                ['Base de datos', $estado->baseDeDatos],
                ['Autenticación', $estado->autenticacion],
            ]);

            return $estado->todoOk() ? self::SUCCESS : self::FAILURE;
        });
    }
}
