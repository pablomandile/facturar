<?php

declare(strict_types=1);

namespace Tests\Support\Arca;

use App\Contracts\Arca\TransporteSoap;
use Closure;
use PHPUnit\Framework\Assert;
use RuntimeException;
use Throwable;

/**
 * Un ARCA de mentira: devuelve, en orden, lo que el test le preparó para cada
 * método, y anota cada llamada con sus parámetros para poder afirmar sobre
 * lo que se mandó por el cable.
 */
final class TransporteFalso implements TransporteSoap
{
    /** @var array<string, list<object|Throwable|Closure(array<string, mixed>): object>> */
    private array $preparadas = [];

    /** @var list<array{metodo: string, parametros: array<string, mixed>}> */
    private array $llamadas = [];

    private ?string $ultimoPedido = null;

    private ?string $ultimaRespuesta = null;

    /** @param  object|Closure(array<string, mixed>): object  $respuesta */
    public function responder(string $metodo, object $respuesta): self
    {
        $this->preparadas[$metodo][] = $respuesta;

        return $this;
    }

    public function fallar(string $metodo, Throwable $error): self
    {
        $this->preparadas[$metodo][] = $error;

        return $this;
    }

    public function llamar(string $metodo, array $parametros = []): object
    {
        $this->llamadas[] = ['metodo' => $metodo, 'parametros' => $parametros];
        $this->ultimoPedido = '<pedido>'.json_encode([$metodo => $parametros]).'</pedido>';

        $preparada = array_shift($this->preparadas[$metodo])
            ?? throw new RuntimeException("El test no preparó una respuesta para {$metodo}.");

        if ($preparada instanceof Throwable) {
            $this->ultimaRespuesta = null;

            throw $preparada;
        }

        $respuesta = $preparada instanceof Closure ? $preparada($parametros) : $preparada;
        $this->ultimaRespuesta = '<respuesta>'.json_encode($respuesta, JSON_UNESCAPED_SLASHES).'</respuesta>';

        return $respuesta;
    }

    public function ultimoPedido(): ?string
    {
        return $this->ultimoPedido;
    }

    public function ultimaRespuesta(): ?string
    {
        return $this->ultimaRespuesta;
    }

    /** @return list<array{metodo: string, parametros: array<string, mixed>}> */
    public function llamadas(?string $metodo = null): array
    {
        return array_values(array_filter(
            $this->llamadas,
            fn (array $llamada): bool => $metodo === null || $llamada['metodo'] === $metodo,
        ));
    }

    /** @return array<string, mixed> */
    public function parametrosDe(string $metodo, int $vez = 0): array
    {
        $llamadas = $this->llamadas($metodo);
        Assert::assertArrayHasKey($vez, $llamadas, "No hubo llamada número {$vez} a {$metodo}.");

        return $llamadas[$vez]['parametros'];
    }

    public function assertNoLlamo(string $metodo): void
    {
        Assert::assertSame([], $this->llamadas($metodo), "Se llamó a {$metodo} y no debía.");
    }
}
