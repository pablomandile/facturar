export type Ambiente = 'homologacion' | 'produccion';

export type OpcionAmbiente = {
    value: Ambiente;
    etiqueta: string;
};

export type Emisor = {
    cuit: string;
    cuit_formateado: string;
    razon_social: string;
    /** Comienzo del nombre del PDF (APELLIDO_NOMBRE); null = la sugerencia. */
    nombre_archivos: string | null;
    nombre_archivos_sugerido: string;
    domicilio_fiscal: string;
    ingresos_brutos: string | null;
    inicio_actividades: string;
    ambiente: Ambiente;
    ambiente_etiqueta: string;
};

export type Certificado = {
    id: number;
    alias: string;
    estado: 'esperando_certificado' | 'vigente' | 'vencido' | 'reemplazado';
    estado_etiqueta: string;
    generado_el: string | null;
    valido_desde: string | null;
    valido_hasta: string | null;
    dias_para_vencer: number | null;
};

export type PuntoDeVenta = {
    id: number;
    numero: number;
    numero_formateado: string;
    descripcion: string | null;
    activo: boolean;
};

export type PasosConfiguracion = {
    perfil: boolean;
    certificado: boolean;
    punto_de_venta: boolean;
};
