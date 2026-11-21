export type Ambiente = 'homologacion' | 'produccion';

export type OpcionAmbiente = {
    value: Ambiente;
    etiqueta: string;
};

export type Emisor = {
    cuit: string;
    cuit_formateado: string;
    razon_social: string;
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

/** El texto del email de las facturas, con sus marcadores (TextoDelEmail). */
export type PlantillaEmail = {
    plantilla: string | null;
    por_defecto: string;
    marcadores: { marcador: string; ejemplo: string }[];
    maximo: number;
};

export type PasosConfiguracion = {
    perfil: boolean;
    certificado: boolean;
    punto_de_venta: boolean;
};
