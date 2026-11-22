<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileCheck2, QrCode, ShieldCheck } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import MarcaFacturar from '@/components/MarcaFacturar.vue';
import PieLegal from '@/components/PieLegal.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const ventajas = [
    {
        icono: FileCheck2,
        titulo: 'Factura C con CAE',
        texto: 'De productos, servicios o ambos, autorizada por ARCA en segundos.',
    },
    {
        icono: QrCode,
        titulo: 'PDF con el QR',
        texto: 'Listo para mandar, con el código que exige ARCA.',
    },
    {
        icono: ShieldCheck,
        titulo: 'Tu certificado, cifrado',
        texto: 'Y probás primero en homologación, sin riesgo.',
    },
];
</script>

<template>
    <Head title="FacturAR" />

    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <!-- Franja celeste: el mismo acento de las secciones de la app. -->
        <div class="h-1.5 bg-section-head" aria-hidden="true" />

        <header
            class="mx-auto flex w-full max-w-4xl flex-wrap items-center justify-between gap-3 px-4 py-5"
        >
            <Link
                href="/"
                class="flex items-center gap-2 text-xl"
                aria-label="FacturAR, inicio"
            >
                <AppLogoIcon class="size-9" />
                <MarcaFacturar />
            </Link>
            <nav class="flex items-center gap-2">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="dashboard()">Ir a mi panel</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Iniciar sesión</Link>
                    </Button>
                    <Button as-child>
                        <Link v-if="$page.props.demo" :href="login()"
                            >Probar la demo</Link
                        >
                        <Link v-else :href="register()">Crear cuenta</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main class="flex flex-1 flex-col">
            <section class="bg-section-tint">
                <div
                    class="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-14 sm:py-20"
                >
                    <h1
                        class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                    >
                        Facturá como monotributista,
                        <span class="text-primary">directo contra ARCA</span>.
                    </h1>
                    <p
                        class="max-w-2xl text-lg text-pretty text-muted-foreground"
                    >
                        Cargás tus clientes, armás la factura C y en segundos
                        tenés el CAE y el PDF con el código QR.
                    </p>
                    <div v-if="!$page.props.auth.user" class="flex gap-2 pt-2">
                        <Button size="lg" as-child>
                            <Link v-if="$page.props.demo" :href="login()"
                                >Probar la demo</Link
                            >
                            <Link v-else :href="register()">Empezar</Link>
                        </Button>
                        <Button size="lg" variant="outline" as-child>
                            <Link :href="login()">Ya tengo cuenta</Link>
                        </Button>
                    </div>
                </div>
            </section>

            <section
                class="mx-auto grid w-full max-w-4xl gap-4 px-4 py-12 sm:grid-cols-3"
            >
                <article
                    v-for="v in ventajas"
                    :key="v.titulo"
                    class="rounded-lg border border-t-4 border-t-primary bg-card p-5 shadow-sm"
                >
                    <component
                        :is="v.icono"
                        class="mb-3 size-6 text-primary"
                        aria-hidden="true"
                    />
                    <h2 class="font-semibold">{{ v.titulo }}</h2>
                    <p class="mt-1 text-sm text-pretty text-muted-foreground">
                        {{ v.texto }}
                    </p>
                </article>
            </section>
        </main>

        <div class="px-4 pb-6">
            <PieLegal />
        </div>
    </div>
</template>
