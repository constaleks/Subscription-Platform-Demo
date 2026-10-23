import { useState, type ChangeEvent, type SubmitEvent } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { isAxiosError } from 'axios';
import { PlusIcon, Trash2Icon } from 'lucide-react';
import { useAuthStore } from '@/features/user/auth/authStore';
import { toast } from 'sonner';
import { createMonitor, updateMonitor, type CreateMonitorPayload } from '@/api/user/monitor';
import type { HttpMethod, Monitor } from '@/types/models';

import { cn } from 'cn';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

const HTTP_METHODS: HttpMethod[] = ['GET', 'HEAD'];
const MAX_HEADERS = 10;
const DEFAULT_TIMEOUT_SECONDS = 30;

const selectClassName =
    'h-8 w-full min-w-0 rounded-lg border border-input bg-transparent px-2.5 py-1 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30';

interface HeaderRow {
    name: string;
    value: string;
}

type FieldErrors = Record<string, string>;

interface MonitorFormProps extends React.ComponentProps<'div'> {
    /** When given, the form edits this monitor instead of creating a new one. */
    monitor?: Monitor;
}

export function MonitorForm({ monitor, className, ...props }: MonitorFormProps) {
    const navigate = useNavigate();
    const plan = useAuthStore((s) => s.user?.plan);
    const fetchCurrentUser = useAuthStore((s) => s.fetchCurrentUser);
    const intervals = plan?.allowed_intervals ?? [];
    const isEditing = monitor !== undefined;
    // A monitor created on a higher plan may use an interval the current plan no longer allows.
    const isIntervalAllowed = !monitor || intervals.includes(monitor.interval_seconds);

    const [name, setName] = useState(monitor?.name ?? '');
    const [url, setUrl] = useState(monitor?.url ?? '');
    const [intervalSeconds, setIntervalSeconds] = useState<number | ''>(
        monitor && isIntervalAllowed ? monitor.interval_seconds : (intervals[0] ?? ''),
    );
    const [method, setMethod] = useState<HttpMethod>(monitor?.method ?? 'GET');
    const [expectedStatusCode, setExpectedStatusCode] = useState(monitor?.expected_status_code?.toString() ?? '');
    const [timeoutSeconds, setTimeoutSeconds] = useState(String(monitor?.timeout_seconds ?? DEFAULT_TIMEOUT_SECONDS));
    // The API never returns header values, only names, so existing headers come back with empty values.
    const [headers, setHeaders] = useState<HeaderRow[]>(monitor?.request_header_names.map((n) => ({ name: n, value: '' })) ?? []);
    // When editing, headers are only sent if the user touched them, so the stored values are kept otherwise.
    const [headersChanged, setHeadersChanged] = useState(!isEditing);

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

    const handleChange = (e: ChangeEvent<HTMLInputElement>): void => {
        if (e.target.id === 'name') {
            setName(e.target.value);
        } else if (e.target.id === 'url') {
            setUrl(e.target.value);
        } else if (e.target.id === 'expected_status_code') {
            setExpectedStatusCode(e.target.value);
        } else if (e.target.id === 'timeout_seconds') {
            setTimeoutSeconds(e.target.value);
        }
    };

    const updateHeader = (index: number, key: keyof HeaderRow, value: string): void => {
        setHeadersChanged(true);
        setHeaders((rows) => rows.map((row, i) => (i === index ? { ...row, [key]: value } : row)));
    };

    const addHeader = (): void => {
        setHeadersChanged(true);
        setHeaders((rows) => [...rows, { name: '', value: '' }]);
    };

    const removeHeader = (index: number): void => {
        setHeadersChanged(true);
        setHeaders((rows) => rows.filter((_, i) => i !== index));
    };

    const buildPayload = (): CreateMonitorPayload => {
        const filledHeaders = headers.filter((h) => h.name.trim() !== '');

        const payload: CreateMonitorPayload = {
            name: name.trim(),
            url: url.trim(),
            interval_seconds: Number(intervalSeconds),
            method,
            expected_status_code: expectedStatusCode === '' ? null : Number(expectedStatusCode),
            timeout_seconds: Number(timeoutSeconds),
        };

        if (headersChanged) {
            payload.request_headers = filledHeaders.length ? Object.fromEntries(filledHeaders.map((h) => [h.name.trim(), h.value])) : null;
        }

        return payload;
    };

    const handleSubmit = async (e: SubmitEvent<HTMLFormElement>): Promise<void> => {
        e.preventDefault();
        setIsSubmitting(true);
        setError(null);
        setFieldErrors({});

        try {
            if (monitor) {
                await updateMonitor(monitor.id, buildPayload());
                toast.success('Monitor updated');
            } else {
                await createMonitor(buildPayload());
                // Refresh monitors_count used for the plan limit.
                await fetchCurrentUser();
                toast.success('Monitor created');
            }
            navigate('/view');
        } catch (err) {
            const { message, errors } = extractErrors(err);
            setError(message);
            setFieldErrors(errors);
        } finally {
            setIsSubmitting(false);
        }
    };

    const headersError = Object.entries(fieldErrors).find(([key]) => key.startsWith('request_headers'))?.[1];

    return (
        <div className={cn('flex flex-col gap-6', className)} {...props}>
            <Card>
                <CardHeader>
                    <CardTitle className="text-xl">{isEditing ? monitor.name : 'New monitor'}</CardTitle>
                    <CardDescription>We'll check this URL on the interval you choose and alert you when it goes down.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form method="post" onSubmit={handleSubmit}>
                        <FieldGroup>
                            <Field data-invalid={!!fieldErrors.name}>
                                <FieldLabel htmlFor="name">Name</FieldLabel>
                                <Input
                                    id="name"
                                    placeholder="My website"
                                    maxLength={255}
                                    value={name}
                                    onChange={handleChange}
                                    aria-invalid={!!fieldErrors.name}
                                    required
                                />
                                <FieldError>{fieldErrors.name}</FieldError>
                            </Field>

                            <Field data-invalid={!!fieldErrors.url}>
                                <FieldLabel htmlFor="url">URL</FieldLabel>
                                <Input
                                    id="url"
                                    type="url"
                                    placeholder="https://example.com"
                                    maxLength={2048}
                                    value={url}
                                    onChange={handleChange}
                                    aria-invalid={!!fieldErrors.url}
                                    required
                                />
                                <FieldError>{fieldErrors.url}</FieldError>
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field data-invalid={!!fieldErrors.method}>
                                    <FieldLabel htmlFor="method">Method</FieldLabel>
                                    <select
                                        id="method"
                                        className={selectClassName}
                                        value={method}
                                        onChange={(e) => setMethod(e.target.value as HttpMethod)}
                                        aria-invalid={!!fieldErrors.method}>
                                        {HTTP_METHODS.map((m) => (
                                            <option key={m} value={m}>
                                                {m}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError>{fieldErrors.method}</FieldError>
                                </Field>

                                <Field data-invalid={!!fieldErrors.interval_seconds}>
                                    <FieldLabel htmlFor="interval_seconds">Check interval</FieldLabel>
                                    <select
                                        id="interval_seconds"
                                        className={selectClassName}
                                        value={intervalSeconds}
                                        onChange={(e) => setIntervalSeconds(Number(e.target.value))}
                                        aria-invalid={!!fieldErrors.interval_seconds}
                                        required>
                                        {intervals.map((seconds) => (
                                            <option key={seconds} value={seconds}>
                                                {formatInterval(seconds)}
                                            </option>
                                        ))}
                                    </select>
                                    {!isIntervalAllowed && (
                                        <FieldDescription>
                                            The current interval ({formatInterval(monitor.interval_seconds).toLowerCase()}) isn't available on your
                                            plan. Pick a new one to save changes.
                                        </FieldDescription>
                                    )}
                                    <FieldError>{fieldErrors.interval_seconds}</FieldError>
                                </Field>

                                <Field data-invalid={!!fieldErrors.expected_status_code}>
                                    <FieldLabel htmlFor="expected_status_code">Expected status code</FieldLabel>
                                    <Input
                                        id="expected_status_code"
                                        type="number"
                                        min={100}
                                        max={599}
                                        placeholder="Any 2xx"
                                        value={expectedStatusCode}
                                        onChange={handleChange}
                                        aria-invalid={!!fieldErrors.expected_status_code}
                                    />
                                    <FieldError>{fieldErrors.expected_status_code}</FieldError>
                                </Field>

                                <Field data-invalid={!!fieldErrors.timeout_seconds}>
                                    <FieldLabel htmlFor="timeout_seconds">Timeout (seconds)</FieldLabel>
                                    <Input
                                        id="timeout_seconds"
                                        type="number"
                                        min={1}
                                        max={60}
                                        value={timeoutSeconds}
                                        onChange={handleChange}
                                        aria-invalid={!!fieldErrors.timeout_seconds}
                                        required
                                    />
                                    <FieldError>{fieldErrors.timeout_seconds}</FieldError>
                                </Field>
                            </div>

                            <Field data-invalid={!!headersError}>
                                <FieldLabel>Request headers</FieldLabel>
                                <FieldDescription>
                                    Optional. Up to {MAX_HEADERS} headers; names may only contain letters, digits and dashes.
                                    {isEditing &&
                                        monitor.request_header_names.length > 0 &&
                                        ' Saved values are hidden. Changing any header replaces all of them, so re-enter every value.'}
                                </FieldDescription>
                                {headers.map((header, index) => (
                                    <div key={index} className="flex gap-2">
                                        <Input
                                            placeholder="Header-Name"
                                            aria-label={`Header ${index + 1} name`}
                                            pattern="[A-Za-z0-9\-]{1,64}"
                                            maxLength={64}
                                            value={header.name}
                                            onChange={(e) => updateHeader(index, 'name', e.target.value)}
                                        />
                                        <Input
                                            placeholder={headersChanged ? 'Value' : 'Saved value (hidden)'}
                                            aria-label={`Header ${index + 1} value`}
                                            maxLength={1000}
                                            value={header.value}
                                            onChange={(e) => updateHeader(index, 'value', e.target.value)}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="cursor-pointer shrink-0"
                                            aria-label={`Remove header ${index + 1}`}
                                            onClick={() => removeHeader(index)}>
                                            <Trash2Icon />
                                        </Button>
                                    </div>
                                ))}
                                {headers.length < MAX_HEADERS && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="cursor-pointer self-start"
                                        onClick={addHeader}>
                                        <PlusIcon />
                                        Add header
                                    </Button>
                                )}
                                <FieldError>{headersError}</FieldError>
                            </Field>

                            {error && <FieldDescription className="text-red-500">{error}</FieldDescription>}

                            <Field orientation="horizontal">
                                <Button type="submit" className="cursor-pointer" disabled={isSubmitting || intervals.length === 0}>
                                    {isEditing ? (isSubmitting ? 'Saving...' : 'Save changes') : isSubmitting ? 'Creating...' : 'Create monitor'}
                                </Button>
                                <Button variant="ghost" className="cursor-pointer" render={<Link to="/view" />} nativeButton={false}>
                                    Cancel
                                </Button>
                            </Field>
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

function formatInterval(seconds: number): string {
    if (seconds < 60) return `Every ${seconds} seconds`;
    const minutes = seconds / 60;
    if (minutes < 60) return minutes === 1 ? 'Every minute' : `Every ${minutes} minutes`;
    const hours = minutes / 60;
    return hours === 1 ? 'Every hour' : `Every ${hours} hours`;
}

function extractErrors(err: unknown): { message: string | null; errors: FieldErrors } {
    if (isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(err)) {
        const data = err.response?.data;

        if (data?.errors) {
            const errors = Object.fromEntries(Object.entries(data.errors).map(([key, messages]) => [key, messages[0]]));
            return { message: null, errors };
        }

        return { message: data?.message ?? 'Something went wrong. Please try again.', errors: {} };
    }

    return { message: 'Something went wrong. Please try again.', errors: {} };
}
