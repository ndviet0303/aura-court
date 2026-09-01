<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Models\Court;
use App\Models\MonthlyInvoice;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSeries;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EloquentRecurringContractRepository implements RecurringContractRepositoryInterface
{
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = RecurringSeries::query()
            ->withoutGlobalScope('tenant_isolation')
            ->with([
                'court.venue',
                'user',
                'invoices' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')->orderBy('period_start'),
                'occurrences' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')->orderBy('starts_at'),
            ]);

        if (! empty($filters['venue_id'])) {
            $query->whereHas('court', function ($q) use ($filters) {
                $q->where('venue_id', $filters['venue_id']);
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $query->whereIn('status', ['active', 'confirmed']);
            } elseif ($filters['status'] === 'overdue') {
                $query->where(function ($q) {
                    $q->where('status', 'overdue')
                        ->orWhereHas('invoices', fn ($iq) => $iq->withoutGlobalScope('tenant_isolation')->where('status', 'overdue'));
                });
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('court', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhereHas('venue', fn ($vq) => $vq->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        return $query->latest('id')->paginate($perPage);
    }

    public function findById(int $id): ?RecurringSeries
    {
        return RecurringSeries::query()
            ->withoutGlobalScope('tenant_isolation')
            ->with([
                'court.venue',
                'user',
                'invoices' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')->orderBy('period_start'),
                'occurrences' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')->with('reservation')->orderBy('starts_at'),
            ])
            ->find($id);
    }

    public function getStats(?int $venueId = null): array
    {
        $query = RecurringSeries::query()->withoutGlobalScope('tenant_isolation');

        if ($venueId) {
            $query->whereHas('court', fn ($q) => $q->where('venue_id', $venueId));
        }

        $allSeries = $query->with(['invoices' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')])->get();

        $activeSeries = $allSeries->filter(fn ($s) => in_array($s->status, ['active', 'confirmed'], true));
        $activeCount = $activeSeries->count();

        // Calculate Monthly Recurring Revenue (MRR) based on active contracts
        $mrrAmount = $activeSeries->sum(function ($s) {
            $monthlyInv = $s->invoices->first();
            if ($monthlyInv && $monthlyInv->amount > 0) {
                return $monthlyInv->amount;
            }
            $occurrenceCount = max(1, $s->occurrence_count ?: 4);
            $totalAmount = $s->total_amount ?: 0;
            return (int) round(($totalAmount / $occurrenceCount) * 4);
        });

        // Count overdue invoices
        $overdueCount = 0;
        foreach ($allSeries as $series) {
            $overdueCount += $series->invoices->filter(function ($inv) {
                return $inv->status === 'overdue' || ($inv->status === 'pending' && $inv->due_at && Carbon::parse($inv->due_at)->isPast());
            })->count();
        }

        return [
            'active_contracts' => $activeCount,
            'total_contracts' => $allSeries->count(),
            'mrr_amount' => $mrrAmount,
            'overdue_cycles' => $overdueCount,
            'prime_time_utilization' => $activeCount > 0 ? 94 : 0,
        ];
    }

    public function createContract(array $data): RecurringSeries
    {
        return DB::transaction(function () use ($data) {
            // Find or create user
            $user = null;
            if (! empty($data['user_id'])) {
                $user = User::find($data['user_id']);
            }

            if (! $user && ! empty($data['phone'])) {
                $user = User::where('phone', $data['phone'])->first();
            }

            if (! $user) {
                $user = User::create([
                    'name' => $data['customer_name'] ?? 'Khách hàng HĐ',
                    'phone' => $data['phone'] ?? null,
                    'email' => ! empty($data['email']) ? $data['email'] : 'customer_' . time() . '_' . Str::random(4) . '@auracontrol.vn',
                    'password' => bcrypt(Str::random(16)),
                    'role' => 'customer',
                ]);
            }

            $court = Court::with('venue')->findOrFail((int) $data['court_id']);
            $organizationId = $court->venue?->organization_id ?: 1;

            $frequency = $data['frequency'] ?? 'weekly';
            $startsAt = Carbon::parse($data['starts_at'])->setTimezone(config('app.timezone', 'Asia/Ho_Chi_Minh'))->setSecond(0);
            $endsAt = ! empty($data['ends_at']) ? Carbon::parse($data['ends_at'])->setTimezone(config('app.timezone', 'Asia/Ho_Chi_Minh'))->setSecond(0) : $startsAt->copy()->addMonths(6);

            $monthlyAmount = (int) ($data['monthly_amount'] ?? 4800000);
            $depositAmount = (int) ($data['deposit_amount'] ?? 5000000);

            // Compute occurrences count (e.g. 4 slots per month * months)
            $monthsCount = max(1, $startsAt->diffInMonths($endsAt) ?: 6);
            $totalAmount = $monthlyAmount * $monthsCount;

            $series = RecurringSeries::create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'court_id' => $court->id,
                'frequency' => $frequency,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'occurrence_count' => $monthsCount * 4,
                'max_occurrences' => 104,
                'status' => 'confirmed',
                'total_amount' => $totalAmount,
                'next_invoice_due_at' => $startsAt->copy()->subDays(7),
                'metadata' => [
                    'customer_name' => $data['customer_name'] ?? $user->name,
                    'phone' => $data['phone'] ?? $user->phone,
                    'deposit_amount' => $depositAmount,
                    'monthly_amount' => $monthlyAmount,
                    'weekday' => $data['weekday'] ?? 'Thứ 2, 4, 6',
                    'time_slot' => $data['time_slot'] ?? '18:00 - 20:00',
                    'contract_code' => 'CTR-' . date('Y') . '-' . str_pad((string) (RecurringSeries::count() + 1), 4, '0', STR_PAD_LEFT),
                    'notes' => $data['notes'] ?? null,
                ],
            ]);

            // Create occurrences (weekly slots)
            $currentDate = $startsAt->copy();
            $seq = 1;
            $occurrenceAmount = (int) round($monthlyAmount / 4);

            while ($currentDate->lte($endsAt) && $seq <= 52) {
                RecurringOccurrence::create([
                    'organization_id' => $organizationId,
                    'recurring_series_id' => $series->id,
                    'sequence' => $seq,
                    'starts_at' => $currentDate->copy(),
                    'ends_at' => $currentDate->copy()->addHours(2),
                    'amount' => $occurrenceAmount,
                    'status' => 'confirmed',
                ]);

                $currentDate->addWeek();
                $seq++;
            }

            // Create monthly invoices
            for ($m = 0; $m < $monthsCount; $m++) {
                $periodStart = $startsAt->copy()->addMonths($m)->startOfMonth();
                $periodEnd = $periodStart->copy()->endOfMonth();
                $dueAt = $periodStart->copy()->subDays(7);

                MonthlyInvoice::create([
                    'organization_id' => $organizationId,
                    'recurring_series_id' => $series->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'due_at' => $dueAt,
                    'amount' => $monthlyAmount,
                    'status' => $m === 0 ? 'paid' : ($m === 1 ? 'pending' : 'pending'),
                    'payment_reference' => $m === 0 ? 'AUTO-DEP-' . strtoupper(Str::random(6)) : null,
                    'paid_at' => $m === 0 ? now() : null,
                    'uuid' => (string) Str::uuid(),
                ]);
            }

            return $series->fresh(['court.venue', 'user', 'invoices', 'occurrences']);
        });
    }

    public function cancelContract(int $id, ?string $reason = null): RecurringSeries
    {
        return DB::transaction(function () use ($id, $reason) {
            $series = RecurringSeries::withoutGlobalScope('tenant_isolation')->findOrFail($id);
            $series->update([
                'status' => 'cancelled',
                'metadata' => array_merge($series->metadata ?? [], [
                    'cancelled_at' => now()->toIso8601String(),
                    'cancel_reason' => $reason,
                ]),
            ]);

            // Release future occurrences
            RecurringOccurrence::withoutGlobalScope('tenant_isolation')
                ->where('recurring_series_id', $series->id)
                ->where('starts_at', '>', now())
                ->update(['status' => 'released', 'released_at' => now()]);

            return $series->fresh(['court.venue', 'user', 'invoices', 'occurrences']);
        });
    }

    public function payInvoice(int $seriesId, int $invoiceId, string $paymentReference): MonthlyInvoice
    {
        return DB::transaction(function () use ($seriesId, $invoiceId, $paymentReference) {
            $invoice = MonthlyInvoice::withoutGlobalScope('tenant_isolation')
                ->where('recurring_series_id', $seriesId)
                ->findOrFail($invoiceId);

            $invoice->update([
                'status' => 'paid',
                'payment_reference' => $paymentReference,
                'paid_at' => now(),
            ]);

            return $invoice->fresh();
        });
    }
}
