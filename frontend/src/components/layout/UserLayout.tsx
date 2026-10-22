import { useState } from 'react';
import type { ReactNode } from 'react';

import { MenuIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { UserSidebar } from '../user/UserSidebar';

export function UserLayout({ children }: { children: ReactNode }) {
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <div className="flex min-h-svh">
            <aside className="sticky top-0 hidden h-svh w-64 shrink-0 border-r md:block">
                <UserSidebar />
            </aside>

            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                <SheetContent side="left" className="w-64 p-0">
                    <SheetTitle className="sr-only">Navigation</SheetTitle>
                    <UserSidebar onNavigate={() => setMobileOpen(false)} />
                </SheetContent>
            </Sheet>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-12 items-center border-b px-4 md:hidden">
                    <Button variant="ghost" size="icon" onClick={() => setMobileOpen(true)} aria-label="Open navigation">
                        <MenuIcon />
                    </Button>
                </header>

                <main className="flex-1 p-4 lg:p-6">{children}</main>
            </div>
        </div>
    );
}
