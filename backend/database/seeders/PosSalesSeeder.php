<?php

namespace Database\Seeders;

use App\Models\CashShift;
use App\Models\Organization;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\ServiceTab;
use App\Models\ServiceTabItem;
use App\Models\ShiftReconciliation;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\POS\PosService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * POS activity inside the shifts CashShiftSeeder opened: sales, service tabs, restocks and stock movements.
 *
 * Rows copy the columns PosService::completeOrder / confirmQrPayment / adjustStock and ServiceTabService::settleTab
 * write, with totals from PosService::calculateQuote. They are inserted directly so they can be backdated, and like
 * the booking seeders no ledger journals are posted. Settled tab orders also carry the shift and venue (the service
 * leaves them empty) so every sale belongs to a shift. Closed shifts are then finished with
 * expected cash = opening cash + cash sales, keeping the variance their reconciliation recorded.
 */
class PosSalesSeeder extends Seeder
{
    private Carbon $now;

    private PosService $pos;

    /** @var Collection<string, PosProduct> */
    private Collection $products;

    /** @var array<int, int> stock per product id while the events are replayed in time order */
    private array $stock = [];

    private int $sequence = 0;

    public function run(): void
    {
        $this->now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'));
        $this->pos = app(PosService::class);

        foreach ($this->setups() as $setup) {
            $this->seedVenue($setup);
        }
    }

    private function seedVenue(array $setup): void
    {
        $org = Organization::where('slug', $setup['org'])->first();
        $venue = $org ? Venue::where('organization_id', $org->id)->where('slug', $setup['venue'])->first() : null;
        if (! $org || ! $venue) {
            return;
        }
        $this->products = PosProduct::where('organization_id', $org->id)->get()->keyBy('sku');
        if ($this->products->isEmpty()) {
            return;
        }
        foreach ($this->products as $product) {
            $this->stock[$product->id] = (int) StockMovement::where('product_id', $product->id)->sum('quantity_delta');
        }

        $shifts = CashShift::with('cashier')
            ->where('organization_id', $org->id)
            ->where('venue_id', $venue->id)
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get();
        $users = User::whereIn('email', array_merge($this->customerEmails(), [$setup['tab_staff']]))->get()->keyBy('email');
        $customerIds = User::whereIn('email', $this->customerEmails())->orderBy('id')->pluck('id')->all();

        $events = array_merge(
            $this->restockEvents($org, $setup['restocks']),
            $this->shiftEvents($org, $venue, $shifts, $users, $customerIds, $setup),
        );
        // Replay in time order so stock balances follow the timeline (usort is stable for equal times).
        usort($events, fn (array $a, array $b): int => $a['at'] <=> $b['at']);
        foreach ($events as $event) {
            ($event['run'])();
        }

        if ($users->has($setup['tab_staff']) && $setup['open_tab'] !== []) {
            $this->seedOpenTab($org, $venue, $users[$setup['tab_staff']], $customerIds, $setup['open_tab']);
        }

        $this->finishShifts($shifts);
        $this->syncStock();
        $this->verify($org);
    }

    /** @return list<array{at: Carbon, run: callable}> */
    private function restockEvents(Organization $org, array $restocks): array
    {
        $events = [];
        $perDay = [];
        foreach ($restocks as [$dayOffset, $time, $actorEmail, $type, $note, $lines]) {
            $at = $this->now->copy()->startOfDay()->addDays($dayOffset)->setTimeFromTimeString($time);
            $actor = User::where('email', $actorEmail)->first();
            if (! $actor || $at->gte($this->now)) {
                continue;
            }
            $prefix = $type === 'in' ? 'PN' : 'XK';
            $key = $prefix.$at->format('ymd');
            $perDay[$key] = ($perDay[$key] ?? 0) + 1;
            $reference = sprintf('%s-%s-%02d', $prefix, $at->format('ymd'), $perDay[$key]);

            $events[] = ['at' => $at, 'run' => function () use ($org, $at, $actor, $type, $note, $lines, $reference): void {
                foreach ($lines as $sku => $delta) {
                    $product = $this->products[$sku] ?? null;
                    if (! $product || $this->stock[$product->id] + $delta < 0 || StockMovement::query()
                        ->where('product_id', $product->id)
                        ->where('reference_type', 'pos')
                        ->where('metadata->reference', $reference)
                        ->exists()) {
                        continue;
                    }
                    // Same shape as PosService::adjustStock.
                    $this->movement($org->id, $product, $delta, $type, 'pos', null, $actor->id, $at, [
                        'reference' => $reference,
                        'note' => $note,
                        'balance_after' => null,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit' => $product->unit,
                    ]);
                }
            }];
        }

        return $events;
    }

