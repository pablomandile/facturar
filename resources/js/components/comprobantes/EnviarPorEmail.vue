<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import { ref } from 'vue';
import ComprobanteController from '@/actions/App/Http/Controllers/ComprobanteController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EmailDeFactura } from '@/types/comprobantes';

/*
 * "Enviar por email": a quién, con copia o no, y el texto ya completo con los
 * datos de la factura (TextoDelEmail), que se puede retocar antes de mandarlo.
 * El PDF va adjunto: es el mismo que se descarga.
 */
const props = defineProps<{
    comprobanteId: number;
    email: EmailDeFactura;
}>();

const abierto = ref(false);
</script>

<template>
    <Button variant="outline" data-test="enviar-email" @click="abierto = true">
        <Mail aria-hidden="true" /> Enviar por email
    </Button>

    <Dialog v-model:open="abierto">
        <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Enviar por email</DialogTitle>
                <DialogDescription>
                    Va con el PDF adjunto. Si te responden, la respuesta te
                    llega a {{ props.email.propio }}.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="ComprobanteController.enviar.form(props.comprobanteId)"
                v-slot="{ errors, processing }"
                class="grid gap-4"
                @success="abierto = false"
            >
                <div class="grid gap-2">
                    <Label for="para">Para</Label>
                    <Input
                        id="para"
                        name="para"
                        type="text"
                        inputmode="email"
                        autocomplete="off"
                        :default-value="props.email.para"
                        :readonly="props.email.solo_propio"
                        required
                    />
                    <p class="text-xs text-pretty text-muted-foreground">
                        <template v-if="props.email.solo_propio">
                            Es una factura de homologación: solo te la podés
                            mandar a vos, para ver cómo llega.
                        </template>
                        <template v-else>
                            Hasta tres direcciones, separadas por coma.
                        </template>
                    </p>
                    <InputError :message="errors.para ?? errors['para.0']" />
                </div>

                <label
                    v-if="!props.email.solo_propio"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        name="copia_a_mi"
                        value="1"
                        checked
                        class="size-4 accent-primary"
                    />
                    Mandarme una copia
                </label>

                <div class="grid gap-2">
                    <Label for="texto">Mensaje</Label>
                    <textarea
                        id="texto"
                        name="texto"
                        rows="8"
                        maxlength="5000"
                        required
                        class="field-sizing-content min-h-[calc(6lh+1rem)] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-normal shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        :value="props.email.texto"
                    />
                    <p class="text-xs text-muted-foreground">
                        Sale del texto de Datos fiscales → Email de tus
                        facturas. Lo que cambies acá vale solo para este envío.
                    </p>
                    <InputError :message="errors.texto" />
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="abierto = false"
                    >
                        Cancelar
                    </Button>
                    <Button :disabled="processing" data-test="confirmar-envio">
                        {{ processing ? 'Enviando…' : 'Enviar' }}
                    </Button>
                </div>
            </Form>
        </DialogContent>
    </Dialog>
</template>
