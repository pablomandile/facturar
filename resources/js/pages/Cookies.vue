<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, home } from '@/routes';

/*
 * Qué guarda FacturAR en el navegador y para qué. Es el inventario completo:
 * AvisoCookiesTest busca en el código cada clave que se escribe en el
 * navegador y exige que aparezca acá, así que una clave nueva sin declarar
 * rompe la suite.
 *
 * Se abre con sesión y sin ella: trae su propio encabezado (ver app.ts).
 */

type Dato = {
    nombre: string;
    para: string;
    dura: string;
};

type Grupo = {
    titulo: string;
    descripcion: string;
    datos: Dato[];
};

const GRUPOS: Grupo[] = [
    {
        titulo: 'Cookies',
        descripcion:
            'Pequeños datos que el navegador manda en cada pedido a FacturAR.',
        datos: [
            {
                nombre: 'facturar-session',
                para: 'Mantener tu sesión abierta mientras usás la app.',
                dura: 'Dos horas sin actividad',
            },
            {
                nombre: 'XSRF-TOKEN',
                para: 'Comprobar que cada formulario lo enviaste vos desde FacturAR y no otro sitio en tu nombre.',
                dura: 'Lo mismo que la sesión',
            },
            {
                nombre: 'remember_web_…',
                para: 'Solo si tildás «Recordarme» al ingresar: no tener que volver a escribir la contraseña.',
                dura: 'Hasta que salgas de la cuenta',
            },
            {
                nombre: 'appearance',
                para: 'Si elegiste el modo claro, el oscuro o el del sistema.',
                dura: 'Un año',
            },
            {
                nombre: 'sidebar_state',
                para: 'Si dejaste el menú lateral abierto o cerrado en la computadora.',
                dura: 'Una semana',
            },
        ],
    },
    {
        titulo: 'Guardado en tu dispositivo',
        descripcion:
            'Datos que quedan en el navegador y no viajan al servidor.',
        datos: [
            {
                nombre: 'appearance',
                para: 'La misma preferencia de tema, para aplicarla antes de que cargue la página y no ver un parpadeo.',
                dura: 'Hasta que la cambies',
            },
            {
                nombre: 'facturar:aviso-cookies',
                para: 'Que ya leíste este aviso, para no mostrártelo de nuevo.',
                dura: 'Hasta que borres los datos del navegador',
            },
            {
                nombre: 'facturar-v…',
                para: 'Una copia del programa de la app, sus íconos y la pantalla de «sin conexión», para que abra rápido. No guarda tus facturas ni tus datos.',
                dura: 'Hasta la próxima versión de FacturAR',
            },
        ],
    },
];

const page = usePage();
const conSesion = computed(() => Boolean(page.props.auth.user));
</script>

<template>
    <Head title="Cookies y datos guardados" />

    <div class="min-h-svh bg-background text-foreground">
        <header
            class="mx-auto flex w-full max-w-2xl items-center gap-3 px-4 pt-4 sm:px-6 sm:pt-6"
        >
            <AppLogoIcon class="size-8" />
            <Link
                :href="conSesion ? dashboard() : home()"
                class="ml-auto inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4 shrink-0" aria-hidden="true" />
                {{ conSesion ? 'Volver a la app' : 'Volver al inicio' }}
            </Link>
        </header>

        <main
            class="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4 pb-12 sm:p-6"
        >
            <h1 class="text-xl font-semibold">Cookies y datos guardados</h1>

            <div class="rounded-xl border border-border bg-card p-6">
                <p class="text-pretty">
                    Todo lo que FacturAR guarda en tu navegador es necesario
                    para que funcione. No hay publicidad, ni estadísticas de
                    uso, ni nada de terceros que siga lo que hacés: hasta las
                    tipografías salen de nuestro propio servidor.
                </p>
            </div>

            <section
                v-for="grupo in GRUPOS"
                :key="grupo.titulo"
                class="flex flex-col gap-3"
            >
                <div>
                    <h2 class="font-semibold">{{ grupo.titulo }}</h2>
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{ grupo.descripcion }}
                    </p>
                </div>

                <!--
                    Tarjetas apiladas y no una tabla: con tres columnas, en el
                    celular y con letra grande, la tabla se iría de ancho.
                -->
                <dl
                    class="divide-y divide-border rounded-xl border border-border bg-card"
                >
                    <div
                        v-for="dato in grupo.datos"
                        :key="dato.nombre"
                        class="flex flex-col gap-1 p-4"
                    >
                        <dt class="font-mono text-sm wrap-anywhere">
                            {{ dato.nombre }}
                        </dt>
                        <dd class="text-sm text-pretty">{{ dato.para }}</dd>
                        <dd class="text-xs text-muted-foreground">
                            {{ dato.dura }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="flex flex-col gap-2">
                <h2 class="font-semibold">Cómo borrarlo</h2>
                <p class="text-sm text-pretty">
                    Al salir de tu cuenta se cierra la sesión. El resto lo podés
                    borrar cuando quieras desde la configuración de tu
                    navegador; si borrás las cookies, vas a tener que volver a
                    iniciar sesión.
                </p>
            </section>
        </main>
    </div>
</template>
