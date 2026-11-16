<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Cookie } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAvisoCookies } from '@/composables/useAvisoCookies';
import { cookies } from '@/routes';

/*
 * Aviso de cookies: informativo, con un solo botón.
 *
 * Todo lo que FacturAR guarda en el navegador es necesario para que funcione
 * —sesión, protección de formularios, preferencias, la pantalla sin
 * conexión—, así que no hay nada que rechazar: un «Rechazar» sería de
 * adorno, lo único que podría apagar es el login. El detalle está en la
 * página de cookies, que el test AvisoCookiesTest mantiene completa.
 *
 * Fijo abajo en el celular; desde `md` una tarjeta angosta en la esquina
 * inferior izquierda (los toasts van arriba a la derecha en escritorio).
 */

const { visible, cerrar } = useAvisoCookies();
</script>

<template>
    <div
        v-if="visible"
        role="region"
        aria-label="Aviso de cookies"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-card px-4 pb-[env(safe-area-inset-bottom)] shadow-lg md:inset-x-auto md:bottom-4 md:left-4 md:w-sm md:rounded-xl md:border md:pb-0"
    >
        <div class="flex flex-col gap-3 py-3">
            <div class="flex gap-3">
                <Cookie
                    class="mt-0.5 size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <p class="text-sm text-pretty wrap-anywhere">
                    FacturAR usa cookies y guarda datos en tu dispositivo solo
                    para funcionar: mantener tu sesión y recordar tus
                    preferencias. Nada de publicidad ni seguimiento.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2">
                <Link
                    :href="cookies()"
                    class="inline-flex min-h-11 items-center px-2 text-sm text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline"
                >
                    Más información
                </Link>

                <!-- size="sm" da 32px de alto: en el celular hacen falta 44. -->
                <Button size="sm" class="min-h-11" @click="cerrar">
                    Entendido
                </Button>
            </div>
        </div>
    </div>
</template>
