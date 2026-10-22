import { EllipsisVerticalIcon, LogOutIcon } from 'lucide-react';
import { useAuthStore } from '@/features/user/auth/authStore';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function UserMenu() {
    const user = useAuthStore((s) => s.user);
    const logout = useAuthStore((s) => s.logout);

    if (!user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="flex w-full cursor-pointer items-center gap-2 rounded-lg p-2 text-left text-sm hover:bg-muted">
                <div className="grid flex-1 leading-tight">
                    <span className="truncate font-medium">{user.name}</span>
                    <span className="truncate text-xs text-muted-foreground">{user.email}</span>
                </div>
                <EllipsisVerticalIcon className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent className="min-w-56" side="top" align="end">
                <DropdownMenuItem onClick={logout} className="cursor-pointer">
                    <LogOutIcon />
                    Log out
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
