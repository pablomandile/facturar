import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

/**
 * Convierte el flash `toast` del servidor en un aviso. Se engancha al evento
 * `flash` del router, que se dispara una vez por respuesta: dos avisos
 * seguidos con el mismo texto se muestran los dos.
 *
 * Los errores no se cierran solos: suelen traer lo que contestó ARCA, que hay
 * que poder leer dos veces. Los demás se van a los pocos segundos.
 */
export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](
            data.message,
            data.type === 'error' ? { duration: Infinity } : undefined,
        );
    });
}
