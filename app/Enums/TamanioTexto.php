<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tamaño de letra de la app. Tres opciones y la del medio por defecto: nadie
 * hereda la más chica, se elige.
 *
 * En porcentaje y no en px: un `font-size` en px en la raíz pisa el tamaño que
 * la persona ya configuró en su navegador; con `%` las dos preferencias se
 * multiplican. Tailwind mide en `rem`, así que escala la letra y el espaciado.
 * Los breakpoints (`md:`) NO escalan: la revisión de desborde es una matriz de
 * anchos por tamaños.
 */
enum TamanioTexto: string
{
    /** Duplica a `porDefecto()`: el `$attributes` de User solo admite constantes. */
    public const string PORDEFECTO = 'normal';

    case Pequeno = 'pequeno';
    case Normal = 'normal';
    case Grande = 'grande';

    public static function porDefecto(): self
    {
        return self::from(self::PORDEFECTO);
    }

    /**
     * La cookie la escribe el cliente: un valor desconocido cae en el default.
     */
    public static function desde(?string $valor): self
    {
        return self::tryFrom((string) $valor) ?? self::porDefecto();
    }

    public function porcentaje(): string
    {
        return match ($this) {
            self::Pequeno => '87.5%',
            self::Normal => '100%',
            self::Grande => '112.5%',
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pequeno => 'Pequeño',
            self::Normal => 'Normal',
            self::Grande => 'Grande',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Pequeno => 'Entra más en pantalla, con letra más chica.',
            self::Normal => 'El tamaño habitual. Es el que viene puesto.',
            self::Grande => 'Más fácil de leer. Entra menos en pantalla.',
        };
    }

    /** El porcentaje como número, para dibujar cada opción a su tamaño real. */
    public function escala(): float
    {
        return (float) rtrim($this->porcentaje(), '%');
    }

    /**
     * @return list<array{valor: string, etiqueta: string, descripcion: string, escala: float}>
     */
    public static function opciones(): array
    {
        return array_map(static fn (self $caso): array => [
            'valor' => $caso->value,
            'etiqueta' => $caso->etiqueta(),
            'descripcion' => $caso->descripcion(),
            'escala' => $caso->escala(),
        ], self::cases());
    }
}
