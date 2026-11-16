<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ExportacionController from '@/actions/App/Http/Controllers/ExportacionController';
import LegalesController from '@/actions/App/Http/Controllers/LegalesController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout, privacidad, terminos } from '@/routes';

/*
 * «Aceptar y continuar». La muestra el middleware AceptoTerminos a quien no
 * aceptó la versión vigente: las cuentas de antes de que existieran los
 * textos y todos cuando se sube la versión.
 *
 * Sin aceptar igual se puede salir, llevarse los datos o borrar la cuenta: son
 * derechos que no dependen de aceptar nada.
 */

defineProps<{
    primeraVez: boolean;
    version: string;
}>();

defineOptions({
    layout: {
        title: 'Antes de seguir',
        description: 'Leé y aceptá los términos y la política de privacidad',
    },
});
</script>

<template>
    <Head title="Términos y privacidad" />

    <Form
        v-bind="LegalesController.guardarAceptacion.form()"
        class="flex flex-col gap-6"
        v-slot="{ errors, processing }"
    >
        <p class="text-sm text-pretty">
            <template v-if="primeraVez">FacturAR ahora tiene</template>
            <template v-else>Actualizamos</template>
            los
            <a
                :href="terminos().url"
                target="_blank"
                class="underline underline-offset-4 hover:text-primary"
                >términos y condiciones</a
            >
            y la
            <a
                :href="privacidad().url"
                target="_blank"
                class="underline underline-offset-4 hover:text-primary"
                >política de privacidad</a
            >
            (versión del {{ version }}). Explican qué datos guardamos, para qué,
            y qué podés hacer con ellos. Leelos antes de seguir.
        </p>

        <div class="grid gap-1">
            <label
                class="flex min-h-11 cursor-pointer items-start gap-3 text-sm text-pretty"
            >
                <input
                    type="checkbox"
                    name="terminos"
                    value="1"
                    required
                    class="mt-0.5 size-5 shrink-0 accent-primary"
                />
                <span
                    >Leí y acepto los términos y la política de
                    privacidad.</span
                >
            </label>
            <InputError :message="errors.terminos" />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            Aceptar y continuar
        </Button>

        <p class="text-center text-sm text-pretty text-muted-foreground">
            Si no estás de acuerdo podés
            <a
                :href="ExportacionController.datos.url()"
                class="underline underline-offset-4 hover:text-foreground"
                >descargar tus datos</a
            >
            o
            <Link
                :href="logout()"
                as="button"
                class="underline underline-offset-4 hover:text-foreground"
                >salir</Link
            >.
        </p>
    </Form>
</template>
