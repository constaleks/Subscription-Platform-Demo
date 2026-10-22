import { api, ensureCsrfCookie } from './client';

import type { User } from '@/types/models';

export interface RegisterPayload {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface LoginPayload {
    email: string;
    password: string;
    remember: boolean;
}

/** Laravel's JsonResource wraps the payload in a `data` key. */
interface UserResponse {
    data: User;
}

export async function register(payload: RegisterPayload): Promise<User> {
    await ensureCsrfCookie();
    const { data } = await api.post<UserResponse>('/api/v1/auth/register', payload);
    return data.data;
}

export async function login(payload: LoginPayload): Promise<User> {
    await ensureCsrfCookie();
    const { data } = await api.post<UserResponse>('/api/v1/auth/login', payload);
    return data.data;
}

export async function logout(): Promise<void> {
    await api.post('/api/v1/auth/logout');
}

export async function fetchCurrentUser(): Promise<User> {
    const { data } = await api.get<UserResponse>('/api/v1/auth/me');
    return data.data;
}
