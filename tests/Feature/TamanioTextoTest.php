<?php

declare(strict_types=1);

use App\Enums\TamanioTexto;
use App\Models\User;
use Inertia\Support\SessionKey;

it('viene en Normal, el del medio, y no en el más chico', function (): void {
    $casos = TamanioTexto::cases();

    expect(TamanioTexto::porDefecto())->toBe(TamanioTexto::Normal)
        ->and($casos[1])->toBe(TamanioTexto::porDefecto())
        ->and(User::factory()->create()->tamanio_texto)->toBe(TamanioTexto::Normal);
});

/*
 * En px pisaría el tamaño que la persona configuró en su navegador. El CSS y
 * el enum tienen que decir lo mismo.
 */
it('expresa los tamaños en porcentaje, y el CSS coincide con el enum', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    foreach (TamanioTexto::cases() as $caso) {
        expect($caso->porcentaje())->toEndWith('%')
            ->and(str_contains($css, "html[data-texto='{$caso->value}'] {\n    font-size: {$caso->porcentaje()};"))
            ->toBeTrue("app.css no tiene {$caso->value} en {$caso->porcentaje()}");
    }
});

it('escribe el atributo en el html, sin depender de JavaScript', function (): void {
    expect($this->get('/login')->getContent())->toContain('data-texto="normal"');
});

it('un invitado puede cambiar la letra y se recuerda por la cookie', function (): void {
    $this->put(route('tamanio-texto.update'), ['tamanio_texto' => 'grande'])
        ->assertRedirect()
        ->assertCookie('tamanio_texto', 'grande', encrypted: false);

    expect($this->withUnencryptedCookie('tamanio_texto', 'grande')->get('/login')->getContent())
        ->toContain('data-texto="grande"');
});

it('a quien tiene sesión se lo guarda en la cuenta y avisa con un toast', function (): void {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->put(route('tamanio-texto.update'), ['tamanio_texto' => 'pequeno'])
        ->assertRedirect();

    expect($usuario->refresh()->tamanio_texto)->toBe(TamanioTexto::Pequeno)
        ->and(session()->get(SessionKey::FLASH_DATA)['toast']['type'] ?? null)->toBe('success');
});

it('manda la cuenta por encima de la cookie', function (): void {
    $usuario = User::factory()->create();
    $usuario->forceFill(['tamanio_texto' => TamanioTexto::Grande])->save();

    $html = $this->actingAs($usuario)
        ->withUnencryptedCookie('tamanio_texto', 'pequeno')
        ->get(route('dashboard'))
        ->getContent();

    expect($html)->toContain('data-texto="grande"');
});

it('deja cambiar la letra a quien todavía no aceptó los términos', function (): void {
    $usuario = User::factory()->sinAceptarTerminos()->create();

    $this->actingAs($usuario)
        ->put(route('tamanio-texto.update'), ['tamanio_texto' => 'grande'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($usuario->refresh()->tamanio_texto)->toBe(TamanioTexto::Grande);
});

it('rechaza un tamaño que no existe', function (): void {
    $this->put(route('tamanio-texto.update'), ['tamanio_texto' => 'gigante'])
        ->assertSessionHasErrors('tamanio_texto');
});

it('una cookie con basura cae en el tamaño por defecto', function (): void {
    expect($this->withUnencryptedCookie('tamanio_texto', 'jajaja')->get('/login')->getContent())
        ->toContain('data-texto="normal"');
});

it('la pantalla de Apariencia ofrece los tres tamaños con su escala', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('settings/Appearance')
            ->where('tamanioTexto', 'normal')
            ->has('tamaniosTexto', 3)
            ->has('tamaniosTexto.0', fn ($opcion) => $opcion
                ->where('valor', 'pequeno')
                ->where('escala', 87.5)
                ->etc()
            )
        );
});
