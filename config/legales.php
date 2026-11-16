<?php

return [

    /*
     * Versión vigente de los términos y la política de privacidad, juntos.
     *
     * Subirla vuelve a pedir la aceptación a todos en su próxima visita: es lo
     * que hay que hacer cada vez que cambia algo importante de los textos de
     * `resources/legales/`. Un cambio de redacción que no cambia nada no la sube.
     */
    'version' => '2026-11-16',

    /*
     * Quién responde por los datos y cómo contactarlo. Van en el .env y no en
     * el repo, que es público. Sin completar, los textos muestran un aviso a
     * la vista en su lugar: completarlos es un paso del deploy.
     */
    'responsable' => env('LEGALES_RESPONSABLE'),
    'contacto' => env('LEGALES_CONTACTO'),

];
