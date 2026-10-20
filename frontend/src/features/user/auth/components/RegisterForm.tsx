import { useEffect, useState, type ChangeEvent, type SubmitEvent } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuthStore } from '../authStore';

import { cn } from 'cn';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

export function RegisterForm({ className, ...props }: React.ComponentProps<'div'>) {
    const navigate = useNavigate();
    const register = useAuthStore((s) => s.register);
    const storeError = useAuthStore((s) => s.error);
    const isSubmitting = useAuthStore((s) => s.isSubmitting);
    const clearError = useAuthStore((s) => s.clearError);

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [localError, setLocalError] = useState<string | null>(null);

    useEffect(() => {
        clearError();
    }, [clearError]);

    const handleChange = (e: ChangeEvent<HTMLInputElement>): void => {
        if (e.target.id === 'name') {
            setName(e.target.value);
        } else if (e.target.id === 'email') {
            setEmail(e.target.value);
        } else if (e.target.id === 'password') {
            setPassword(e.target.value);
        } else if (e.target.id === 'confirm-password') {
            setPasswordConfirmation(e.target.value);
        }
    };

    const handleSubmit = async (e: SubmitEvent<HTMLFormElement>): Promise<void> => {
        e.preventDefault();
        setLocalError(null);

        const FULL_NAME_PATTERN = /^\p{L}+(?:['-]\p{L}+)*(?:\s+\p{L}+(?:['-]\p{L}+)*)+$/u;
        if (!FULL_NAME_PATTERN.test(name.trim())) {
            setLocalError('Please enter your full name (first and last name).');
            return;
        }

        const PASSWORD_PATTERN = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
        if (!PASSWORD_PATTERN.test(password)) {
            setLocalError('Password must be at least 8 characters and include an uppercase letter, a lowercase letter, and a number.');
            return;
        }

        if (password !== passwordConfirmation) {
            setLocalError('Passwords do not match.');
            return;
        }

        try {
            await register({
                name,
                email,
                password,
                password_confirmation: passwordConfirmation,
            });
            navigate('/dashboard');
        } catch (err) {
            console.warn(err);
        }
    };

    const errorMessage = localError ?? storeError;

    return (
        <div className={cn('flex flex-col gap-6', className)} {...props}>
            <Card>
                <CardHeader className="text-center">
                    <CardTitle className="text-xl">Create your account</CardTitle>
                    <CardDescription>Enter your email below to create your account</CardDescription>
                </CardHeader>
                <CardContent>
                    <form method="post" onSubmit={handleSubmit}>
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="name">Full Name</FieldLabel>
                                <Input id="name" type="text" placeholder="John Doe" value={name} onChange={handleChange} required />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="email">Email</FieldLabel>
                                <Input id="email" type="email" placeholder="m@example.com" value={email} onChange={handleChange} required />
                            </Field>
                            <Field>
                                <Field className="grid grid-cols-2 gap-4">
                                    <Field>
                                        <FieldLabel htmlFor="password">Password</FieldLabel>
                                        <Input id="password" type="password" value={password} onChange={handleChange} required />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="confirm-password">Confirm Password</FieldLabel>
                                        <Input
                                            id="confirm-password"
                                            type="password"
                                            value={passwordConfirmation}
                                            onChange={handleChange}
                                            required
                                        />
                                    </Field>
                                </Field>
                                {errorMessage && <FieldDescription className="text-red-600 text-center">{errorMessage}</FieldDescription>}
                            </Field>
                            <Field>
                                <Button type="submit" className="cursor-pointer" disabled={isSubmitting}>
                                    {isSubmitting ? 'Creating account...' : 'Create Account'}
                                </Button>
                                <FieldDescription className="text-center">
                                    Already have an account? <Link to="/login">Sign in</Link>
                                </FieldDescription>
                            </Field>
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>
            <FieldDescription className="px-6 text-center">
                By clicking continue, you agree to our <span className="underline cursor-pointer">Terms of Service</span> and{' '}
                <span className="underline cursor-pointer">Privacy Policy</span>.
            </FieldDescription>
        </div>
    );
}
