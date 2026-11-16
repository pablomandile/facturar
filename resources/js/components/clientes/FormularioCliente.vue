<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ClienteController from '@/actions/App/Http/Controllers/ClienteController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    Cliente,
    OpcionCodigo,
    OpcionCondicionIva,
} from '@/types/clientes';

const props = defineProps<{
    cliente: Cliente | null;
    tiposDocumento: OpcionCodigo[];
    condicionesIva: OpcionCondicionIva[];
}>();

const emit = defineEmits<{ guardado: [] }>();

// Por defecto: CUIT y responsable inscripto, el caso más común al facturar
// a una empresa. Con DNI casi siempre es consumidor final.
const tipo = ref(props.cliente?.tipo_documento ?? 80);
const condicion = ref(props.cliente?.condicion_iva ?? 1);

const exigeCuit = computed(
    () =>
        props.condicionesIva.find((c) => c.value === condicion.value)
            ?.exige_cuit ?? false,
);

const formulario = computed(() =>
    props.cliente
        ? ClienteController.update.form(props.cliente.id)
        : ClienteController.store.form(),
);

const claseSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Form
        v-bind="formulario"
        class="grid gap-4 sm:grid-cols-2"
        v-slot="{ errors, processing }"
        @success="emit('guardado')"
    >
        <div class="grid gap-2 sm:col-span-2">
            <Label for="nombre">Nombre o razón social</Label>
            <Input
                id="nombre"
                name="nombre"
                :default-value="cliente?.nombre"
                required
            />
            <InputError :message="errors.nombre" />
        </div>

        <div class="grid gap-2">
            <Label for="tipo_documento">Documento</Label>
            <select
                id="tipo_documento"
                v-model="tipo"
                name="tipo_documento"
                :class="claseSelect"
            >
                <option
                    v-for="opcion in tiposDocumento"
                    :key="opcion.value"
                    :value="opcion.value"
                >
                    {{ opcion.etiqueta }}
                </option>
            </select>
            <InputError :message="errors.tipo_documento" />
        </div>

        <div class="grid gap-2">
            <Label for="numero_documento">Número</Label>
            <Input
                id="numero_documento"
                name="numero_documento"
                inputmode="numeric"
                :default-value="cliente?.numero_documento"
                :placeholder="tipo === 96 ? '12345678' : '30-12345678-9'"
                required
            />
            <InputError :message="errors.numero_documento" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="condicion_iva">Condición frente al IVA</Label>
            <select
                id="condicion_iva"
                v-model="condicion"
                name="condicion_iva"
                :class="claseSelect"
            >
                <option
                    v-for="opcion in condicionesIva"
                    :key="opcion.value"
                    :value="opcion.value"
                >
                    {{ opcion.etiqueta }}
                </option>
            </select>
            <p
                v-if="exigeCuit && tipo !== 80"
                class="text-xs text-muted-foreground"
            >
                Para esta condición ARCA pide identificarlo con CUIT.
            </p>
            <InputError :message="errors.condicion_iva" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="domicilio">Domicilio (opcional)</Label>
            <Input
                id="domicilio"
                name="domicilio"
                :default-value="cliente?.domicilio ?? ''"
                autocomplete="off"
            />
            <InputError :message="errors.domicilio" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="email">Email (opcional)</Label>
            <Input
                id="email"
                name="email"
                type="email"
                :default-value="cliente?.email ?? ''"
                autocomplete="off"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="sm:col-span-2">
            <Button :disabled="processing" data-test="guardar-cliente">
                {{ cliente ? 'Guardar cambios' : 'Agregar cliente' }}
            </Button>
        </div>
    </Form>
</template>
