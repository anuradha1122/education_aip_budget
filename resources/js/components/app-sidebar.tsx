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
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Landmark,
    LayoutGrid,
} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { permissions } = usePage<SharedData>().props;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            url: '/dashboard',
            icon: LayoutGrid,
        },
    ];

    if (permissions?.manage_user_accounts) {
        mainNavItems.push({ title: 'Users', url: '/user-management', icon: Building2 });
    }

    if (permissions?.manage_organisational_data) {
        mainNavItems.push(
            
            {
                title: 'Offices',
                url: '/offices',
                icon: Building2,
            },
            {
                title: 'Funds',
                url: '/funds',
                icon: Landmark,
            },
            {
                title: 'Votes',
                url: '/votes',
                icon: Landmark,
            },
            {
                title: 'Subjects',
                url: '/subjects',
                icon: Landmark,
            },
        );

        mainNavItems.push({
            title: 'Designations',
            url: '/designations',
            icon: Building2,
        });

        mainNavItems.push({
            title: 'Officers',
            url: '/officers',
            icon: Building2,
        });

        mainNavItems.push({
            title: 'Programme types',
            url: '/programme-types',
            icon: Building2,
        });

    }

    if (permissions?.manage_financial_years) {
        mainNavItems.push({
            title: 'Financial years',
            url: '/financial-years',
            icon: Landmark,
        });
    }

    if (permissions?.view_aip_register) {
        mainNavItems.push({
            title: 'AIP activities',
            url: '/aip-activities',
            icon: LayoutGrid,
        });
    }

    if (permissions?.manage_organisational_data) {
        mainNavItems.push({
            title: 'Expense Categories',
            url: '/expense-categories',
            icon: LayoutGrid,
        });
    }

    if (permissions?.manage_organisational_data) {
        mainNavItems.push({
            title: 'Officer–subject assignments',
            url: '/officer-subject-assignments',
            icon: Building2,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
