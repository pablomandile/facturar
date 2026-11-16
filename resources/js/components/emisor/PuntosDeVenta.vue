<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import PuntoDeVentaController from '@/actions/App/Http/Controllers/PuntoDeVentaController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Ambiente, PuntoDeVenta } from '@/types/emisor';

defineProps<{
    ambiente: Ambiente;
    puntos: PuntoDeVenta[];
}>();
</script>

<template>
    <div class="space-y-6">
        <p
            v-if="ambiente === 'homologacion'"
            class="text-sm text-muted-foreground"
        >
            En homologación no hace falta darlo de alta en ARCA: cargá el 1 y
            listo.
        </p>
        <p v-else class="text-sm text-muted-foreground">
            Tiene que existir en ARCA, en
            <strong>Administración de puntos de venta y domicilios</strong>, con
            el sistema
            <strong>Factura Electrónica - Monotributo - Web Services</strong>.
            No sirve el que usás en «Comprobantes en línea».
        </p>

        <ul v-if="puntos.length" class="divide-y rounded-lg border">
            <li
                v-for="punto in puntos"
                :key="punto.id"
                class="flex flex-wrap items-center gap-3 p-3"
            >
                <span class="font-mono font-medium">{{
                    punto.numero_formateado
                }}</span>
                <span class="text-sm text-muted-foreground">{{
                    punto.descripcion
                }}</span>
                <Badge v-if="!punto.activo" variant="secondary"
                    >Desactivado</Badge
                >
                <div class="ml-auto flex gap-1">
                    <Form
                        v-bind="PuntoDeVentaController.update.form(punto.id)"
                        v-slot="{ processing }"
                    >
                        <input
                            type="hidden"
                            name="numero"
                            :value="punto.numero"
                        />
                        <input
                            type="hidden"
                            name="descripcion"
                            :value="punto.descripcion ?? ''"
                        />
                        <input
                            type="hidden"
                            name="activo"
                            :value="punto.activo ? '0' : '1'"
                        />
                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="processing"
                        >
                            {{ punto.activo ? 'Desactivar' : 'Activar' }}
                        </Button>
                    </Form>
                    <Form
                        v-bind="PuntoDeVentaController.destroy.form(punto.id)"
                        v-slot="{ processing }"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="processing"
                        >
                            Quitar
                        </Button>
                    </Form>
                </div>
            </li>
        </ul>

        <Form
            v-bind="PuntoDeVentaController.store.form()"
            class="flex flex-wrap items-end gap-3"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="grid w-28 gap-2">
                <Label for="numero">Número</Label>
                <Input
                    id="numero"
                    name="numero"
                    type="number"
                    min="1"
                    max="99998"
                    required
                />
            </div>
            <div class="grid min-w-48 flex-1 gap-2">
                <Label for="descripcion">Descripción (opcional)</Label>
                <Input
                    id="descripcion"
                    name="descripcion"
                    placeholder="Por ejemplo: Web"
                />
            </div>
            <Button :disabled="processing" data-test="agregar-punto-de-venta">
                Agregar
            </Button>
            <InputError class="basis-full" :message="errors.numero" />
        </Form>
    </div>
</template>
