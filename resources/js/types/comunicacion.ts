/** El asunto y el texto del email de las facturas, con sus marcadores (TextoDelEmail). */
export type PlantillaEmail = {
    plantilla: string | null;
    por_defecto: string;
    marcadores: { marcador: string; ejemplo: string }[];
    maximo: number;
    asunto: string | null;
    asunto_por_defecto: string;
    asunto_maximo: number;
};
