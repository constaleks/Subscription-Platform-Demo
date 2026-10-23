import { createBrowserRouter, Navigate } from 'react-router-dom';
import { ProtectedRoute } from '@/features/user/auth/components/ProtectedRoute';
import { GuestRoute } from '@/features/user/auth/components/GuestRoute';
import { LoginPage } from '@/routes/user/auth/LoginPage';
import { RegisterPage } from '@/routes/user/auth/RegisterPage';
import { DashboardPage } from '@/routes/user/dashboard/DashboardPage';
import { CreateMonitorPage } from './routes/user/monitor/CreateMonitorPage';
import { IndexMonitorPage } from './routes/user/monitor/IndexMonitorPage';
import { EditMonitorPage } from './routes/user/monitor/EditMonitorPage';

export const router = createBrowserRouter([
    { path: '/', element: <Navigate to="/dashboard" replace /> },
    {
        element: <GuestRoute />,
        children: [
            { path: '/login', element: <LoginPage /> },
            { path: '/register', element: <RegisterPage /> },
        ],
    },
    {
        element: <ProtectedRoute />,
        children: [
            { path: '/dashboard', element: <DashboardPage /> },
            { path: '/create', element: <CreateMonitorPage /> },
            { path: '/view', element: <IndexMonitorPage /> },
            { path: '/edit/:id', element: <EditMonitorPage /> },
        ],
    },
]);