    /**
     * Sales for every shift that has none yet: a steady flow of walk-in sales, one sale to a regular, a pre-game
     * purchase for up to two customers who played at the venue during the shift, and settled service tabs on the
     * latest shifts. The open shift gets a few sales so far plus a wallet payment made just now.
     *
     * @return list<array{at: Carbon, run: callable}>
     */
    private function shiftEvents(Organization $org, Venue $venue, Collection $shifts, Collection $users, array $customerIds, array $setup): array
    {
        $events = [];
        $closed = $shifts->where('status', 'closed')->filter(fn (CashShift $shift): bool => ! PosOrder::where('cash_shift_id', $shift->id)->exists())->values();
        $visits = $closed->mapWithKeys(fn (CashShift $shift): array => [
            $shift->id => $this->visits($venue->id, $customerIds, $shift->opened_at, $shift->closed_at->copy()->subMinutes(30)),
        ]);

        // Settled tabs on the latest shifts: a finished visit that leaves time to settle before closing.
        $tabs = [];
        $staff = $users[$setup['tab_staff']] ?? null;
        foreach ($staff ? $closed->reverse() : [] as $shift) {
            if (count($tabs) >= count($setup['tab_baskets'])) {
                break;
            }
            $visit = $visits[$shift->id]->first(fn (Reservation $r): bool => $this->local($r->ends_at)->lte($shift->closed_at->copy()->subMinutes(10)));
            if ($visit) {
                $tabs[$shift->id] = [$visit, $setup['tab_baskets'][count($tabs)]];
            }
        }

        $archiveBefore = $this->now->copy()->subDays(60);
        $index = 0;
        foreach ($closed as $shift) {
            $archive = $shift->opened_at->lt($archiveBefore);
            $i = $archive ? 0 : $index++;
            $count = $archive ? 3 : 8 + ($i * 3) % 5;
            $regular = $users[$archive ? $setup['archive_customer'] : $setup['regulars'][$i % count($setup['regulars'])]] ?? null;
            $from = $shift->opened_at->copy()->addMinutes(12);
            $to = $shift->closed_at->copy()->subMinutes(15);

            foreach (range(0, $count - 1) as $k) {
                $at = $this->slot($from, $to, $k, $count, $i);
                $basket = $setup['baskets'][($i * 7 + $k * 5) % count($setup['baskets'])];
                $method = ($i + $k) % 5 === 2 ? 'qr' : 'cash';
                $customer = $k === 1 ? $regular : null;
                $events[] = ['at' => $at, 'run' => fn () => $this->sell($org, $shift, $basket, $method, $at, $customer, null, $k)];
            }

            [$tabVisit, $tabBasket] = $tabs[$shift->id] ?? [null, null];
            $buyers = $visits[$shift->id]->reject(fn (Reservation $r): bool => $r->id === $tabVisit?->id)->take(2)->values();
            foreach ($buyers as $v => $visit) {
                $at = $this->clamp($this->local($visit->starts_at)->subMinutes(10), $shift->opened_at->copy()->addMinutes(5), $to)->startOfMinute();
                $basket = $setup['visit_baskets'][($i + $v) % count($setup['visit_baskets'])];
                $events[] = ['at' => $at, 'run' => fn () => $this->sell($org, $shift, $basket, $v % 2 === 0 ? 'cash' : 'qr', $at, $visit->user, $visit, $count + $v)];
            }

            if ($tabVisit) {
                $settledAt = $this->local($tabVisit->ends_at)->addMinutes(5);
                $events[] = ['at' => $settledAt, 'run' => fn () => $this->settledTab($org, $shift, $tabVisit, $staff, $tabBasket, $settledAt)];
            }
        }

        $open = $shifts->firstWhere('status', 'open');
        if ($open && ! PosOrder::where('cash_shift_id', $open->id)->exists()) {
            $from = $open->opened_at->copy()->addMinutes(12);
            $to = $this->now->copy()->subMinutes(6)->startOfMinute();
            $regular = $users[$setup['open_shift_regular']] ?? null;
            foreach ($to->gt($from) ? range(0, 2) : [] as $k) {
                $at = $this->slot($from, $to, $k, 3, $index);
                $basket = $setup['baskets'][($index * 7 + $k * 5) % count($setup['baskets'])];
                $events[] = ['at' => $at, 'run' => fn () => $this->sell($org, $open, $basket, $k === 2 ? 'qr' : 'cash', $at, $k === 1 ? $regular : null, null, $k)];
            }
            // Wallet top-ups are stamped at seed time (UserSeeder), so the wallet payment happens now, after them.
            $walletCustomer = $users[$setup['wallet_customer']] ?? null;
            if ($walletCustomer && $setup['wallet_basket'] !== []) {
                $at = $this->now->copy();
                $events[] = ['at' => $at, 'run' => fn () => $this->sell($org, $open, $setup['wallet_basket'], 'wallet', $at, $walletCustomer, null, 3)];
            }
        }

        return $events;
    }

