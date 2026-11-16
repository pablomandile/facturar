export type EstadoComprobante =
    | 'borrador'
    | 'pendiente'
    | 'autorizado'
    | 'rechazado'
    | 'error';

export type FilaComprobante = {
    id: number;
    numero: string;
    tiene_numero: boolean;
    fecha: string;
    receptor: string;
    total: string;
    estado: EstadoComprobante;
    estado_etiqueta: string;
};

export type ItemDelFormulario = {
    descripcion: string;
    cantidad: string;
    unidad: string | null;
    precio_unitario: string;
};

export type ComprobanteEditable = {
    id: number;
    punto_de_venta_id: number;
    cliente_id: number | null;
    concepto: number;
    fecha_emision: string;
    fecha_servicio_desde: string | null;
    fecha_servicio_hasta: string | null;
    fecha_vto_pago: string | null;
    observaciones: string | null;
    items: ItemDelFormulario[];
};

export type ObservacionArca = { codigo: number; mensaje: string };

export type ComprobanteDetalle = {
    id: number;
    tipo: string;
    numero: string;
    tiene_numero: boolean;
    estado: EstadoComprobante;
    estado_etiqueta: string;
    ambiente: string;
    concepto: string;
    fecha_emision: string;
    fecha_servicio_desde: string | null;
    fecha_servicio_hasta: string | null;
    fecha_vto_pago: string | null;
    receptor: {
        nombre: string;
        documento: string | null;
        condicion_iva: string;
        domicilio: string | null;
    };
    items: {
        descripcion: string;
        cantidad: string;
        unidad: string | null;
        precio_unitario: string;
        importe: string;
    }[];
    total: string;
    cae: string | null;
    cae_vencimiento: string | null;
    observaciones: string | null;
    respuesta_arca: {
        observaciones?: ObservacionArca[];
        errores?: ObservacionArca[];
        eventos?: ObservacionArca[];
        mensaje?: string;
    } | null;
};

export type Opcion = { id: number; etiqueta: string };

export type OpcionConcepto = {
    value: number;
    etiqueta: string;
    con_servicios: boolean;
};
