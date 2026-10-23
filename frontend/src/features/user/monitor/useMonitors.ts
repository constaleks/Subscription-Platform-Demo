import { useCallback, useEffect, useState } from 'react';
import { isAxiosError } from 'axios';
import * as monitorApi from '@/api/user/monitor';
import { useAuthStore } from '@/features/user/auth/authStore';
import type { Monitor } from '@/types/models';

export function useMonitors() {
    const fetchCurrentUser = useAuthStore((s) => s.fetchCurrentUser);

    const [monitors, setMonitors] = useState<Monitor[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const load = useCallback(
        (): Promise<void> =>
            monitorApi.fetchMonitors().then(
                (list) => {
                    setMonitors(list);
                    setError(null);
                    setIsLoading(false);
                },
                (err) => {
                    setError(extractErrorMessage(err));
                    setIsLoading(false);
                },
            ),
        [],
    );

    useEffect(() => {
        load();
    }, [load]);

    const reload = (): Promise<void> => {
        setIsLoading(true);
        setError(null);
        return load();
    };

    const replace = (updated: Monitor): void => {
        setMonitors((list) => list.map((m) => (m.id === updated.id ? updated : m)));
    };

    const run = async (id: number, request: () => Promise<void>): Promise<void> => {
        setBusyId(id);
        try {
            await request();
        } catch (err) {
            throw new Error(extractErrorMessage(err), { cause: err });
        } finally {
            setBusyId(null);
        }
    };

    const pause = (id: number) => run(id, async () => replace(await monitorApi.pauseMonitor(id)));

    const resume = (id: number) => run(id, async () => replace(await monitorApi.resumeMonitor(id)));

    const remove = (id: number) =>
        run(id, async () => {
            await monitorApi.deleteMonitor(id);
            setMonitors((list) => list.filter((m) => m.id !== id));
            await fetchCurrentUser();
        });

    return { monitors, isLoading, error, busyId, reload, pause, resume, remove };
}

export function extractErrorMessage(err: unknown): string {
    if (isAxiosError<{ message?: string }>(err)) {
        return err.response?.data?.message ?? 'Something went wrong. Please try again.';
    }

    return 'Something went wrong. Please try again.';
}
