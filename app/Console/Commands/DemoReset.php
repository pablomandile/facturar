<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Vacía la base y vuelve a sembrar la demo. Corre todas las noches en la
 * copia de pablomandile (routes/console.php), y solo con APP_DEMO=true.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Borra todos los datos y resiembra la emisora de la demo (solo con APP_DEMO=true)';

    public function handle(): int
    {
        // Sin el flag no se toca nada: este comando borra la base entera.
        if (! config('demo.enabled')) {
            $this->error('La demo no está activa (APP_DEMO). No se borró nada.');

            return self::FAILURE;
        }

        // `AppServiceProvider` prohíbe `migrate:fresh` en producción, y la demo
        // corre con APP_ENV=production. Se levanta solo acá, que ya exige el flag.
        DB::prohibitDestructiveCommands(false);

        if ($this->call('migrate:fresh', ['--force' => true]) !== self::SUCCESS) {
            $this->error('No se pudo vaciar la base. No se sembró nada.');

            return self::FAILURE;
        }

        // Lo que los visitantes hicieron durante el día (y los mails, que en
        // la demo van al log) no tiene por qué quedar.
        $log = storage_path('logs/laravel.log');
        if (File::exists($log)) {
            File::put($log, '');
        }

        return $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
    }
}
