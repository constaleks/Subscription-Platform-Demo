import { UserLayout } from '@/components/layout/UserLayout';
import { SectionCards } from '@/features/user/dashboard/components/SectionCards';

export function DashboardPage() {
    return (
        <UserLayout>
            <div className="@container/main flex flex-col gap-4 md:gap-6">
                <h1 className="text-lg font-semibold">Dashboard</h1>
                <SectionCards /> {/* static data - placeholder */}
            </div>
        </UserLayout>
    );
}
