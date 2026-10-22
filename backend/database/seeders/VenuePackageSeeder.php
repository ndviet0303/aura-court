<?php

namespace Database\Seeders;

use App\Domain\Package\Actions\ApplyPackagePayment;
use App\Domain\Package\Actions\CreatePackagePaymentRequest;
use App\Domain\Package\Actions\TerminateVenuePackage;
use App\Domain\Package\DTOs\CreatePackagePaymentRequestData;
use App\Domain\Package\DTOs\TerminateVenuePackageData;
use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePaymentReceipt;
use App\Models\PackagePrice;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePackage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D16 demo: four prices and an active package at every venue, each paid through the live package actions on a
 * back-dated clock. One venue has under three days left, one terminated its first package and bought another,
 * and one paid a second request after its first transfer arrived late.
 */
class VenuePackageSeeder extends Seeder
{
    private const PRICES = [30 => 300000, 90 => 810000, 180 => 1500000, 360 => 2700000];

    // Each organization's own owner pays, not a platform admin who also owns the flagship club.
    private const OWNERS = ['aura-badminton-club' => 'owner@demo.auracourt.vn', 'hoang-gia-sports' => 'owner.hoanggia@demo.auracourt.vn'];

    private Carbon $now;

    public function run(): void
    {
        $this->now = Carbon::now(config('app.timezone'))->startOfMinute();
        // Checked before any write: without AuraCourt's receiving account the request action refuses to run.
        if (blank(config('services.sepay.account'))) {
            if (! app()->environment('local', 'testing')) {
                throw new RuntimeException('SEPAY_ACCOUNT is not set. Seeding with APP_ENV='.app()->environment().' requires AuraCourt\'s SePay receiving account (see backend/.env.example).');
            }
            config(['services.sepay.account' => '0000000000']);
        }

        $admin = User::where('email', 'admin@auracourt.vn')->firstOrFail();
        // Effective before the first back-dated request below.
        $this->at($this->day(40, 8, 0), function () use ($admin): void {
            foreach (self::PRICES as $termDays => $amount) {
                // A term that already has a price keeps it, so a reseed never overrides a price the admin set.
                PackagePrice::firstOrCreate(['term_days' => $termDays], ['amount' => $amount, 'effective_at' => now(), 'created_by' => $admin->id]);
            }
        });

        foreach (Venue::with('organization')->orderBy('id')->get() as $venue) {
            // One transaction per story, so a venue with any package already has its whole story.
            if (VenuePackage::where('venue_id', $venue->id)->exists()) {
                continue;
            }
            $ownerId = User::where('email', self::OWNERS[$venue->organization->slug])->firstOrFail()->id;
            DB::transaction(fn () => $this->story($venue, $ownerId));
        }
    }

    private function story(Venue $venue, int $ownerId): void
    {
        switch ($venue->slug) {
            case 'aura-arena-quan-ngua':
                $this->pay($venue, $ownerId, PackageTerm::Days180, $this->day(35, 10, 0));
                break;
            case 'aura-complex-tay-ho':
                // The first transfer lands after the 10-minute window, so the owner pays again on a new request.
                $this->pay($venue, $ownerId, PackageTerm::Days90, $this->day(34, 9, 0), 12, PackagePaymentReceipt::OUTCOME_LATE);
                $this->pay($venue, $ownerId, PackageTerm::Days90, $this->day(34, 9, 20));
                break;
            case 'aura-landmark-72':
                // A renewal keeps the term, so the owner terminates the 30-day package to buy 360 days.
                $this->pay($venue, $ownerId, PackageTerm::Days30, $this->day(35, 9, 30));
                $this->at($this->day(20, 10, 0), fn () => app(TerminateVenuePackage::class)
                    ->execute(new TerminateVenuePackageData($venue->organization_id, $venue->id, true, $ownerId)));
                $this->pay($venue, $ownerId, PackageTerm::Days360, $this->day(20, 10, 5));
                break;
            case 'aura-central-saigon':
                // Paid 28 days ago at 20:00 for 30 days, so at any seed hour it ends within three days and shows D11's 3-day reminder.
                $this->pay($venue, $ownerId, PackageTerm::Days30, $this->day(28, 20, 0));
                break;
            case 'hoang-gia-phu-my-hung':
                $this->pay($venue, $ownerId, PackageTerm::Days90, $this->day(35, 9, 15));
                break;
            default:
                // aura-court, and any venue added later, so every venue keeps an active package.
                $this->pay($venue, $ownerId, PackageTerm::Days360, $this->day(35, 9, 0));
        }
    }

    /** Opens a request at $at and pays it by a SePay transfer $paidAfter minutes later, received a minute after that. */
    private function pay(Venue $venue, int $ownerId, PackageTerm $term, Carbon $at, int $paidAfter = 3, string $outcome = PackagePaymentReceipt::OUTCOME_APPLIED): void
    {
        $request = $this->at($at, fn () => app(CreatePackagePaymentRequest::class)
            ->execute(new CreatePackagePaymentRequestData($venue->organization_id, $venue->id, $term, $ownerId)));
        $paidAt = $at->copy()->addMinutes($paidAfter);
        $transfer = [
            // Not numeric like SePay's ids, so a real transfer after the restore can never be taken for a duplicate.
            'id' => 'seed-'.$request->request_code,
            'gateway' => config('services.sepay.bank'),
            'transactionDate' => $paidAt->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s'),
            'accountNumber' => config('services.sepay.account'),
            'code' => $request->payment_code,
            'content' => $request->payment_code.' phi goi',
            'transferType' => 'in',
            'transferAmount' => $request->amount,
            'referenceCode' => 'FT'.$paidAt->format('ymd').random_int(100000, 999999),
        ];
        $receipt = $this->at($paidAt->copy()->addMinute(), fn () => app(ApplyPackagePayment::class)
            ->execute($request->payment_code, $transfer, json_encode($transfer, JSON_THROW_ON_ERROR)));
        // The action stores any other outcome silently, which would leave the venue without the package the demo needs.
        if ($receipt?->outcome !== $outcome) {
            throw new RuntimeException("Seeded package payment {$request->request_code} at {$venue->slug} ended as ".($receipt->outcome ?? 'a duplicate').", expected {$outcome}.");
        }
    }

    private function day(int $daysAgo, int $hour, int $minute): Carbon
    {
        return $this->now->copy()->subDays($daysAgo)->setTime($hour, $minute);
    }

    /** Runs $callback with the clock at $at so the actions and Eloquent timestamps record that moment. */
    private function at(Carbon $at, callable $callback): mixed
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow($at);
        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
        }
    }
}