    /**
     * Reservations of demo customers at the venue that started in the window and were played (confirmed, or
     * migrated from a confirmed/completed booking), earliest first.
     *
     * @return Collection<int, Reservation>
     */
    private function visits(int $venueId, array $customerIds, Carbon $from, Carbon $to): Collection
    {
        return Reservation::query()
            ->with(['slots', 'user'])
            ->whereIn('user_id', $customerIds)
            ->whereHas('court', fn ($q) => $q->where('venue_id', $venueId))
            ->whereHas('slots', fn ($q) => $q->whereBetween('starts_at', [$from->copy()->utc(), $to->copy()->utc()]))
            ->where(fn ($q) => $q->whereIn('status', ['confirmed', 'in_use', 'completed'])
                ->orWhereHas('legacyMapping', fn ($m) => $m->whereIn('legacy_status', ['confirmed', 'completed'])))
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Reservation $r): string => sprintf('%012d-%08d', $r->starts_at->getTimestamp(), $r->id))
            ->values();
    }

    /** @param list<array{0: string, 1: int, 2?: int}> $basket */
    private function sell(Organization $org, CashShift $shift, array $basket, string $method, Carbon $at, ?User $customer, ?Reservation $reservation, int $k): void
    {
        $items = $this->quoteItems($basket);
        if ($items === []) {
            return;
        }
        $quote = $this->pos->calculateQuote($org->id, ['items' => $items, 'payment_method' => 'cash']);
        $total = (int) $quote['grand_total'];

        $wallet = null;
        if ($method === 'wallet') {
            $wallet = $customer ? WalletAccount::where('organization_id', $org->id)->where('user_id', $customer->id)->where('currency', 'VND')->first() : null;
            $lastTransactionAt = $wallet ? WalletTransaction::where('wallet_account_id', $wallet->id)->max('created_at') : null;
            if (! $wallet || $wallet->balance < $total || ($lastTransactionAt && Carbon::parse($lastTransactionAt, $at->getTimezone())->gt($at))) {
                $method = 'cash';
                $wallet = null;
            }
        }

        $cashier = $shift->cashier;
        $isQr = $method === 'qr';
        $completedAt = $isQr ? $at->copy()->addMinutes(1 + $k % 2) : $at->copy();
        $step = [10000, 50000, 100000][$k % 3];
        $cashTendered = $method === 'cash' ? (int) (ceil($total / $step) * $step) : null;
        $changeDue = $method === 'cash' ? $cashTendered - $total : 0;
        $code = sprintf('POS-%d-%s', $cashier->id, $this->stamp($at));

        $receipt = [
            'order_code' => $code,
            'created_at' => $at->toIso8601String(),
            'cashier_name' => $cashier->name,
            'customer_name' => $customer?->name,
            'court_info' => $reservation ? "Sân #{$reservation->court_id}" : null,
            'items' => array_map(fn (array $line): array => [
                'product_id' => $line['product_id'],
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'rental_duration_hours' => $line['rental_duration_hours'],
                'price' => $line['unit_price'],
                'vat_rate' => $line['vat_rate'],
                'tax_amount' => $line['tax_amount'],
                'total' => $line['line_total'],
            ], $quote['items']),
            'subtotal' => $quote['subtotal'],
            'discount_amount' => $quote['discount_amount'],
            'membership_discount_amount' => $quote['membership_discount_amount'],
            'taxable_amount' => $quote['taxable_amount'],
            'tax_amount' => $quote['tax_amount'],
            'vat_breakdown' => $quote['vat_breakdown'],
            'rounding_amount' => $quote['rounding_amount'],
            'grand_total' => $total,
            'payment_method' => $method,
            'tenders' => [],
            'cash_tendered' => $cashTendered,
            'change_due' => $changeDue,
            'status' => 'completed',
            'is_paid' => true,
        ];
        $providerReference = $code;
        if ($isQr) {
            $bank = config('services.sepay.bank', 'MBBank');
            $account = config('services.sepay.account', '0901234567');
            $accountName = config('services.sepay.account_name', 'AURA COURT SYSTEM');
            $receipt['qr_data'] = [
                'bank' => $bank,
                'account' => $account,
                'account_name' => $accountName,
                'amount' => $total,
                'memo' => $code,
                'qr_url' => 'https://img.vietqr.io/image/'.$bank.'-'.$account.'-compact2.png?amount='.$total.'&addInfo='.urlencode($code).'&accountName='.urlencode((string) $accountName),
            ];
            // Confirmed by the SePay webhook: the payment keeps the bank transaction id.
            $receipt['verified_at'] = $completedAt->toIso8601String();
            $providerReference = (string) (20000000 + crc32($code) % 9000000);
        }

        $order = (new PosOrder)->forceFill([
            'organization_id' => $org->id,
            'cart_id' => null,
            'customer_id' => $customer?->id,
            'cashier_id' => $cashier->id,
            'cash_shift_id' => $shift->id,
            'venue_id' => $shift->venue_id,
            'register_id' => $shift->register_id,
            'reservation_id' => $reservation?->id,
            'status' => 'completed',
            'payment_method' => $method,
            'subtotal' => $quote['subtotal'],
            'discount_amount' => $quote['discount_amount'],
            'tax_amount' => $quote['tax_amount'],
            'total_amount' => $total,
            'rounding_amount' => $quote['rounding_amount'],
            'payment_reference' => $code,
            'cash_tendered' => $cashTendered,
            'change_due' => $changeDue,
            'completed_at' => $completedAt,
            'metadata' => [
                'vat_breakdown' => $quote['vat_breakdown'],
                'receipt' => $receipt,
                'expires_at' => $isQr ? $at->copy()->addMinutes(15)->toIso8601String() : null,
                'coupon_usage' => null,
                'membership_snapshot' => null,
            ],
            'created_at' => $at,
            'updated_at' => $completedAt,
        ]);
        $order->save();

        foreach ($quote['items'] as $line) {
            (new PosOrderItem)->forceFill([
                'organization_id' => $org->id,
                'order_id' => $order->id,
                'product_id' => $line['product_id'],
                'sku' => $line['sku'],
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'rental_duration_hours' => $line['rental_duration_hours'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'metadata' => ['line_subtotal' => $line['line_subtotal'], 'vat_rate' => $line['vat_rate']],
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();

            $product = $this->products[$line['sku']];
            if ($product->isTracked()) {
                $this->movement($org->id, $product, -$line['quantity'], 'sale', PosOrder::class, $order->id, $cashier->id, $completedAt, null);
            }
        }

        (new PosPayment)->forceFill([
            'organization_id' => $org->id,
            'order_id' => $order->id,
            'method' => $method,
            'amount' => $total,
            'status' => 'paid',
            'provider_reference' => $providerReference,
            'metadata' => ['cash_tendered' => $cashTendered, 'change_due' => $changeDue, 'qr_data' => $receipt['qr_data'] ?? null],
            'created_at' => $at,
            'updated_at' => $completedAt,
        ])->save();

        if ($wallet) {
            $balanceAfter = (int) $wallet->balance - $total;
            (new WalletTransaction)->forceFill([
                'organization_id' => $org->id,
                'wallet_account_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $total,
                'balance_after' => $balanceAfter,
                'source_type' => 'wallet_spend',
                'source_id' => abs(crc32("pos:{$order->id}")),
                'reference' => "pos:{$order->id}",
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();
            $wallet->timestamps = false;
            $wallet->forceFill(['balance' => $balanceAfter, 'updated_at' => $at])->save();
        }
    }

    /**
     * A service tab staff ran during a finished visit and the shift cashier settled in cash after play, shaped like
     * ServiceTabService::addItems + settleTab (tab prices carry no VAT).
     */
    private function settledTab(Organization $org, CashShift $shift, Reservation $reservation, User $staff, array $basket, Carbon $settledAt): void
    {
        $lines = $this->tabLines($basket);
        if ($lines === [] || ServiceTab::where('reservation_id', $reservation->id)->exists()) {
            return;
        }
        $cashier = $shift->cashier;
        $customer = $reservation->user;
        $openedAt = $this->local($reservation->starts_at)->addMinutes(5);
        $total = array_sum(array_map(fn (array $line): int => $line['line_total'], $lines));
        $cashTendered = (int) (ceil($total / 50000) * 50000);
        $changeDue = $cashTendered - $total;
        $code = 'TAB-POS-'.$this->stamp($settledAt);

        $tab = (new ServiceTab)->forceFill([
            'organization_id' => $org->id,
            'reservation_id' => $reservation->id,
            'status' => ServiceTab::STATUS_UNPAID,
            'total_amount' => $total,
            'opened_by' => $staff->id,
            'created_at' => $openedAt,
            'updated_at' => $openedAt,
        ]);
        $tab->save();

        $order = (new PosOrder)->forceFill([
            'organization_id' => $org->id,
            'customer_id' => $customer?->id,
            'cashier_id' => $cashier->id,
            'cash_shift_id' => $shift->id,
            'venue_id' => $shift->venue_id,
            'register_id' => $shift->register_id,
            'reservation_id' => $reservation->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'rounding_amount' => 0,
            'total_amount' => $total,
            'payment_reference' => $code,
            'cash_tendered' => $cashTendered,
            'change_due' => $changeDue,
            'completed_at' => $settledAt,
            'created_at' => $settledAt,
            'updated_at' => $settledAt,
        ]);
        $order->save();

        $receiptItems = [];
        foreach ($lines as $j => $line) {
            $product = $line['product'];
            $addedAt = $openedAt->copy()->addMinutes(10 * $j);
            (new ServiceTabItem)->forceFill([
                'service_tab_id' => $tab->id,
                'product_id' => $product->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'status' => ServiceTabItem::STATUS_SETTLED,
                'created_at' => $addedAt,
                'updated_at' => $settledAt,
            ])->save();
            $this->movement($org->id, $product, -$line['quantity'], 'sale', 'service_tab', $tab->id, $cashier->id, $settledAt, [
                'balance_after' => null,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'unit' => $product->unit,
                'tab_id' => $tab->id,
                'reservation_id' => $reservation->id,
            ]);
            (new PosOrderItem)->forceFill([
                'organization_id' => $org->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'created_at' => $settledAt,
                'updated_at' => $settledAt,
            ])->save();
            $receiptItems[] = ['name' => $product->name, 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'line_total' => $line['line_total']];
        }

        (new PosPayment)->forceFill([
            'organization_id' => $org->id,
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => $total,
            'status' => 'completed',
            'metadata' => ['cash_tendered' => $cashTendered, 'change_due' => $changeDue],
            'created_at' => $settledAt,
            'updated_at' => $settledAt,
        ])->save();

        $order->timestamps = false;
        $order->forceFill(['metadata' => [
            'notes' => "Settlement of Service Tab #{$tab->id}",
            'receipt' => [
                'order_code' => $code,
                'created_at' => $settledAt->toIso8601String(),
                'cashier_name' => $cashier->name,
                'customer_name' => $customer?->name,
                'court_info' => "Tab Sân #{$reservation->court_id}",
                'items' => $receiptItems,
                'subtotal' => $total,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'grand_total' => $total,
                'payment_method' => 'cash',
                'cash_tendered' => $cashTendered,
                'change_due' => $changeDue,
            ],
        ]])->save();

        $tab->timestamps = false;
        $tab->forceFill([
            'status' => ServiceTab::STATUS_SETTLED,
            'settled_by' => $cashier->id,
            'settled_at' => $settledAt,
            'pos_order_id' => $order->id,
            'updated_at' => $settledAt,
        ])->save();
    }

    /**
     * An unpaid tab on today's reservation at the venue (in play now if there is one), left for the cashier to
     * settle during the demo. Items are only reserved, so stock does not move yet.
     */
    private function seedOpenTab(Organization $org, Venue $venue, User $staff, array $customerIds, array $basket): void
    {
        if (ServiceTab::where('organization_id', $org->id)->where('status', ServiceTab::STATUS_UNPAID)->exists()) {
            return;
        }
        $today = $this->visits($venue->id, $customerIds, $this->now->copy()->startOfDay(), $this->now->copy()->endOfDay())
            ->filter(fn (Reservation $r): bool => $r->status === 'confirmed')
            ->values();
        $started = $today->filter(fn (Reservation $r): bool => $this->local($r->starts_at)->lte($this->now));
        $reservation = $started->first(fn (Reservation $r): bool => $this->local($r->ends_at)->gt($this->now))
            ?? $started->last()
            ?? $today->first();
        $lines = $this->tabLines($basket);
        if (! $reservation || $lines === []) {
            $this->command?->warn('PosSalesSeeder: no confirmed reservation today at '.$venue->slug.', open service tab skipped.');

            return;
        }

        $startsAt = $this->local($reservation->starts_at);
        $openedAt = ($startsAt->lte($this->now) ? $this->clamp($startsAt->copy()->addMinutes(5), $startsAt, $this->now) : $this->now->copy()->subMinutes(5))->startOfMinute();
        $tab = (new ServiceTab)->forceFill([
            'organization_id' => $org->id,
            'reservation_id' => $reservation->id,
            'status' => ServiceTab::STATUS_UNPAID,
            'total_amount' => array_sum(array_map(fn (array $line): int => $line['line_total'], $lines)),
            'opened_by' => $staff->id,
            'created_at' => $openedAt,
            'updated_at' => $openedAt,
        ]);
        $tab->save();
        foreach ($lines as $line) {
            (new ServiceTabItem)->forceFill([
                'service_tab_id' => $tab->id,
                'product_id' => $line['product']->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'status' => ServiceTabItem::STATUS_RESERVED,
                'created_at' => $openedAt,
                'updated_at' => $openedAt,
            ])->save();
        }
    }

    /** Closed shifts: expected cash = opening cash + cash sales; the counted cash keeps the reviewed variance. */
    private function finishShifts(Collection $shifts): void
    {
        foreach ($shifts->where('status', 'closed') as $shift) {
            $cashSales = (int) PosOrder::where('cash_shift_id', $shift->id)->where('status', 'completed')->where('payment_method', 'cash')->sum('total_amount');
            $variance = (int) (ShiftReconciliation::where('cash_shift_id', $shift->id)->value('variance') ?? $shift->variance ?? 0);
            $expected = (int) $shift->opening_cash + $cashSales;
            $shift->timestamps = false;
            $shift->forceFill(['expected_cash' => $expected, 'declared_cash' => $expected + $variance, 'variance' => $variance])->save();
        }
    }

    /** stock_quantity = sum of the product's movements, last touched by its latest movement. */
    private function syncStock(): void
    {
        foreach ($this->products as $product) {
            $movements = StockMovement::where('product_id', $product->id);
            $stock = (int) (clone $movements)->sum('quantity_delta');
            $lastMovedAt = (clone $movements)->max('created_at');
            $product->timestamps = false;
            $product->forceFill([
                'stock_quantity' => $stock,
                'updated_at' => $lastMovedAt ? Carbon::parse($lastMovedAt, $this->now->getTimezone())->max($product->updated_at)->copy() : $product->updated_at,
            ])->save();
        }
    }

    private function verify(Organization $org): void
    {
        $failures = [];
        $orders = DB::select('
            select o.payment_reference, o.total_amount,
                (select coalesce(sum(i.line_total), 0) from pos_order_items i where i.order_id = o.id) as items_total,
                (select coalesce(sum(p.amount), 0) from pos_payments p where p.order_id = o.id) as paid_total
            from pos_orders o where o.organization_id = ?', [$org->id]);
        foreach ($orders as $order) {
            if ((int) $order->items_total !== (int) $order->total_amount || (int) $order->paid_total !== (int) $order->total_amount) {
                $failures[] = "order {$order->payment_reference}: items {$order->items_total}, paid {$order->paid_total}, total {$order->total_amount}";
            }
        }
        foreach (CashShift::where('organization_id', $org->id)->where('status', 'closed')->get() as $shift) {
            $cashSales = (int) PosOrder::where('cash_shift_id', $shift->id)->where('status', 'completed')->where('payment_method', 'cash')->sum('total_amount');
            $reconciled = ShiftReconciliation::where('cash_shift_id', $shift->id)->value('variance');
            if ($shift->expected_cash !== $shift->opening_cash + $cashSales || $shift->declared_cash - $shift->expected_cash !== $shift->variance || (int) $reconciled !== $shift->variance) {
                $failures[] = "shift {$shift->id}: cash does not reconcile";
            }
        }
        foreach (PosProduct::where('organization_id', $org->id)->get() as $product) {
            $reserved = (int) ServiceTabItem::where('product_id', $product->id)->where('status', ServiceTabItem::STATUS_RESERVED)->sum('quantity');
            if ($product->stock_quantity !== (int) StockMovement::where('product_id', $product->id)->sum('quantity_delta') || $product->stock_quantity < $reserved) {
                $failures[] = "product {$product->sku}: stock {$product->stock_quantity} does not match its movements or reservations";
            }
        }
        foreach (WalletAccount::where('organization_id', $org->id)->get() as $wallet) {
            $last = WalletTransaction::where('wallet_account_id', $wallet->id)->orderByDesc('created_at')->orderByDesc('id')->value('balance_after');
            if ($last !== null && (int) $last !== (int) $wallet->balance) {
                $failures[] = "wallet {$wallet->id}: balance {$wallet->balance} != last balance_after {$last}";
            }
        }
        $doubleOpen = CashShift::where('organization_id', $org->id)->where('status', 'open')->groupBy('cashier_id')->havingRaw('count(*) > 1')->pluck('cashier_id');
        if ($doubleOpen->isNotEmpty()) {
            $failures[] = 'cashiers with more than one open shift: '.$doubleOpen->implode(', ');
        }
        if ($failures !== []) {
            throw new RuntimeException("PosSalesSeeder self-check failed for {$org->slug}:\n".implode("\n", $failures));
        }
    }

    /**
     * Items for calculateQuote; a tracked product without enough stock left is dropped from the basket.
     *
     * @return list<array{product_id: int, quantity: int, rental_duration_hours?: int}>
     */
    private function quoteItems(array $basket): array
    {
        $items = [];
        foreach ($basket as $line) {
            [$sku, $quantity] = $line;
            $product = $this->products[$sku] ?? null;
            if (! $product || ($product->isTracked() && $this->stock[$product->id] < $quantity)) {
                continue;
            }
            $items[] = ['product_id' => $product->id, 'quantity' => $quantity] + (isset($line[2]) ? ['rental_duration_hours' => $line[2]] : []);
        }

        return $items;
    }

    /** @return list<array{product: PosProduct, quantity: int, unit_price: int, line_total: int}> */
    private function tabLines(array $basket): array
    {
        $lines = [];
        foreach ($basket as [$sku, $quantity]) {
            $product = $this->products[$sku] ?? null;
            if (! $product || ! $product->isTracked() || $this->stock[$product->id] < $quantity) {
                continue;
            }
            $lines[] = ['product' => $product, 'quantity' => $quantity, 'unit_price' => (int) $product->price, 'line_total' => $quantity * (int) $product->price];
        }

        return $lines;
    }

    private function movement(int $orgId, PosProduct $product, int $delta, string $type, ?string $referenceType, ?int $referenceId, ?int $userId, Carbon $at, ?array $metadata): void
    {
        $this->stock[$product->id] += $delta;
        if ($metadata !== null && array_key_exists('balance_after', $metadata)) {
            $metadata['balance_after'] = $this->stock[$product->id];
        }
        (new StockMovement)->forceFill([
            'organization_id' => $orgId,
            'product_id' => $product->id,
            'type' => $type,
            'quantity_delta' => $delta,
            'unit_cost' => (int) $product->cost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $userId,
            'metadata' => $metadata,
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();
    }

    /** Sale k of $count, spread over the window with a few minutes of deterministic jitter. */
    private function slot(Carbon $from, Carbon $to, int $k, int $count, int $i): Carbon
    {
        $span = max(0, $to->getTimestamp() - $from->getTimestamp());
        $offset = intdiv($span * $k, $count) + (($i * 7 + $k * 11) % 9) * 60;

        return $from->copy()->addSeconds(min($offset, $span))->startOfMinute();
    }

    /** The uniqid()-shaped suffix of POS order codes (8 hex digits of seconds + 5 of a sequence). */
    private function stamp(Carbon $at): string
    {
        return strtoupper(sprintf('%08x%05x', $at->getTimestamp(), ++$this->sequence));
    }

    /** A copy of $time moved inside [$min, $max] (Carbon's min/max return one of the operands, not a copy). */
    private function clamp(Carbon $time, Carbon $min, Carbon $max): Carbon
    {
        return $time->copy()->max($min)->copy()->min($max)->copy();
    }

    private function local(Carbon $time): Carbon
    {
        return $time->copy()->setTimezone($this->now->getTimezone());
    }

    private function customerEmails(): array
    {
        return array_merge(['user@example.com'], array_map(fn (int $n): string => sprintf('khach%02d@demo.auracourt.vn', $n), range(1, 24)));
    }

    private function setups(): array
    {
        $khach = fn (int $n): string => sprintf('khach%02d@demo.auracourt.vn', $n);

        return [
            [
                'org' => 'aura-badminton-club',
                'venue' => 'aura-court',
                'tab_staff' => 'staff@demo.auracourt.vn',
                'regulars' => ['user@example.com', $khach(1), $khach(4), 'user@example.com', $khach(6), $khach(9), 'user@example.com', $khach(12)],
                // Buys in the oldest shift, so that sale's loyalty points have expired by now.
                'archive_customer' => $khach(8),
                'open_shift_regular' => $khach(15),
                'wallet_customer' => $khach(2),
                'wallet_basket' => [['POS-POCARI-500', 2], ['POS-PROBAR-60', 1]],
                // aura-court has badminton, pickleball and table tennis courts.
                'baskets' => [
                    [['POS-LAVIE-500', 2]],
                    [['POS-REVIVE-500', 2], ['POS-OISHI-40', 1]],
                    [['POS-POCARI-500', 1], ['POS-CHUOI-GIA', 2]],
                    [['POS-VINASTAR-12', 1], ['POS-LAVIE-500', 2]],
                    [['POS-REDBULL-250', 1], ['POS-PROBAR-60', 1]],
                    [['POS-RENT-BMT', 2, 2]],
                    [['POS-YONEX-GRIP', 2], ['POS-WRISTBAND-YNX', 1]],
                    [['POS-CAPHE-SUADA', 2]],
                    [['POS-COCO-330', 1], ['POS-LAVIE-500', 1]],
                    [['POS-SVC-DANLUOI', 1]],
                    [['POS-RENT-PKB', 2, 1], ['POS-LAVIE-500', 2]],
                    [['POS-GUGEL-32', 2], ['POS-POCARI-500', 1]],
                    [['POS-YONEX-AS50', 1]],
                    [['POS-BONGBAN-DF3', 1], ['POS-LAVIE-500', 2]],
                    [['POS-SOCKS-AURA', 1], ['POS-REVIVE-500', 1]],
                    [['POS-SVC-TUDO', 2], ['POS-LAVIE-500', 2]],
                    [['POS-FRANKLIN-X40', 1], ['POS-REVIVE-500', 2]],
                    [['POS-REVIVE-500', 4], ['POS-OISHI-40', 2]],
                ],
                'visit_baskets' => [
                    [['POS-LAVIE-500', 2], ['POS-REVIVE-500', 1]],
                    [['POS-POCARI-500', 2]],
                    [['POS-REVIVE-500', 2], ['POS-CHUOI-GIA', 2]],
                    [['POS-CAPHE-SUADA', 1], ['POS-LAVIE-500', 1]],
                ],
                'tab_baskets' => [
                    [['POS-LAVIE-500', 4], ['POS-REVIVE-500', 2]],
                    [['POS-POCARI-500', 3], ['POS-OISHI-40', 2]],
                    [['POS-REDBULL-250', 2], ['POS-LAVIE-500', 2], ['POS-CHUOI-GIA', 2]],
                ],
                'open_tab' => [['POS-LAVIE-500', 4], ['POS-REVIVE-500', 2], ['POS-OISHI-40', 2]],
                // [day offset, time, actor, type, note, [sku => quantity delta]]
                'restocks' => [
                    [-9, '08:30', 'manager.caugiay@demo.auracourt.vn', 'in', 'Nhập hàng NPP nước giải khát Minh Phát', ['POS-LAVIE-500' => 48, 'POS-REVIVE-500' => 48, 'POS-POCARI-500' => 48, 'POS-REDBULL-250' => 24]],
                    [-9, '09:10', 'manager.caugiay@demo.auracourt.vn', 'in', 'Nhập hàng NPP bánh kẹo Hoàng Long', ['POS-OISHI-40' => 40, 'POS-PROBAR-60' => 20]],
                    [-5, '08:45', 'manager.caugiay@demo.auracourt.vn', 'in', 'Nhập chuối từ vựa trái cây Long Biên', ['POS-CHUOI-GIA' => 40]],
                    [-4, '09:00', 'manager.caugiay@demo.auracourt.vn', 'in', 'Nhập cà phê pha sẵn từ xưởng Phương Vy', ['POS-CAPHE-SUADA' => 24]],
                    [-4, '09:20', 'manager.caugiay@demo.auracourt.vn', 'in', 'Nhập hàng NPP Yonex miền Bắc', ['POS-YONEX-GRIP' => 24]],
                    [-2, '08:15', 'manager.caugiay@demo.auracourt.vn', 'out', 'Kiểm kho đầu ngày: hủy chuối dập, quá chín', ['POS-CHUOI-GIA' => -5]],
                ],
            ],
            [
                'org' => 'hoang-gia-sports',
                'venue' => 'hoang-gia-phu-my-hung',
                'tab_staff' => 'staff@demo.auracourt.vn',
                'regulars' => [$khach(10), $khach(14)],
                'archive_customer' => $khach(10),
                'open_shift_regular' => $khach(10),
                'wallet_customer' => $khach(10),
                'wallet_basket' => [],
                // Pickleball, tennis and 5-a-side football.
                'baskets' => [
                    [['HG-LAVIE-500', 2]],
                    [['HG-REVIVE-500', 2]],
                    [['HG-RENT-PKB', 2, 2]],
                    [['HG-POCARI-500', 2], ['HG-LAVIE-500', 1]],
                    [['HG-WILSON-USOPEN', 1]],
                    [['HG-LAVIE-500', 10]],
                    [['HG-CAPHE-SUADA', 2]],
                    [['HG-FRANKLIN-X40', 1], ['HG-GRIP-WILSON', 1]],
                    [['HG-RENT-BALL', 1, 1], ['HG-LAVIE-500', 6]],
                    [['HG-GRIP-WILSON', 2]],
                    [['HG-REVIVE-500', 3], ['HG-POCARI-500', 2]],
                ],
                'visit_baskets' => [
                    [['HG-LAVIE-500', 2]],
                    [['HG-REVIVE-500', 2], ['HG-LAVIE-500', 1]],
                    [['HG-POCARI-500', 2]],
                ],
                // The owner runs the counter alone, without tabs.
                'tab_baskets' => [],
                'open_tab' => [],
                'restocks' => [
                    [-5, '09:00', 'owner.hoanggia@demo.auracourt.vn', 'in', 'Nhập hàng NPP nước giải khát Phú Mỹ', ['HG-LAVIE-500' => 48, 'HG-REVIVE-500' => 24, 'HG-POCARI-500' => 24]],
                ],
            ],
        ];
    }
}
