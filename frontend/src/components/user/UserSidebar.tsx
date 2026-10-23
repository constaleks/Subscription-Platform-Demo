import { NavLink } from 'react-router-dom';
import { CommandIcon, LayoutDashboardIcon, LayersPlus, ChartNoAxesColumn } from 'lucide-react';
import { cn } from '@/lib/utils';
import { UserMenu } from './UserMenu';
import { Button } from '../ui/button';

const navItems = [
    { title: 'Dashboard', to: '/dashboard', icon: LayoutDashboardIcon },
    { title: 'Monitors', to: '/view', icon: ChartNoAxesColumn },
];

export function UserSidebar({ onNavigate }: { onNavigate?: () => void }) {
    return (
        <div className="flex h-full flex-col gap-4 p-3">
            <span className="flex items-center gap-2 px-2 py-1.5 font-semibold">
                <CommandIcon className="size-5" />
                Uptime Monitoring
            </span>

            <nav className="flex flex-1 flex-col gap-1">
                <Button className="justify-start cursor-pointer">
                    <NavLink className="flex items-center gap-2 w-full" to="/create">
                        <LayersPlus /> Quick create
                    </NavLink>
                </Button>
                {navItems.map(({ title, to, icon: Icon }) => (
                    <NavLink
                        key={to}
                        to={to}
                        onClick={onNavigate}
                        className={({ isActive }) =>
                            cn('flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-muted', isActive && 'bg-muted font-medium')
                        }>
                        <Icon className="size-4" />
                        {title}
                    </NavLink>
                ))}
            </nav>

            <UserMenu />
        </div>
    );
}
