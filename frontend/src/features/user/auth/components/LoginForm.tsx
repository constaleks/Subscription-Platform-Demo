import { useEffect, useState, type ChangeEvent, type SubmitEvent } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuthStore } from '../authStore';

import { cn } from 'cn';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';

export function LoginForm({ className, ...props }: React.ComponentProps<'div'>) {
    const navigate = useNavigate();
    const login = useAuthStore((s) => s.login);
    const error = useAuthStore((s) => s.error);
    const isSubmitting = useAuthStore((s) => s.isSubmitting);
    const clearError = useAuthStore((s) => s.clearError);

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);

    useEffect(() => {
        clearError();
    }, [clearError]);

    const handleChange = (e: ChangeEvent<HTMLInputElement>): void => {
        if (e.target.id === 'email') {
            setEmail(e.target.value);
        } else if (e.target.id === 'password') {
            setPassword(e.target.value);
        }
    };

    const handleRememberChange = (checked: boolean): void => {
        setRemember(checked);
    };

    const handleSubmit = async (e: SubmitEvent<HTMLFormElement>): Promise<void> => {
        e.preventDefault();

        try {
            await login({ email, password, remember });
            navigate('/dashboard');
        } catch (err) {
            console.warn(err);
        }
    };

    return (
        <div className={cn('flex flex-col gap-6', className)} {...props}>
            <Card>
                <CardHeader className="text-center">
                    <CardTitle className="text-xl">Welcome back</CardTitle>
                    <CardDescription>Login with your credentials</CardDescription>
                </CardHeader>
                <CardContent>
                    <form method="post" onSubmit={handleSubmit}>
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="email">Email</FieldLabel>
                                <Input id="email" type="email" placeholder="m@example.com" value={email} onChange={handleChange} required />
                            </Field>
                            <Field>
                                <div className="flex items-center">
                                    <FieldLabel htmlFor="password">Password</FieldLabel>
                                    {/*  
                                    <a href="#" className="ml-auto text-sm underline-offset-4 hover:underline">
                                        Forgot your password?
                                    </a>
                                    */}
                                </div>
                                <Input id="password" type="password" value={password} onChange={handleChange} required />
                            </Field>
                            <Field orientation="horizontal">
                                <Checkbox id="remember" name="remember" checked={remember} onCheckedChange={handleRememberChange} />
                                <FieldLabel htmlFor="remember">Remember me</FieldLabel>
                            </Field>
                            {error && <FieldDescription className="text-red-500 text-center">{error}</FieldDescription>}
                            <Field>
                                <Button type="submit" className="cursor-pointer" disabled={isSubmitting}>
                                    {isSubmitting ? 'Logging in...' : 'Login'}
                                </Button>
                                <FieldDescription className="text-center">
                                    Don't have an account? <Link to="/register">Sign up</Link>
                                </FieldDescription>
                            </Field>
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>
            <FieldDescription className="px-6 text-center">
                By clicking login, you agree to our <span className="underline cursor-pointer">Terms of Service</span> and{' '}
                <span className="underline cursor-pointer">Privacy Policy</span>.
            </FieldDescription>
        </div>
    );
}
