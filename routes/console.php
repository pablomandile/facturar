<?php

use App\Models\LlamadaArca;
use Illuminate\Support\Facades\Schedule;

/*
 * En Hostinger lo dispara un cron de hPanel cada minuto con
 * `php artisan schedule:run` (sin `cd ... &&`: el ejecutor de hPanel no lo
 * soporta; ver la skill deploy-hostinger).
 */

Schedule::command('arca:vencer-certificados')->dailyAt('03:00');

// El registro de llamadas a ARCA se guarda 90 días (config/arca.php).
Schedule::command('model:prune', ['--model' => [LlamadaArca::class]])->dailyAt('03:30');
