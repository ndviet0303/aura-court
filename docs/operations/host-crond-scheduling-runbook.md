# Host Crond Scheduling and Sync Queue Operating Runbook

**Document Version:** 1.0.0  
**Phase:** Runtime Execution & Topology Hardening  
**Capability:** `runtime-execution`  
**Target Architecture:** Single-Host `crond` + Synchronous In-Process Queue Execution (`court-server`)

---

## 1. Overview & Architecture

Aura Court uses a worker-free, host-triggered operating topology:
1. **Job Execution (`QUEUE_CONNECTION=sync`):** Application jobs and side-effect dispatches execute synchronously within the calling HTTP or CLI process. Persistent queue workers (`php artisan queue:work`) are eliminated from production.
2. **Scheduled Tasks (`crond` on Host):** Business schedules (booking expiry, notifications, daily financial reconciliation, and worker-free failure recovery) are triggered every minute by the host's `crond` invoking Laravel's scheduler inside the running `court-server` container. Dedicated scheduler containers (`php artisan schedule:work`) are eliminated.
3. **Worker-Free Failure Recovery:** Secondary delivery failures (e.g. mail transport timeouts) and provider webhook errors are persisted to `outbox_messages` and `provider_events`. Bounded scheduled recovery tasks scan eligible records every minute, isolating failures per item with exponential backoff up to 8 attempts.

---

## 2. Host `crond` Configuration

### 2.1 Single-Host Ownership Principle
**CRITICAL:** Exactly **one** host per environment (production, staging) may install and execute the crontab entry. Running crontab on multiple hosts against shared databases/containers violates schedule uniqueness and causes duplicate execution pressure.

### 2.2 Verified Execution Targets
- **Host Docker Binary:** `/usr/bin/docker` (or `/usr/local/bin/docker` — verify via `which docker`). Never rely on interactive `$PATH`.
- **Target Container Name:** `court-server`
- **Application Directory Inside Container:** `/app/booking-api`
- **Runtime User Inside Container:** `www-data` (owns storage and cache directories)
- **Invocation Command:** `php artisan schedule:run`
- **Log Destination:** `/var/log/booking-cron.log`

### 2.3 Crontab Specification
Install on the host as root or an authorized member of the `docker` group:

```cron
# /etc/cron.d/aura-court-scheduler
# Aura Court Host Cron Trigger - Runs Laravel schedule:run every minute
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

* * * * * root /usr/bin/docker exec -u www-data -w /app/booking-api court-server php artisan schedule:run >> /var/log/booking-cron.log 2>&1
```

*Note: The command executes inside the running container and automatically inherits the container's environment variables. No application secrets are placed on the host or in the crontab.*

### 2.4 Log Rotation (`logrotate`)
Create `/etc/logrotate.d/booking-cron` on the host:

```text
/var/log/booking-cron.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    copytruncate
    create 0640 root root
}
```

---

## 3. Handling Outages and Container Failures

### 3.1 Container Unavailable or Restarting
When `court-server` is stopped, updating, or restarting:
- `/usr/bin/docker exec` exits with code 1 (`Error: No such container: court-server`).
- The error is logged to `/var/log/booking-cron.log`.
- Host `crond` remains active and will automatically resume successful execution as soon as `court-server` returns to healthy state.
- **Operator Action:** Monitor cron log for sustained container errors; verify container health with `docker ps -f name=court-server`.

### 3.2 Missed-Task Catch-up & Recovery Procedure
If the host or container experienced extended downtime (e.g. 30 minutes or overnight):
1. **Outbox & Provider Event Recovery:**
   - Recovery tasks run automatically on the next tick. Outbox messages and provider events whose `available_at` / `next_attempt_at` passed during downtime are immediately picked up in bounded batches (50 items per tick).
   - No manual replay is required for outbox or provider webhooks.
2. **Booking Expiry Catch-up:**
   - On the first tick, `BookingService::expirePending()` automatically queries all bookings past `expires_at` and releases them safely.
