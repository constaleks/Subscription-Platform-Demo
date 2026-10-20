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

export interface AuthResponse {
    user: User;
}

export async function register(payload: RegisterPayload): Promise<AuthResponse> {
    await ensureCsrfCookie();
    const { data } = await api.post<AuthResponse>('/api/v1/auth/register', payload);
    return data;
}

export async function login(payload: LoginPayload): Promise<AuthResponse> {
    await ensureCsrfCookie();
    const { data } = await api.post<AuthResponse>('/api/v1/auth/login', payload);
    return data;
}

export async function logout(): Promise<void> {
    await api.post('/api/v1/auth/logout');
}

export async function fetchCurrentUser(): Promise<AuthResponse> {
    const { data } = await api.get<AuthResponse>('/api/v1/auth/me');
    return data;
}
