import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '../authStore';
import { FullPageLoader } from '@/components/user/PageLoader';

export function GuestRoute() {
    const status = useAuthStore((s) => s.status);

    if (status === 'idle') {
        return <FullPageLoader />;
    }

    if (status === 'authenticated') {
        return <Navigate to="/dashboard" replace />;
    }

    return <Outlet />;
}
