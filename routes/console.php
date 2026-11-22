<?php

use App\Models\LlamadaArca;
use Illuminate\Support\Facades\Schedule;

/*
 * En Hostinger lo dispara un cron de hPanel cada minuto con
 * `php artisan schedule:run` (sin `cd ... &&`: el ejecutor de hPanel no lo
 * soporta; ver la skill deploy-hostinger).
 */

Schedule::command('arca:vencer-certificados')->dailyAt('03:00');

// Las facturas que quedaron sin respuesta de ARCA bloquean su punto de venta:
// se resuelven seguido.
Schedule::command('arca:reconciliar-pendientes')->everyFiveMinutes()->withoutOverlapping();

// El registro de llamadas a ARCA se guarda 90 días (config/arca.php).
Schedule::command('model:prune', ['--model' => [LlamadaArca::class]])->dailyAt('03:30');

// Solo en la demo (APP_DEMO=true): la deja como nueva todas las noches, a las
// 04:00 de Argentina (la app corre en UTC).
Schedule::command('demo:reset')->dailyAt('07:00')->when(fn (): bool => (bool) config('demo.enabled'));
