import { Link } from '@inertiajs/react';
import {
    Globe,
    LayoutGrid,
    LayoutTemplate,
    Settings,
    Users,
} from 'lucide-react';
import { index as templatesIndex } from '@/actions/App/Http/Controllers/Admin/TemplateController';
import { index as previewsIndex } from '@/actions/App/Http/Controllers/Admin/CustomizedPreviewController';
import { edit as settingsEdit } from '@/actions/App/Http/Controllers/Admin/SettingController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Kelola Template',
        href: templatesIndex(),
        icon: LayoutTemplate,
    },
    {
        title: 'Preview Klien',
        href: previewsIndex(),
        icon: Users,
    },
    {
        title: 'Pengaturan',
        href: settingsEdit(),
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Lihat Katalog Publik',
        href: home(),
        icon: Globe,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
