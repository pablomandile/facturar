<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;

it('el manifest es válido y sus íconos existen', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toMatchArray(['display' => 'standalone', 'start_url' => '/dashboard', 'short_name' => 'FacturAR'])
        ->and(collect($manifest['icons'])->pluck('purpose'))->toContain('maskable');

    foreach ($manifest['icons'] as $icono) {
        $ruta = public_path(strtok($icono['src'], '?'));
        expect($ruta)->toBeReadableFile();
        [$ancho, $alto] = getimagesize($ruta);
        expect("{$ancho}x{$alto}")->toBe($icono['sizes']);
    }
});

it('el service worker tiene handler de fetch y el ?v= coincide con el del manifest y el HTML', function () {
    $sw = (string) file_get_contents(public_path('sw.js'));
    $manifest = (string) file_get_contents(public_path('manifest.webmanifest'));
    $html = (string) file_get_contents(resource_path('views/app.blade.php'));

    preg_match('/icon-192\.png\?v=(\d+)/', $manifest, $enManifest);
    preg_match('/icon-192\.png\?v=(\d+)/', $sw, $enSw);
    preg_match('/favicon-32\.png\?v=(\d+)/', $html, $enHtml);

    expect($sw)->toContain("addEventListener('fetch'")
        ->and($enSw[1])->toBe($enManifest[1])
        ->and($enHtml[1])->toBe($enManifest[1]);
});

it('la pantalla sin conexión se dibuja sin assets compilados', function () {
    $this->get('/offline')
        ->assertOk()
        ->assertSee('No hay conexión')
        ->assertDontSee('/build/', false);
});

it('la respuesta JSON de Inertia no se guarda en caché y varía por X-Inertia', function () {
    $this->actingAs(User::factory()->create());

    $json = $this->get(route('dashboard'), ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request())]);
    expect($json->headers->get('Cache-Control'))->toContain('no-store')
        ->and($json->headers->get('Vary'))->toContain('X-Inertia');

    // El documento HTML no: perdería el back/forward cache de Chrome.
    $html = $this->get(route('dashboard'));
    expect($html->headers->get('Cache-Control'))->not->toContain('no-store')
        ->and($html->headers->get('Vary'))->toContain('X-Inertia');
});
