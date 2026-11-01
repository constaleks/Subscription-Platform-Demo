# Monitoring flow

How a monitor gets checked, how incidents are opened and resolved, and how emails are sent.

There are three stages. Each one runs in its own process and they communicate only through the queue and the database:

1. **Scheduler**: decides which monitors are due and dispatches check jobs.
2. **Check worker**: performs the HTTP check, stores the result, opens/resolves incidents.
3. **Notification worker**: sends the email and records that it was sent.

## Schema

cron (every minute) ──► php artisan schedule:run
│
▼
routes/console.php ──► app:dispatch-due-monitors [runs in the scheduler process]
│ for each due monitor:
│ 1. claim it with an UPDATE (moves next_check_at forward)
│ 2. push CheckMonitorJob to "checks-high" (Pro) or "checks-low" (free)
▼
queue worker ──► CheckMonitorJob::handle() [runs in a queue worker process]
│
├─► MonitorChecker::check() (Services) → does the HTTP request
│ └─► HostGuard::resolvePublic() (Support) → SSRF check
│ returns a CheckResult (Support)
│
├─► DB transaction:
│ • save a MonitorCheck row
│ • update the monitor's status / consecutive_failures
│ • if Up→Down: IncidentManager::open() (Services)
│ • if Down→Up: IncidentManager::resolve() (Services)
│
└─► $user->notify(IncidentOpened | IncidentResolved)
│ (the notifications are ShouldQueue → go to the "notifications" queue)
▼
queue worker ──► sends the email
│
▼ Laravel fires a NotificationSent event
Listeners/MarkIncidentNotified → sets down_notified_at / recovery_notified_at
