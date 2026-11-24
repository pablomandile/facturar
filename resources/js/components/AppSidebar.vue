<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Landmark, LayoutGrid, Mail, ReceiptText, Users } from '@lucide/vue';
import { onUnmounted } from 'vue';
import ClienteController from '@/actions/App/Http/Controllers/ClienteController';
import ComprobanteController from '@/actions/App/Http/Controllers/ComprobanteController';
import ComunicacionController from '@/actions/App/Http/Controllers/ComunicacionController';
import EmisorController from '@/actions/App/Http/Controllers/EmisorController';
import AppLogo from '@/components/AppLogo.vue';
import BotonInstalar from '@/components/BotonInstalar.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import PieLegal from '@/components/PieLegal.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/*
 * En el celular el menú es un sheet a pantalla completa, y navegar en una SPA
 * no lo desmonta: quedaba abierto tapando la pantalla nueva, con el body
 * bloqueado. Se cierra en el router (no en cada enlace, que alguno se olvida),
 * con `navigate` (la página efectivamente cambió) y solo en mobile: en
 * escritorio la barra es fija. Ver la skill overlays-al-navegar.
 */
const { isMobile, setOpenMobile } = useSidebar();

onUnmounted(
    router.on('navigate', () => {
        if (isMobile.value) {
            setOpenMobile(false);
        }
    }),
);

const mainNavItems: NavItem[] = [
    {
        title: 'Panel',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Facturas',
        href: ComprobanteController.index(),
        icon: ReceiptText,
    },
    {
        title: 'Clientes',
        href: ClienteController.index(),
        icon: Users,
    },
    {
        title: 'Comunicación',
        href: ComunicacionController.edit(),
        icon: Mail,
    },
    {
        title: 'Datos fiscales',
        href: EmisorController.edit(),
        icon: Landmark,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <!--
                En el pie del menú: aparece una sola vez y anda igual en el
                menú del celular y en escritorio. Se esconde solo si la app ya
                está instalada o el navegador no puede instalarla.
            -->
            <div class="px-2 group-data-[collapsible=icon]:hidden">
                <BotonInstalar class="w-full" />
                <PieLegal class="mt-2" />
            </div>

            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
