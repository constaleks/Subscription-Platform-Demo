import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

import { UserLayout } from '@/components/layout/UserLayout';
import { MonitorForm } from '@/features/user/monitor/components/MonitorForm';
import { extractErrorMessage } from '@/features/user/monitor/useMonitors';
import { fetchMonitor } from '@/api/user/monitor';
import type { Monitor } from '@/types/models';
import { Button } from '@/components/ui/button';

interface LoadResult {
    id: string;
    monitor?: Monitor;
    error?: string;
}

export function EditMonitorPage() {
    const { id } = useParams();
    const [result, setResult] = useState<LoadResult | null>(null);

    useEffect(() => {
        let ignore = false;

        fetchMonitor(Number(id)).then(
            (monitor) => !ignore && setResult({ id: id!, monitor }),
            (err) => !ignore && setResult({ id: id!, error: extractErrorMessage(err) }),
        );

        return () => {
            ignore = true;
        };
    }, [id]);

    const { monitor, error }: Partial<LoadResult> = result && result.id === id ? result : {};

    return (
        <UserLayout>
            <div className="@container/main flex flex-col gap-4 md:gap-6">
                <h1 className="text-lg font-semibold">Edit monitor</h1>
                {error ? (
                    <div className="flex flex-col items-center gap-2 rounded-lg border p-6 text-center text-sm">
                        <p className="text-red-500">{error}</p>
                        <Button variant="outline" size="sm" render={<Link to="/view" />} nativeButton={false}>
                            Back to monitors
                        </Button>
                    </div>
                ) : monitor ? (
                    <MonitorForm key={monitor.id} monitor={monitor} />
                ) : (
                    <div className="rounded-lg border p-6 text-center text-sm text-muted-foreground">Loading monitor...</div>
                )}
            </div>
        </UserLayout>
    );
}