3. **Missed Daily Financial Reconciliation (01:00 AM):**
   - If downtime spanned 01:00 AM, trigger reconciliation manually inside `court-server`:
     ```bash
     docker exec -u www-data -w /app/booking-api court-server php artisan tinker --execute="
     App\Models\Organization::where('status', 'active')->pluck('id')->each(function(\$id) {
         App\Jobs\RunDailyReconciliation::dispatch(\$id);
     });"
     ```
   - Reconciliation is idempotent and duplicate-safe: running catch-up will not create duplicate journal entries.

---

## 4. Cutover Procedure (Asynchronous Workers → Host `crond` + `sync`)

Follow these steps during maintenance window:

### Step 1: Pause Work Producers
- Inform team or temporarily enable maintenance mode if needed (`php artisan down`).
- Verify no large batch operations are actively being queued.

### Step 2: Inventory & Drain Existing Queue Backlog
- Inspect previous queue connection (Redis or Database):
  ```bash
  # Check Redis queue depth if previously used:
  docker exec -w /app/booking-api court-server php artisan tinker --execute="echo 'Redis queue size: ' . Illuminate\Support\Facades\Queue::connection('redis')->size();"
  
  # Check database jobs table if previously used:
  docker exec -w /app/booking-api court-server php artisan tinker --execute="echo 'DB queue size: ' . DB::table('jobs')->count();"
  ```
- **Allow workers to drain:** Keep old worker running until pending, reserved, and delayed counts reach 0.
- **NEVER** flush queues or truncate `jobs` table to reach zero.
- Review and preserve `failed_jobs` table for historical audit.

### Step 3: Stop Worker and Scheduler Containers
In Portainer or Docker:
- Stop and remove `booking-queue` container.
- Stop and remove `booking-scheduler` container.
- Confirm only `court-server` and `booking-realtime` remain running.

### Step 4: Update Stack & Environment Configuration
- In Portainer Stack Environment, set:
  ```env
  QUEUE_CONNECTION=sync
  ```
- Deploy the updated `devops/portainer/booking_app.stack.yml` (without queue and scheduler service definitions).
- Clear and rebuild configuration cache inside `court-server`:
  ```bash
  docker exec -u www-data -w /app/booking-api court-server php artisan config:clear
  docker exec -u www-data -w /app/booking-api court-server php artisan config:cache
  ```
- Verify effective runtime configuration:
  ```bash
  docker exec -u www-data -w /app/booking-api court-server php artisan tinker --execute="echo 'Effective queue: ' . config('queue.default');"
  # MUST output: Effective queue: sync
  ```

### Step 5: Enable Host `crond` Trigger
- Ensure `booking-scheduler` container is stopped.
- Install `/etc/cron.d/aura-court-scheduler` on the designated host.
- Tail `/var/log/booking-cron.log` for 2–3 minutes to verify clean execution.
- Resume traffic (`php artisan up`).

---

## 5. Rollback Procedure (Host `crond` + `sync` → Asynchronous Workers)

If unresolvable synchronous request latency or scheduling issues occur:

1. **Disable Host `crond`:**
   ```bash
   # Comment out the cron job or remove file:
   rm -f /etc/cron.d/aura-court-scheduler
   ```
2. **Allow Active Synchronous Operations to Settle:** Wait 30 seconds for in-flight requests to complete.
3. **Restore Portainer Stack Definition:**
   - Deploy stack revision containing `booking-queue` and `booking-scheduler` services.
   - Set Stack Environment variable `QUEUE_CONNECTION=redis` (or `database`).
4. **Refresh Container Configuration:**
   ```bash
   docker exec -u www-data -w /app/booking-api court-server php artisan config:clear
   docker exec -u www-data -w /app/booking-api court-server php artisan config:cache
   ```
5. **Verify Single Scheduler Ownership:**
   - Confirm `booking-scheduler` container is running and host cron is disabled.
6. **Reconcile Recovery State:**
   - All `outbox_messages` and `provider_events` records remain intact. The restored workers and scheduler will continue processing seamlessly.
