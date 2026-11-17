<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { privacidad, terminos } from '@/routes';
import { redirect } from '@/routes/google';

/*
 * Ingreso con Google. Sin credenciales en el .env la ruta da 404, así que el
 * botón no se dibuja.
 *
 * Es un `<a>` y no un `<Link>`: el destino redirige a accounts.google.com, y
 * una visita de Inertia no puede salir de la app.
 */

const props = withDefaults(
    defineProps<{
        modo?: 'ingresar' | 'registrarse';
        /**
         * En el login va en false: el botón de llave de acceso, que viene
         * abajo, ya trae su separador.
         */
        conSeparador?: boolean;
    }>(),
    { modo: 'ingresar', conSeparador: true },
);

const habilitado = computed(() => usePage().props.googleHabilitado === true);

const texto = computed(() =>
    props.modo === 'registrarse'
        ? 'Registrarme con Google'
        : 'Continuar con Google',
);
</script>

<template>
    <div v-if="habilitado" class="flex flex-col gap-3">
        <Button as-child variant="outline" class="w-full">
            <a :href="redirect.url()" data-test="boton-google">
                <svg
                    class="size-4"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path
                        fill="#4285F4"
                        d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.76h3.57c2.08-1.92 3.27-4.74 3.27-8.09Z"
                    />
                    <path
                        fill="#34A853"
                        d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.76c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M5.84 14.09a6.6 6.6 0 0 1 0-4.18V7.07H2.18a11 11 0 0 0 0 9.86l3.66-2.84Z"
                    />
                    <path
                        fill="#EA4335"
                        d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38Z"
                    />
                </svg>
                {{ texto }}
            </a>
        </Button>

        <!--
            Con Google la cuenta se crea sin la casilla del registro: nace sin
            versión aceptada y AceptoTerminos le pide aceptar en el primer
            pedido. Esto es solo el aviso, antes de tocar el botón.
        -->
        <p class="text-center text-xs text-pretty text-muted-foreground">
            Al continuar con Google vas a leer y aceptar los
            <a
                :href="terminos().url"
                target="_blank"
                class="underline underline-offset-4 hover:text-foreground"
                >términos</a
            >
            y la
            <a
                :href="privacidad().url"
                target="_blank"
                class="underline underline-offset-4 hover:text-foreground"
                >política de privacidad</a
            >.
        </p>

        <div v-if="conSeparador" class="relative my-3">
            <div class="absolute inset-0 flex items-center">
                <Separator class="w-full" />
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background px-2 text-muted-foreground">
                    O con tu email
                </span>
            </div>
        </div>
    </div>
</template>
