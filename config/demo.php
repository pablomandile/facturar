<?php

/*
 * Modo demo: la copia de facturar.pablomandile.com.ar es la demo del
 * portfolio. Con APP_DEMO=true:
 *
 * - hay una cuenta compartida con una emisora inventada, que `demo:reset`
 *   vuelve a sembrar todas las noches (DemoSeeder);
 * - ARCA es simulada (FabricaDeTransportesDemo): emitir da un CAE inventado y
 *   la cuenta es de homologación, así que todo PDF dice "sin validez fiscal";
 * - nadie se registra ni cambia la cuenta, los datos fiscales, el certificado
 *   o los puntos de venta (ProtegerDemo);
 * - los mails van al log (MAIL_MAILER=log en el .env de la demo).
 *
 * En la productiva queda apagado y nada de esto actúa.
 */
return [
    'enabled' => (bool) env('APP_DEMO', false),
    'email' => 'demo@pablomandile.com.ar',
    'password' => 'demo1234',
];
