import type { ComponentProps } from 'react';

import { UserLayout } from '@/components/layout/UserLayout';
import { DataTable } from '@/features/user/dashboard/DataTable';
import { SectionCards } from '@/features/user/dashboard/SectionCards';

const data: ComponentProps<typeof DataTable>['data'] = [];

export function DashboardPage() {
    return (
        <UserLayout>
            <div className="@container/main flex flex-col gap-4 md:gap-6">
                <h1 className="text-lg font-semibold">Dashboard</h1>
                <SectionCards /> {/* static data - placeholder */}
                <DataTable data={data} />
            </div>
        </UserLayout>
    );
}
