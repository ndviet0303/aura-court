<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status CHECK constraints for organizations, partners, venues and tenant programs. host_kycs.tax_status and lease_contract_status hold free text and stay unconstrained.
 * Allowed values are what the code writes and reads plus what existing data holds.
 * Adding a status means a migration that drops and re-adds the constraint.
 */
return new class extends Migration
{
    private const CHECKS = [
        ['organizations', 'status', ['active', 'suspended']],
        ['organizations', 'partner_status', ['approved', 'pending', 'supplement_requested', 'suspended', 'terminated']],
        ['organization_members', 'status', ['active', 'inactive', 'invited', 'suspended']],
        ['organization_invitations', 'status', ['accepted', 'expired', 'pending', 'revoked']],
        ['users', 'status', ['active', 'banned', 'locked', 'suspended']],
        ['venues', 'status', ['active', 'archived', 'closed', 'draft', 'pending_publication', 'published', 'revision_required', 'suspended', 'temporarily_closed']],
        ['venue_publication_revisions', 'status', ['approved', 'pending', 'rejected', 'revision_required']],
        ['receiving_connections', 'status', ['draft', 'pending_verification', 'revoked', 'superseded', 'verified']],
        ['partner_documents', 'status', ['rejected', 'replaced', 'uploaded', 'verified']],
        ['host_kycs', 'status', ['approved', 'draft', 'flagged', 'needs_supplement', 'pending', 'rejected', 'revision_required', 'supplement_requested']],
        ['host_kycs', 'investigation_status', ['clean', 'cleared', 'flagged_duplicate']],
        ['host_kycs', 'napas_match_status', ['matched', 'pending']],
        ['coupons', 'status', ['active', 'draft', 'retired', 'suspended']],
        ['promotion_campaigns', 'status', ['active', 'draft', 'retired', 'suspended']],
        ['loyalty_rules', 'status', ['draft', 'published']],
        ['tenant_customers', 'status', ['active']],
        ['tenant_membership_plans', 'status', ['active', 'archived', 'draft', 'published']],
        ['tenant_memberships', 'status', ['active']],
    ];

    public function up(): void
    {
        foreach (self::CHECKS as [$table, $column, $values]) {
            $allowed = implode(', ', array_map(fn (string $value) => "'{$value}'", $values));
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$allowed}))");
        }
    }

    public function down(): void
    {
        foreach (self::CHECKS as [$table, $column]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_{$column}_check");
        }
    }
};
