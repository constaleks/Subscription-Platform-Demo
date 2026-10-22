export type PlanKey = 'free' | 'pro';

export interface Plan {
    key: PlanKey;
    max_monitors: number;
    allowed_intervals: Array<number>;
    retention_days: number;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    plan: Plan;
    monitors_count: number;
}

export type MonitorStatus = 'pending' | 'up' | 'down';
export type HttpMethod = 'GET' | 'HEAD';
export type PauseReason = 'user' | 'plan_limit';

export interface Monitor {
    id: number;
    name: string;
    url: string;
    method: HttpMethod;
    expected_status_code: number | null;
    request_header_names: string[];
    interval_seconds: number;
    timeout_seconds: number;
    is_active: boolean;
    paused_reason: PauseReason | null;
    status: MonitorStatus;
    consecutive_failures: number;
    last_checked_at: string | null;
    next_check_at: string;
    created_at: string;
}
