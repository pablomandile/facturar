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

/** Lo que precarga el formulario: una factura guardada o una duplicada. */
export type ComprobanteBase = {
    punto_de_venta_id: number | null;
    cliente_id: number | null;
    concepto: number;
    fecha_emision: string;
    fecha_servicio_desde: string | null;
    fecha_servicio_hasta: string | null;
    fecha_vto_pago: string | null;
    observaciones: string | null;
    enviar_al_autorizar: boolean;
    items: ItemDelFormulario[];
};

export type ComprobanteEditable = ComprobanteBase & {
    id: number;
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

export type EnvioPorEmail = {
    id: number;
    fecha: string | null;
    destinatarios: string[];
    con_copia: boolean;
    enviado: boolean;
};

/** Lo que necesita el diálogo "Enviar por email" de una factura autorizada. */
export type EmailDeFactura = {
    /** A quién va por defecto: el cliente, o la cuenta si es de homologación. */
    para: string;
    /** Homologación: solo se puede mandar al email de la cuenta. */
    solo_propio: boolean;
    propio: string;
    /** La plantilla del emisor, ya completa con los datos de esta factura. */
    texto: string;
    envios: EnvioPorEmail[];
};

export type Opcion = { id: number; etiqueta: string };

/** Un cliente en el formulario de la factura, con su descripción habitual. */
export type OpcionCliente = Opcion & {
    plantilla: string | null;
    email: string | null;
};

export type OpcionConcepto = {
    value: number;
    etiqueta: string;
    con_servicios: boolean;
};
