import { useAuthStore } from '@/features/user/auth/authStore';

export function DashboardPage() {
    const logout = useAuthStore((s) => s.logout);

    return (
        <div>
            <h1>Dashboard</h1>
            <button onClick={logout}>Logout</button>
        </div>
    );
}
