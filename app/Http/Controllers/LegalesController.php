<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AceptarTerminosRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los términos, la política de privacidad y su aceptación.
 *
 * Los textos viven en `resources/legales/*.md` y se convierten acá: una sola
 * fuente, que se edita como texto y no como plantilla de Vue. Lo que cambia
 * por instalación (quién es el responsable, el mail de contacto) entra por
 * config y no está escrito en el repo, que es público.
 */
class LegalesController extends Controller
{
    private const TEXTOS = [
        'terminos' => 'Términos y condiciones',
        'privacidad' => 'Política de privacidad',
    ];

    public function terminos(): Response
    {
        return $this->texto('terminos');
    }

    public function privacidad(): Response
    {
        return $this->texto('privacidad');
    }

    public function aceptar(Request $peticion): Response
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User);

        return Inertia::render('auth/AceptarTerminos', [
            'primeraVez' => $usuario->terminos_version === null,
            'version' => self::versionLegible(),
        ]);
    }

    public function guardarAceptacion(AceptarTerminosRequest $peticion): RedirectResponse
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User);
        $usuario->aceptarTerminos();

        return redirect()->intended(route('dashboard'));
    }

    /** El texto en markdown con los datos de esta instalación ya puestos. */
    public static function markdown(string $cual): string
    {
        $falta = fn (string $que): string => "[falta configurar {$que}]";

        return strtr((string) file_get_contents(resource_path("legales/{$cual}.md")), [
            '{responsable}' => (string) (config('legales.responsable') ?: $falta('LEGALES_RESPONSABLE')),
            '{contacto}' => (string) (config('legales.contacto') ?: $falta('LEGALES_CONTACTO')),
            '{retencion_llamadas}' => (string) config('arca.retencion_llamadas_dias'),
        ]);
    }

    private function texto(string $cual): Response
    {
        return Inertia::render('Legal', [
            'titulo' => self::TEXTOS[$cual],
            'version' => self::versionLegible(),
            // Texto propio, del repo: el escape es por si alguien pega HTML en el .md.
            'html' => Str::markdown(self::markdown($cual), ['html_input' => 'escape']),
        ]);
    }

    /** «16 de noviembre de 2026», en el locale de la app. */
    private static function versionLegible(): string
    {
        $version = config('legales.version');

        return CarbonImmutable::parse(is_string($version) ? $version : 'now')->translatedFormat('j \d\e F \d\e Y');
    }
}
