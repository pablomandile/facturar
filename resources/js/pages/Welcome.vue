<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import { Button } from '@/components/ui/button';
</script>

<template>
    <Head title="FacturAR" />

    <div
        class="flex min-h-svh flex-col bg-background px-4 py-6 text-foreground"
    >
        <header class="mx-auto flex w-full max-w-3xl items-center justify-end">
            <nav class="flex items-center gap-2">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="dashboard()">Ir a mi panel</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Iniciar sesión</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="register()">Crear cuenta</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-3xl flex-1 flex-col justify-center gap-6 py-12"
        >
            <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">
                FacturAR
            </h1>

            <p class="max-w-xl text-lg text-muted-foreground">
                Facturá como monotributista directo contra ARCA: cargás tus
                clientes, armás la factura C y en segundos tenés el CAE y el PDF
                con el código QR.
            </p>

            <ul
                class="max-w-xl list-inside list-disc space-y-1 text-muted-foreground"
            >
                <li>Factura C de productos, servicios o ambos</li>
                <li>Tu certificado de ARCA guardado cifrado</li>
                <li>Probás primero en homologación, sin riesgo</li>
            </ul>

            <div v-if="!$page.props.auth.user" class="pt-2">
                <Button size="lg" as-child>
                    <Link :href="register()">Empezar</Link>
                </Button>
            </div>
        </main>
    </div>
</template>
