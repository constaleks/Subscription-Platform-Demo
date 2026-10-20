import { AuthLayout } from '@/components/layout/AuthLayout';
import { RegisterForm } from '@/features/user/auth/components/RegisterForm';

export function RegisterPage() {
    return (
        <AuthLayout>
            <RegisterForm />
        </AuthLayout>
    );
}
