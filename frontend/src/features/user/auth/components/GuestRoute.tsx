import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '../authStore';

export function GuestRoute() {
    const status = useAuthStore((s) => s.status);

    if (status === 'authenticated') {
        return <Navigate to="/dashboard" replace />;
    }

    return <Outlet />;
}
