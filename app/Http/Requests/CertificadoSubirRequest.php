<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Certificado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * El certificado que devolvió ARCA. Se acepta como archivo o pegado como
 * texto: WSASS (homologación) lo muestra en pantalla y lo más fácil es
 * copiarlo; la pantalla de producción lo da para descargar.
 *
 * Que corresponda a la clave, al CUIT y al ambiente lo valida
 * `GeneradorDeCertificados::incorporar()`, que es quien sabe leerlo.
 */
class CertificadoSubirRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certificado = $this->route('certificado');

        return $certificado instanceof Certificado && ($this->user()?->can('update', $certificado) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archivo' => ['nullable', 'required_without:texto', 'file', 'max:20'],
            'texto' => ['nullable', 'required_without:archivo', 'string', 'max:20000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'archivo.required_without' => 'Subí el archivo del certificado o pegá su contenido.',
            'texto.required_without' => 'Subí el archivo del certificado o pegá su contenido.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['archivo' => 'archivo del certificado', 'texto' => 'certificado'];
    }

    public function contenido(): string
    {
        $archivo = $this->file('archivo');

        if ($archivo instanceof UploadedFile) {
            return (string) $archivo->get();
        }

        return (string) $this->input('texto');
    }
}
