<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Arca\EstadoCertificado;
use App\Models\Certificado;
use App\Models\User;

/**
 * Los certificados son del emisor de la cuenta. Solo se sube el certificado
 * de ARCA a un pedido que lo está esperando, y solo se descarta un pedido:
 * un certificado que ya se usó para facturar queda como historial.
 */
class CertificadoPolicy
{
    public function view(User $usuario, Certificado $certificado): bool
    {
        return $this->esSuyo($usuario, $certificado);
    }

    public function update(User $usuario, Certificado $certificado): bool
    {
        return $this->esSuyo($usuario, $certificado)
            && $certificado->estado === EstadoCertificado::EsperandoCertificado;
    }

    public function delete(User $usuario, Certificado $certificado): bool
    {
        return $this->update($usuario, $certificado);
    }

    private function esSuyo(User $usuario, Certificado $certificado): bool
    {
        return $certificado->emisor->usuario_id === $usuario->id;
    }
}
