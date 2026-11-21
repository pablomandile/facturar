<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import ClienteController from '@/actions/App/Http/Controllers/ClienteController';
import ComprobanteController from '@/actions/App/Http/Controllers/ComprobanteController';
import FormularioCliente from '@/components/clientes/FormularioCliente.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type {
    Cliente,
    OpcionCodigo,
    OpcionCondicionIva,
    Paginado,
} from '@/types/clientes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clientes', href: ClienteController.index() }],
    },
});

defineProps<{
    clientes: Paginado<Cliente>;
    busqueda: string;
    tiposDocumento: OpcionCodigo[];
    condicionesIva: OpcionCondicionIva[];
}>();

// El diálogo de alta y edición: `editando` null es un cliente nuevo.
const abierto = ref(false);
const editando = ref<Cliente | null>(null);

function abrir(cliente: Cliente | null): void {
    editando.value = cliente;
    abierto.value = true;
}

function borrar(cliente: Cliente): void {
    router.delete(ClienteController.destroy.url(cliente.id), {
        preserveScroll: true,
        onBefore: () =>
            confirm(
                `¿Borrar a ${cliente.nombre}? Las facturas ya emitidas no cambian.`,
            ),
    });
}
</script>

<template>
    <Head title="Clientes" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <header class="flex flex-wrap items-center gap-3">
            <h1 class="text-xl font-semibold tracking-tight">Clientes</h1>
            <Button
                class="ml-auto"
                data-test="nuevo-cliente"
                @click="abrir(null)"
            >
                <Plus aria-hidden="true" /> Nuevo cliente
            </Button>
        </header>

        <Form
            v-bind="ClienteController.index.form()"
            class="flex gap-2"
            :options="{ preserveState: true }"
        >
            <Input
                name="q"
                type="search"
                :default-value="busqueda"
                placeholder="Buscar por nombre o documento"
                aria-label="Buscar clientes"
            />
            <Button variant="outline" size="icon" aria-label="Buscar">
                <Search aria-hidden="true" />
            </Button>
        </Form>

        <p
            v-if="!clientes.data.length"
            class="rounded-lg border p-6 text-center text-sm text-muted-foreground"
        >
            {{
                busqueda
                    ? 'No hay clientes que coincidan con la búsqueda.'
                    : 'Todavía no cargaste clientes. Para facturar a consumidor final no hace falta.'
            }}
        </p>

        <ul
            v-else
            class="divide-y overflow-hidden rounded-lg border bg-card shadow-sm [&>*:nth-child(even)]:bg-section-tint/50"
        >
            <li
                v-for="cliente in clientes.data"
                :key="cliente.id"
                class="flex flex-wrap items-center gap-x-3 gap-y-1 p-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ cliente.nombre }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ cliente.documento }} ·
                        {{ cliente.condicion_iva_etiqueta }}
                    </p>
                </div>
                <div class="flex gap-1">
                    <Button variant="outline" size="sm" as-child>
                        <Link
                            :href="
                                ComprobanteController.create({
                                    query: { cliente: cliente.id },
                                })
                            "
                            data-test="facturar-cliente"
                            >Facturar</Link
                        >
                    </Button>
                    <Button variant="ghost" size="sm" @click="abrir(cliente)">
                        Editar
                    </Button>
                    <Button variant="ghost" size="sm" @click="borrar(cliente)">
                        Borrar
                    </Button>
                </div>
            </li>
        </ul>

        <nav
            v-if="clientes.last_page > 1"
            class="flex items-center justify-between text-sm"
            aria-label="Páginas"
        >
            <Link
                v-if="clientes.prev_page_url"
                :href="clientes.prev_page_url"
                preserve-scroll
                class="underline underline-offset-4"
                >Anterior</Link
            >
            <span v-else />
            <span class="text-muted-foreground"
                >Página {{ clientes.current_page }} de
                {{ clientes.last_page }}</span
            >
            <Link
                v-if="clientes.next_page_url"
                :href="clientes.next_page_url"
                preserve-scroll
                class="underline underline-offset-4"
                >Siguiente</Link
            >
            <span v-else />
        </nav>
    </div>

    <Dialog v-model:open="abierto">
        <DialogContent class="max-h-[90svh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{
                    editando ? 'Editar cliente' : 'Nuevo cliente'
                }}</DialogTitle>
                <DialogDescription>
                    Los datos que van en la factura como receptor.
                </DialogDescription>
            </DialogHeader>
            <FormularioCliente
                :key="editando?.id ?? 'nuevo'"
                :cliente="editando"
                :tipos-documento="tiposDocumento"
                :condiciones-iva="condicionesIva"
                @guardado="abierto = false"
            />
        </DialogContent>
    </Dialog>
</template>
