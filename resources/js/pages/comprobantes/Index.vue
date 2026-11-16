<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import ComprobanteController from '@/actions/App/Http/Controllers/ComprobanteController';
import EstadoBadge from '@/components/comprobantes/EstadoBadge.vue';
import { Button } from '@/components/ui/button';
import { fechaCorta } from '@/lib/fechas';
import type { Paginado } from '@/types/clientes';
import type { FilaComprobante } from '@/types/comprobantes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Facturas', href: ComprobanteController.index() },
        ],
    },
});

defineProps<{
    comprobantes: Paginado<FilaComprobante>;
    estado: string | null;
    estados: { value: string; etiqueta: string }[];
    ambiente: string;
}>();
</script>

<template>
    <Head title="Facturas" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <header class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Facturas</h1>
                <p class="text-sm text-muted-foreground">{{ ambiente }}</p>
            </div>
            <Button class="ml-auto" as-child>
                <Link
                    :href="ComprobanteController.create()"
                    data-test="nueva-factura"
                >
                    <Plus aria-hidden="true" /> Nueva factura
                </Link>
            </Button>
        </header>

        <nav class="flex flex-wrap gap-1" aria-label="Filtrar por estado">
            <Button
                :variant="estado === null ? 'secondary' : 'ghost'"
                size="sm"
                as-child
            >
                <Link :href="ComprobanteController.index()">Todas</Link>
            </Button>
            <Button
                v-for="opcion in estados"
                :key="opcion.value"
                :variant="estado === opcion.value ? 'secondary' : 'ghost'"
                size="sm"
                as-child
            >
                <Link
                    :href="
                        ComprobanteController.index({
                            query: { estado: opcion.value },
                        })
                    "
                    >{{ opcion.etiqueta }}</Link
                >
            </Button>
        </nav>

        <p
            v-if="!comprobantes.data.length"
            class="rounded-lg border p-6 text-center text-sm text-muted-foreground"
        >
            No hay facturas{{ estado ? ' en ese estado' : ' todavía' }}.
        </p>

        <ul v-else class="divide-y rounded-lg border">
            <li v-for="fila in comprobantes.data" :key="fila.id">
                <Link
                    :href="ComprobanteController.show(fila.id)"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 p-3 hover:bg-accent"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ fila.receptor }}</p>
                        <p class="font-mono text-sm text-muted-foreground">
                            {{ fila.numero }} · {{ fechaCorta(fila.fecha) }}
                        </p>
                    </div>
                    <span class="tabular-nums">$ {{ fila.total }}</span>
                    <EstadoBadge
                        :estado="fila.estado"
                        :etiqueta="fila.estado_etiqueta"
                    />
                </Link>
            </li>
        </ul>

        <nav
            v-if="comprobantes.last_page > 1"
            class="flex items-center justify-between text-sm"
            aria-label="Páginas"
        >
            <Link
                v-if="comprobantes.prev_page_url"
                :href="comprobantes.prev_page_url"
                class="underline underline-offset-4"
                >Anterior</Link
            >
            <span v-else />
            <span class="text-muted-foreground"
                >Página {{ comprobantes.current_page }} de
                {{ comprobantes.last_page }}</span
            >
            <Link
                v-if="comprobantes.next_page_url"
                :href="comprobantes.next_page_url"
                class="underline underline-offset-4"
                >Siguiente</Link
            >
            <span v-else />
        </nav>
    </div>
</template>
