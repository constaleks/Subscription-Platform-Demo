import { AuthLayout } from '@/components/layout/AuthLayout';
import { LoginForm } from '@/features/user/auth/components/LoginForm';

export function LoginPage() {
    return (
        <AuthLayout>
            <LoginForm />
        </AuthLayout>
    );
}
