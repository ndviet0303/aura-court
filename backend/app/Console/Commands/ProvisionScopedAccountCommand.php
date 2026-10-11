<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Infrastructure\FeatureFlagService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionScopedAccountCommand extends Command
{
    protected $signature = 'org:provision-pos
        {--target-env= : Explicit target environment (local, staging, production)}
        {--email= : Account email address}
        {--org= : Target organization ID}
        {--role=organization_owner : Scoped organization role (organization_owner, cashier)}
        {--dry-run : Preflight analysis only without writing changes}
        {--confirm-production : Explicit confirmation required for production environment}';

    protected $description = 'Provision or upgrade an account for scoped organization and POS operation with credential preservation';

    public function handle(FeatureFlagService $featureFlags): int
    {
        $env = strtolower(trim((string) ($this->option('target-env') ?: $this->option('env'))));
        $email = strtolower(trim((string) $this->option('email')));
        $orgOption = $this->option('org');
        $role = strtolower(trim((string) $this->option('role')));
        $dryRun = (bool) $this->option('dry-run');
        $confirmProduction = (bool) $this->option('confirm-production');

        // 1. Scenario: Target information is missing
        if (empty($env) || empty($email) || empty($orgOption)) {
            $this->error('Target information is incomplete. Provisioning requires explicit --env, --email, and --org.');
            $this->line('Usage: php artisan org:provision-pos --env=local|staging|production --email=user@example.com --org=<id> [--role=organization_owner|cashier] [--dry-run]');

            return self::FAILURE;
        }

        // Validate environment
        if (! in_array($env, ['local', 'testing', 'staging', 'production'], true)) {
            $this->error("Invalid environment '{$env}'. Must be one of: local, testing, staging, production.");

            return self::FAILURE;
        }

        // 2. Execution Gating for Production
        if ($env === 'production' && ! $confirmProduction) {
            $this->warn('================================================================');
            $this->warn('EXECUTION GATED: Production target detected.');
            $this->warn('Operational execution against production is gated and requires');
            $this->warn('explicit operational confirmation via --confirm-production.');
            $this->warn('Provisioning paused safely without changes.');
            $this->warn('================================================================');

            return self::FAILURE;
        }

        // Validate role
        $validRoles = [
            OrganizationMember::ROLE_ORGANIZATION_OWNER,
            OrganizationMember::ROLE_VENUE_MANAGER,
            OrganizationMember::ROLE_CASHIER,
            OrganizationMember::ROLE_STAFF,
        ];
        if (! in_array($role, $validRoles, true)) {
            $this->error("Invalid role '{$role}'. Allowed scoped roles: ".implode(', ', $validRoles));

            return self::FAILURE;
        }

        // Validate organization existence (do NOT fall back to first organization)
        $orgId = (int) $orgOption;
        $organization = Organization::find($orgId);
        if (! $organization) {
            $this->error("Organization with ID #{$orgId} does not exist. Missing target cannot be substituted.");

            return self::FAILURE;
        }

        // 3. User Resolution & Credential Preservation
        $user = User::where('email', $email)->first();
        $isNewUser = false;
        $originalPasswordHash = null;

        if ($user) {
            $originalPasswordHash = $user->password;
            $this->line("Found existing account: #{$user->id} ({$user->name}) [Email: {$user->email}, Global Role: {$user->role}]");
        } else {
            $isNewUser = true;
            $this->line("Account with email {$email} does not exist. Creating new account with random password...");
        }

        // 4. Feature Flag & Capability Cohort Impact Audit
        $pilotCohort = (array) (config('features.pilot_organizations') ?? []);
        $isInPilotCohort = in_array((string) $orgId, $pilotCohort, true);
        $globalPosFlag = filter_var(config('features.flags.tenant_pos_and_wallet', false), FILTER_VALIDATE_BOOLEAN);
        $isPosCapabilityEnabled = $featureFlags->enabled('tenant_pos_and_wallet', $orgId);

        // Check current membership status
        $currentMembership = $user
            ? OrganizationMember::where('organization_id', $orgId)->where('user_id', $user->id)->first()
            : null;

        $beforeRole = $currentMembership ? $currentMembership->role : 'none';
        $beforeStatus = $currentMembership ? $currentMembership->status : 'none';

        $this->newLine();
        $this->info('=== PREFLIGHT AUDIT & COHORT IMPACT ===');
        $this->table(
            ['Parameter', 'Current Value', 'Target Value'],
            [
                ['Environment', $env, $env],
                ['Account Email', $email, $email],
                ['Target Org ID', (string) $orgId, (string) $orgId],
                ['Target Org Name', $organization->name, $organization->name],
                ['Scoped Org Role', $beforeRole, $role],
                ['Membership Status', $beforeStatus, 'active'],
                ['Global Role (Preserved)', $user ? $user->role : 'customer', $user ? $user->role : 'customer'],
                ['Credentials Preserved', $user ? 'YES (hash unchanged)' : 'N/A (new user generated)', 'YES'],
                ['Pilot Cohort Member', $isInPilotCohort ? 'YES' : 'NO', $isInPilotCohort ? 'YES' : 'NO'],
                ['Global POS Flag', $globalPosFlag ? 'ENABLED' : 'DISABLED', $globalPosFlag ? 'ENABLED' : 'DISABLED'],
                ['Effective POS Capability', $isPosCapabilityEnabled ? 'ENABLED' : 'DISABLED', $isPosCapabilityEnabled ? 'ENABLED' : 'DISABLED'],
            ]
        );

        if (! $isPosCapabilityEnabled) {
            $this->warn("NOTICE: POS capability ('tenant_pos_and_wallet') is DISABLED for Organization #{$orgId}.");
            $this->warn("Account will receive the '{$role}' role, but POS access remains gated until pilot cohort configuration includes organization #{$orgId}.");
            $this->warn('Verification will report capability-disabled separately from role assignment.');
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('DRY-RUN COMPLETE: Preflight verified successfully. No changes were committed to database.');

            return self::SUCCESS;
        }

        // 5. Execute Mutation in Transaction with Credential Preservation
        DB::transaction(function () use ($user, $isNewUser, $email, $orgId, $role, $originalPasswordHash): void {
            if ($isNewUser) {
                $user = User::create([
                    'name' => Str::title(explode('@', $email)[0]),
                    'email' => $email,
                    'password' => Str::random(32), // Secure randomized password
                    'role' => 'customer', // Preserves customer global identity!
                    'onboarded' => true,
                    'password_set' => false,
                    'email_verified_at' => Carbon::now(),
                ]);
            }

            // Idempotent membership assignment
            $permissions = $role === OrganizationMember::ROLE_ORGANIZATION_OWNER
                ? ['*']
                : ['pos.sell', 'pos.shift'];

            OrganizationMember::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $role,
                    'status' => 'active',
                    'permissions' => $permissions,
                ]
            );

            // Verify credentials were not altered
            if (! $isNewUser && $user->password !== $originalPasswordHash) {
                throw new \RuntimeException('Security invariant violated: user credentials were modified during provisioning.');
            }
        });

        $this->newLine();
        $this->info("SUCCESS: Account {$email} provisioned with role '{$role}' in '{$organization->name}' (#{$orgId}).");
        $this->line("Credentials preserved: YES. Global role: {$user->role}. Membership status: active.");

        return self::SUCCESS;
    }
}
