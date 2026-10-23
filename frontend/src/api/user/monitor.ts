import { api } from './client';
import type { HttpMethod, Monitor } from '@/types/models';

export interface CreateMonitorPayload {
    name: string;
    url: string;
    interval_seconds: number;
    method?: HttpMethod;
    expected_status_code?: number | null;
    timeout_seconds?: number;
    request_headers?: Record<string, string> | null;
}

export type UpdateMonitorPayload = Partial<CreateMonitorPayload>;

interface MonitorResponse {
    data: Monitor;
}

interface MonitorCollectionResponse {
    data: Monitor[];
}

export async function fetchMonitors(): Promise<Monitor[]> {
    const { data } = await api.get<MonitorCollectionResponse>('/api/v1/monitors');
    return data.data;
}

export async function fetchMonitor(id: number): Promise<Monitor> {
    const { data } = await api.get<MonitorResponse>(`/api/v1/monitors/${id}`);
    return data.data;
}

export async function createMonitor(payload: CreateMonitorPayload): Promise<Monitor> {
    const { data } = await api.post<MonitorResponse>('/api/v1/monitors', payload);
    return data.data;
}

export async function updateMonitor(id: number, payload: UpdateMonitorPayload): Promise<Monitor> {
    const { data } = await api.patch<MonitorResponse>(`/api/v1/monitors/${id}`, payload);
    return data.data;
}

export async function deleteMonitor(id: number): Promise<void> {
    await api.delete(`/api/v1/monitors/${id}`);
}

export async function pauseMonitor(id: number): Promise<Monitor> {
    const { data } = await api.post<MonitorResponse>(`/api/v1/monitors/${id}/pause`);
    return data.data;
}

export async function resumeMonitor(id: number): Promise<Monitor> {
    const { data } = await api.post<MonitorResponse>(`/api/v1/monitors/${id}/resume`);
    return data.data;
}
