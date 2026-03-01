<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { type NavGroup, type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowRightLeft,
    BarChart3,
    Building2,
    Cable,
    Calendar,
    CircleHelp,
    ClipboardCheck,
    Download,
    FileBarChart,
    HardDrive,
    History,
    LayoutDashboard,
    LayoutGrid,
    Link2,
    Mail,
    Package,
    Server,
    Settings,
    SlidersHorizontal,
    Upload,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { can, hasAnyRole } = usePermissions();

type NavItemWithPermissions = NavItem & { permission?: string; roles?: string[] };

// Helper to filter items based on permissions
const filterByPermissions = (items: NavItemWithPermissions[]): NavItem[] => {
    return items.filter((item) => {
        if (item.roles && item.roles.length > 0) {
            return hasAnyRole(item.roles);
        }
        if (!item.permission) {
            return true;
        }
        return can(item.permission);
    });
};

// Define navigation groups with their items
const navGroups = computed<NavGroup[]>(() => {
    const groups: { label: string; items: NavItemWithPermissions[] }[] = [
        {
            label: 'Overview',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                },
            ],
        },
        {
            label: 'Infrastructure',
            items: [
                {
                    title: 'Datacenters',
                    href: '/datacenters',
                    icon: Building2,
                },
                {
                    title: 'Devices',
                    href: '/devices',
                    icon: HardDrive,
                },
                {
                    title: 'Racks',
                    href: '/racks',
                    icon: Server,
                },
                {
                    title: 'Connections',
                    href: '/connections/diagram/page',
                    icon: Cable,
                },
                {
                    title: 'Device Types',
                    href: '/device-types',
                    icon: Settings,
                },
                {
                    title: 'Equipment Moves',
                    href: '/equipment-moves',
                    icon: ArrowRightLeft,
                },
            ],
        },
        {
            label: 'Audits',
            items: [
                {
                    title: 'Audits',
                    href: '/audits',
                    icon: ClipboardCheck,
                },
                {
                    title: 'Audit Dashboard',
                    href: '/audits/dashboard',
                    icon: LayoutDashboard,
                    roles: ['Administrator', 'IT Manager', 'Auditor'],
                },
                {
                    title: 'Findings',
                    href: '/findings',
                    icon: AlertCircle,
                },
                {
                    title: 'Discrepancies',
                    href: '/discrepancies',
                    icon: AlertTriangle,
                },
            ],
        },
        {
            label: 'Reports',
            items: [
                {
                    title: 'Audit Reports',
                    href: '/reports',
                    icon: FileBarChart,
                    roles: ['Administrator', 'IT Manager', 'Auditor'],
                },
                {
                    title: 'Capacity Reports',
                    href: '/capacity-reports',
                    icon: BarChart3,
                },
                {
                    title: 'Asset Reports',
                    href: '/reports/assets',
                    icon: Package,
                },
                {
                    title: 'Connection Reports',
                    href: '/connection-reports',
                    icon: Link2,
                },
                {
                    title: 'Audit History',
                    href: '/reports/audit-history',
                    icon: History,
                },
                {
                    title: 'Custom Reports',
                    href: '/custom-reports',
                    icon: SlidersHorizontal,
                    roles: ['Administrator', 'IT Manager', 'Auditor'],
                },
                {
                    title: 'Distribution Lists',
                    href: '/distribution-lists',
                    icon: Mail,
                    permission: 'distribution-lists.view',
                },
                {
                    title: 'Scheduled Reports',
                    href: '/report-schedules',
                    icon: Calendar,
                    permission: 'scheduled-reports.view',
                },
            ],
        },
        {
            label: 'Administration',
            items: [
                {
                    title: 'Users',
                    href: '/users',
                    icon: Users,
                    permission: 'users.view',
                },
                {
                    title: 'Activity Logs',
                    href: '/activity-logs',
                    icon: Activity,
                },
                {
                    title: 'Imports',
                    href: '/imports',
                    icon: Upload,
                    roles: ['Administrator', 'IT Manager'],
                },
                {
                    title: 'Exports',
                    href: '/exports',
                    icon: Download,
                    roles: ['Administrator', 'IT Manager'],
                },
            ],
        },
        {
            label: 'Support',
            items: [
                {
                    title: 'Help Center',
                    href: '/help',
                    icon: CircleHelp,
                },
            ],
        },
    ];

    // Filter items by permissions and remove empty groups
    return groups
        .map((group) => ({
            label: group.label,
            items: filterByPermissions(group.items),
        }))
        .filter((group) => group.items.length > 0);
});
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
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
