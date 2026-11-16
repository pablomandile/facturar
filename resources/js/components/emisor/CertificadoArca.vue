<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import CertificadoController from '@/actions/App/Http/Controllers/CertificadoController';
import InstruccionesArca from '@/components/emisor/InstruccionesArca.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fechaCorta } from '@/lib/fechas';
import type { Ambiente, Certificado } from '@/types/emisor';

defineProps<{
    ambiente: Ambiente;
    ambienteEtiqueta: string;
    vigente: Certificado | null;
    pendiente: Certificado | null;
}>();

// Con un certificado vigente, el formulario para pedir otro queda escondido
// detrás de "Renovar": es algo que se hace una vez cada dos años.
const renovando = ref(false);

const claseCampo =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <div class="space-y-6">
        <div
            v-if="vigente"
            class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border p-4"
        >
            <Badge>Vigente</Badge>
            <span class="text-sm">
                <strong>{{ vigente.alias }}</strong> · válido hasta el
                {{ fechaCorta(vigente.valido_hasta) }}
            </span>
            <span
                v-if="
                    vigente.dias_para_vencer !== null &&
                    vigente.dias_para_vencer <= 30
                "
                class="text-sm font-medium text-destructive"
            >
                Vence en {{ vigente.dias_para_vencer }} días: renovalo.
            </span>
            <Button
                v-if="!pendiente && !renovando"
                variant="outline"
                size="sm"
                class="ml-auto"
                @click="renovando = true"
            >
                Renovar
            </Button>
        </div>

        <template v-if="pendiente">
            <section class="space-y-2">
                <h3 class="font-medium">1. Bajá el pedido de certificado</h3>
                <p class="text-sm text-muted-foreground">
                    Es un archivo público: no lleva tu clave, que queda guardada
                    cifrada acá y no sale nunca.
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button as-child>
                        <a
                            :href="CertificadoController.csr.url(pendiente.id)"
                            download
                            >Bajar {{ pendiente.alias }}.csr</a
                        >
                    </Button>
                    <Form
                        v-bind="
                            CertificadoController.destroy.form(pendiente.id)
                        "
                        v-slot="{ processing }"
                    >
                        <Button variant="ghost" :disabled="processing">
                            Descartar el pedido
                        </Button>
                    </Form>
                </div>
            </section>

            <section class="space-y-2">
                <h3 class="font-medium">
                    2. Pedí el certificado en ARCA ({{ ambienteEtiqueta }})
                </h3>
                <InstruccionesArca
                    :ambiente="ambiente"
                    :alias="pendiente.alias"
                />
            </section>

            <section class="space-y-2">
                <h3 class="font-medium">3. Cargá el certificado</h3>
                <Form
                    v-bind="CertificadoController.update.form(pendiente.id)"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="texto">Pegá el certificado</Label>
                        <textarea
                            id="texto"
                            name="texto"
                            rows="6"
                            spellcheck="false"
                            placeholder="-----BEGIN CERTIFICATE-----"
                            :class="[claseCampo, 'font-mono text-xs']"
                        />
                        <InputError :message="errors.texto" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="archivo">…o subí el archivo</Label>
                        <Input
                            id="archivo"
                            name="archivo"
                            type="file"
                            accept=".crt,.pem,.cer,.der"
                        />
                        <InputError :message="errors.archivo" />
                    </div>
                    <Button
                        :disabled="processing"
                        data-test="subir-certificado"
                    >
                        Cargar certificado
                    </Button>
                </Form>
            </section>
        </template>

        <Form
            v-else-if="!vigente || renovando"
            v-bind="CertificadoController.store.form()"
            class="space-y-4"
            v-slot="{ errors, processing }"
        >
            <p class="text-sm text-muted-foreground">
                Para facturar, ARCA te pide un certificado digital. Generamos la
                clave y el pedido acá; vos solo tenés que subir el pedido a ARCA
                y traer el certificado que te devuelve.
            </p>
            <div class="grid max-w-sm gap-2">
                <Label for="alias">Nombre del certificado</Label>
                <Input
                    id="alias"
                    name="alias"
                    default-value="facturar"
                    autocomplete="off"
                    required
                />
                <p class="text-xs text-muted-foreground">
                    Solo letras y números. Es como lo vas a ver en ARCA.
                </p>
                <InputError :message="errors.alias" />
            </div>
            <div class="flex gap-2">
                <Button :disabled="processing" data-test="generar-certificado">
                    Generar el pedido
                </Button>
                <Button
                    v-if="renovando"
                    type="button"
                    variant="ghost"
                    @click="renovando = false"
                >
                    Cancelar
                </Button>
            </div>
        </Form>
    </div>
</template>
