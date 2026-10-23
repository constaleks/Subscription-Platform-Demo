import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { PlusIcon } from 'lucide-react';
import { toast } from 'sonner';

import { UserLayout } from '@/components/layout/UserLayout';
import { DataTable, type MonitorActions } from '@/features/user/monitor/components/DataTable';
import { useAuthStore } from '@/features/user/auth/authStore';
import { useMonitors } from '@/features/user/monitor/useMonitors';
import type { Monitor } from '@/types/models';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

export function IndexMonitorPage() {
    const navigate = useNavigate();
    const plan = useAuthStore((s) => s.user?.plan);
    const { monitors, isLoading, error, busyId, reload, pause, resume, remove } = useMonitors();
    const [monitorToDelete, setMonitorToDelete] = useState<Monitor | null>(null);

    const maxMonitors = plan?.max_monitors ?? 0;
    const activeCount = monitors.filter((m) => m.is_active).length;
    const canResume = activeCount < maxMonitors;
    const canCreate = monitors.length < maxMonitors;

    const actions = useMemo<MonitorActions>(
        () => ({
            onEdit: (monitor) => navigate(`/edit/${monitor.id}`),
            onDelete: setMonitorToDelete,
            onPause: (monitor) =>
                pause(monitor.id).then(
                    () => toast.success(`"${monitor.name}" paused`),
                    (err: Error) => toast.error(err.message),
                ),
            onResume: (monitor) =>
                resume(monitor.id).then(
                    () => toast.success(`"${monitor.name}" started`),
                    (err: Error) => toast.error(err.message),
                ),
            canResume,
            busyId,
        }),
        // pause/resume are recreated every render but only close over stable setters.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [navigate, canResume, busyId],
    );

    const confirmDelete = async (): Promise<void> => {
        if (!monitorToDelete) return;
        const { id, name } = monitorToDelete;
        setMonitorToDelete(null);
        try {
            await remove(id);
            toast.success(`"${name}" deleted`);
        } catch (err) {
            toast.error((err as Error).message);
        }
    };

    return (
        <UserLayout>
            <div className="@container/main flex flex-col gap-4 md:gap-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-lg font-semibold">Monitors</h1>
                        {plan && !isLoading && (
                            <p className="text-sm text-muted-foreground">
                                {activeCount} of {maxMonitors} active monitors on the <span className="capitalize">{plan.key}</span> plan
                            </p>
                        )}
                    </div>
                    {canCreate || isLoading ? (
                        <Button render={<Link to="/create" />} nativeButton={false} disabled={isLoading}>
                            <PlusIcon />
                            New monitor
                        </Button>
                    ) : (
                        <p className="text-sm text-muted-foreground">Monitor limit reached. Delete one to add another.</p>
                    )}
                </div>

                {error ? (
                    <div className="flex flex-col items-center gap-2 rounded-lg border p-6 text-center text-sm">
                        <p className="text-red-500">{error}</p>
                        <Button variant="outline" size="sm" onClick={reload}>
                            Try again
                        </Button>
                    </div>
                ) : isLoading ? (
                    <div className="rounded-lg border p-6 text-center text-sm text-muted-foreground">Loading monitors...</div>
                ) : (
                    <DataTable data={monitors} actions={actions} />
                )}
            </div>

            <AlertDialog open={monitorToDelete !== null} onOpenChange={(open) => !open && setMonitorToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete "{monitorToDelete?.name}"?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This permanently deletes the monitor along with its check history and incidents. This can't be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="cursor-pointer">Cancel</AlertDialogCancel>
                        <AlertDialogAction className="cursor-pointer" variant="destructive" onClick={confirmDelete}>
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </UserLayout>
    );
}
