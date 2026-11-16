<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import EmisorController from '@/actions/App/Http/Controllers/EmisorController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Emisor, OpcionAmbiente } from '@/types/emisor';

defineProps<{
    emisor: Emisor | null;
    ambientes: OpcionAmbiente[];
}>();
</script>

<template>
    <Form
        v-bind="EmisorController.update.form()"
        class="grid gap-6 sm:grid-cols-2"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="cuit">CUIT</Label>
            <Input
                id="cuit"
                name="cuit"
                inputmode="numeric"
                :default-value="emisor?.cuit_formateado"
                placeholder="20-12345678-9"
                required
            />
            <InputError :message="errors.cuit" />
        </div>

        <div class="grid gap-2">
            <Label for="razon_social">Nombre o razón social</Label>
            <Input
                id="razon_social"
                name="razon_social"
                :default-value="emisor?.razon_social"
                autocomplete="name"
                required
            />
            <InputError :message="errors.razon_social" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="domicilio_fiscal">Domicilio fiscal</Label>
            <Input
                id="domicilio_fiscal"
                name="domicilio_fiscal"
                :default-value="emisor?.domicilio_fiscal"
                autocomplete="street-address"
                required
            />
            <InputError :message="errors.domicilio_fiscal" />
        </div>

        <div class="grid gap-2">
            <Label for="ingresos_brutos">Ingresos brutos</Label>
            <Input
                id="ingresos_brutos"
                name="ingresos_brutos"
                :default-value="emisor?.ingresos_brutos ?? ''"
                placeholder="Número o «Exento»"
            />
            <p class="text-xs text-muted-foreground">
                Va impreso en la factura. Si no tenés, dejalo vacío.
            </p>
            <InputError :message="errors.ingresos_brutos" />
        </div>

        <div class="grid gap-2">
            <Label for="inicio_actividades">Inicio de actividades</Label>
            <Input
                id="inicio_actividades"
                name="inicio_actividades"
                type="date"
                :default-value="emisor?.inicio_actividades"
                required
            />
            <InputError :message="errors.inicio_actividades" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="ambiente">Ambiente de ARCA</Label>
            <select
                id="ambiente"
                name="ambiente"
                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            >
                <option
                    v-for="ambiente in ambientes"
                    :key="ambiente.value"
                    :value="ambiente.value"
                    :selected="
                        (emisor?.ambiente ?? 'homologacion') === ambiente.value
                    "
                >
                    {{ ambiente.etiqueta }}
                </option>
            </select>
            <p class="text-xs text-muted-foreground">
                Empezá en homologación: es el ambiente de pruebas de ARCA y las
                facturas no tienen validez fiscal. Cada ambiente lleva su propio
                certificado y sus puntos de venta.
            </p>
            <InputError :message="errors.ambiente" />
        </div>

        <div class="sm:col-span-2">
            <Button :disabled="processing" data-test="guardar-emisor">
                {{ emisor ? 'Guardar cambios' : 'Guardar y seguir' }}
            </Button>
        </div>
    </Form>
</template>
