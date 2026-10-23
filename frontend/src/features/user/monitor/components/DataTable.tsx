import { useMemo, useState } from 'react';
import {
    createColumnHelper,
    createPaginatedRowModel,
    createSortedRowModel,
    FlexRender,
    rowPaginationFeature,
    rowSortingFeature,
    tableFeatures,
    useTable,
    type SortingState,
} from '@tanstack/react-table';
import { ChevronLeftIcon, ChevronRightIcon, EllipsisVerticalIcon, PauseIcon, PencilIcon, PlayIcon, Trash2Icon } from 'lucide-react';

import type { Monitor, MonitorStatus } from '@/types/models';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

const features = tableFeatures({
    rowPaginationFeature,
    rowSortingFeature,
    paginatedRowModel: createPaginatedRowModel(),
    sortedRowModel: createSortedRowModel(),
});

const columnHelper = createColumnHelper<typeof features, Monitor>();

const statusStyles: Record<MonitorStatus, string> = {
    up: 'bg-green-500',
    down: 'bg-red-500',
    pending: 'bg-muted-foreground',
};

export interface MonitorActions {
    onEdit: (monitor: Monitor) => void;
    onDelete: (monitor: Monitor) => void;
    onPause: (monitor: Monitor) => void;
    onResume: (monitor: Monitor) => void;
    canResume: boolean;
    busyId: number | null;
}

const createColumns = (actions: MonitorActions) =>
    columnHelper.columns([
        columnHelper.accessor('name', {
            header: 'Name',
            cell: ({ row }) => <span className="font-medium">{row.original.name}</span>,
        }),
        columnHelper.accessor('url', {
            header: 'URL',
            cell: ({ row }) => (
                <a
                    href={row.original.url}
                    target="_blank"
                    rel="noreferrer"
                    className="block max-w-64 truncate text-muted-foreground hover:underline">
                    {row.original.url}
                </a>
            ),
        }),
        columnHelper.accessor('status', {
            header: 'Status',
            cell: ({ row }) => {
                const { status, consecutive_failures } = row.original;
                return (
                    <Badge variant="outline" className="gap-1.5 px-1.5 capitalize">
                        <span className={`size-2 rounded-full ${statusStyles[status]}`} />
                        {status}
                        {status === 'down' && consecutive_failures > 1 && (
                            <span className="text-muted-foreground">×{consecutive_failures}</span>
                        )}
                    </Badge>
                );
            },
        }),
        columnHelper.accessor('is_active', {
            header: 'Active',
            cell: ({ row }) => {
                const { is_active, paused_reason } = row.original;
                if (is_active) {
                    return 'Active';
                }
                return <span className="text-muted-foreground">{paused_reason === 'plan_limit' ? 'Paused (plan limit)' : 'Paused'}</span>;
            },
        }),
        columnHelper.accessor('interval_seconds', {
            header: 'Interval',
            cell: ({ row }) => formatInterval(row.original.interval_seconds),
        }),
        columnHelper.accessor('last_checked_at', {
            header: 'Last check',
            cell: ({ row }) => <DateCell value={row.original.last_checked_at} fallback="Never" />,
        }),
        columnHelper.accessor('next_check_at', {
            header: 'Next check',
            cell: ({ row }) => (row.original.is_active ? <DateCell value={row.original.next_check_at} /> : '—'),
        }),
        columnHelper.display({
            id: 'actions',
            cell: ({ row }) => {
                const monitor = row.original;
                const isBusy = actions.busyId === monitor.id;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            className="cursor-pointer"
                            disabled={isBusy}
                            render={
                                <Button variant="ghost" className="flex size-8 text-muted-foreground data-open:bg-muted" size="icon" />
                            }>
                            <EllipsisVerticalIcon />
                            <span className="sr-only">Open menu</span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            {monitor.is_active ? (
                                <DropdownMenuItem className="cursor-pointer" onClick={() => actions.onPause(monitor)}>
                                    <PauseIcon />
                                    Pause
                                </DropdownMenuItem>
                            ) : (
                                <DropdownMenuItem
                                    className="cursor-pointer"
                                    disabled={!actions.canResume}
                                    onClick={() => actions.onResume(monitor)}>
                                    <PlayIcon />
                                    {actions.canResume ? 'Start' : 'Start (plan limit reached)'}
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem className="cursor-pointer" onClick={() => actions.onEdit(monitor)}>
                                <PencilIcon />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="cursor-pointer" variant="destructive" onClick={() => actions.onDelete(monitor)}>
                                <Trash2Icon />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        }),
    ]);

export function DataTable({ data, actions }: { data: Monitor[]; actions: MonitorActions }) {
    const columns = useMemo(() => createColumns(actions), [actions]);
    const [sorting, setSorting] = useState<SortingState>([]);
    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 });

    const table = useTable({
        features,
        data,
        columns,
        state: { sorting, pagination },
        getRowId: (row) => row.id.toString(),
        onSortingChange: setSorting,
        onPaginationChange: setPagination,
    });

    return (
        <div className="flex flex-col gap-4">
            <div className="overflow-hidden rounded-lg border">
                <Table>
                    <TableHeader className="bg-muted">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id} colSpan={header.colSpan}>
                                        {header.isPlaceholder ? null : <FlexRender header={header} />}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getAllCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            <FlexRender cell={cell} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-24 text-center text-muted-foreground">
                                    No monitors yet.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {table.getPageCount() > 1 && (
                <div className="flex items-center justify-end gap-2 text-sm font-medium">
                    Page {table.state.pagination.pageIndex + 1} of {table.getPageCount()}
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage()}>
                        <span className="sr-only">Go to previous page</span>
                        <ChevronLeftIcon />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage()}>
                        <span className="sr-only">Go to next page</span>
                        <ChevronRightIcon />
                    </Button>
                </div>
            )}
        </div>
    );
}

function DateCell({ value, fallback = '—' }: { value: string | null; fallback?: string }) {
    if (!value) {
        return <span className="text-muted-foreground">{fallback}</span>;
    }

    const date = new Date(value);
    return (
        <time dateTime={value} title={date.toLocaleString()}>
            {formatRelative(date)}
        </time>
    );
}

const relativeFormatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

function formatRelative(date: Date): string {
    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 60) return relativeFormatter.format(seconds, 'second');
    if (abs < 3600) return relativeFormatter.format(Math.round(seconds / 60), 'minute');
    if (abs < 86400) return relativeFormatter.format(Math.round(seconds / 3600), 'hour');
    return relativeFormatter.format(Math.round(seconds / 86400), 'day');
}

function formatInterval(seconds: number): string {
    if (seconds % 3600 === 0) return `${seconds / 3600}h`;
    if (seconds % 60 === 0) return `${seconds / 60}m`;
    return `${seconds}s`;
}
