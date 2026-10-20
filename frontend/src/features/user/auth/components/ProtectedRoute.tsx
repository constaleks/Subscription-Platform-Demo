import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '../authStore';

export function ProtectedRoute() {
    const status = useAuthStore((s) => s.status);

    if (status === 'unauthenticated') {
        return <Navigate to="/login" replace />;
    }

    return <Outlet />;
}
