import { create } from 'zustand';
import { isAxiosError } from 'axios';
import * as authApi from '@/api/user/auth';
import type { User } from '@/types/models';

interface AuthState {
    user: User | null;
    status: 'idle' | 'authenticated' | 'unauthenticated';
    isSubmitting: boolean;
    error: string | null;
    register: (payload: authApi.RegisterPayload) => Promise<void>;
    login: (payload: authApi.LoginPayload) => Promise<void>;
    logout: () => Promise<void>;
    fetchCurrentUser: () => Promise<void>;
    clearError: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
    user: null,
    status: 'idle',
    isSubmitting: false,
    error: null,

    register: async (payload) => {
        set({ isSubmitting: true, error: null });
        try {
            const { user } = await authApi.register(payload);
            set({ user, status: 'authenticated', isSubmitting: false });
        } catch (err) {
            set({ status: 'unauthenticated', error: extractErrorMessage(err), isSubmitting: false });
            throw err;
        }
    },

    login: async (payload) => {
        set({ isSubmitting: true, error: null });
        try {
            const { user } = await authApi.login(payload);
            set({ user, status: 'authenticated', isSubmitting: false });
        } catch (err) {
            set({ status: 'unauthenticated', error: extractErrorMessage(err), isSubmitting: false });
            throw err;
        }
    },

    logout: async () => {
        await authApi.logout();
        set({ user: null, status: 'unauthenticated' });
    },

    fetchCurrentUser: async () => {
        try {
            const { user } = await authApi.fetchCurrentUser();
            set({ user, status: 'authenticated' });
        } catch {
            set({ user: null, status: 'unauthenticated' });
        }
    },

    clearError: () => {
        set({ error: null });
    },
}));

function extractErrorMessage(err: unknown): string {
    if (isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(err)) {
        const data = err.response?.data;
        const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;
        return firstFieldError ?? data?.message ?? 'Something went wrong. Please try again.';
    }

    return 'Something went wrong. Please try again.';
}
