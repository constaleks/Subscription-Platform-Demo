import { UserLayout } from '@/components/layout/UserLayout';
import { MonitorForm } from '@/features/user/monitor/components/MonitorForm';

export function CreateMonitorPage() {
    return (
        <UserLayout>
            <div className="@container/main flex flex-col gap-4 md:gap-6">
                <h1 className="text-lg font-semibold">Create new monitor</h1>
                <MonitorForm />
            </div>
        </UserLayout>
    );
}
