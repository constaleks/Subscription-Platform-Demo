import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '../authStore';
import { FullPageLoader } from '@/components/user/PageLoader';

export function ProtectedRoute() {
    const status = useAuthStore((s) => s.status);

    if (status === 'idle') {
        return <FullPageLoader />;
    }

    if (status === 'unauthenticated') {
        return <Navigate to="/login" replace />;
    }

    return <Outlet />;
}
