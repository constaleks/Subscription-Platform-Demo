import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8876';

export const api = axios.create({
    baseURL: API_BASE_URL,
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
    },
});

/**
 * Sanctum requires this to be called once before any state-changing request
 * (register/login) in a fresh session. It sets the XSRF-TOKEN cookie that
 * axios will then read and send back on the next request automatically.
 */
export async function ensureCsrfCookie(): Promise<void> {
    await api.get('/sanctum/csrf-cookie');
}
