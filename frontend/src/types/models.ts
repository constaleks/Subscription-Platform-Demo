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
