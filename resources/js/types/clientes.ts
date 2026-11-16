export type Cliente = {
    id: number;
    tipo_documento: number;
    numero_documento: string;
    documento: string;
    nombre: string;
    condicion_iva: number;
    condicion_iva_etiqueta: string;
    domicilio: string | null;
    email: string | null;
};

export type OpcionCodigo = {
    value: number;
    etiqueta: string;
};

export type OpcionCondicionIva = OpcionCodigo & {
    exige_cuit: boolean;
};

/** Lo que manda un paginador de Laravel a Inertia. */
export type Paginado<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
