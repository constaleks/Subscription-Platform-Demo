import { Loader2Icon } from 'lucide-react';

export function FullPageLoader() {
    return (
        <div className="flex min-h-svh items-center justify-center" role="status" aria-label="Loading">
            <Loader2Icon className="size-8 animate-spin text-muted-foreground" />
        </div>
    );
}
