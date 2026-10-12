-- Preflight for openspec change harden-db-integrity (tasks 1.3, 2.1, 8.3).
-- Read-only. Run with psql against the target database:
--   PGOPTIONS='-c default_transaction_read_only=on' psql -X -f docs/operations/harden-db-integrity-preflight.sql
-- Every "must be 0" row has to return 0 before the migrations are deployed.

\echo '== overlapping active reservation_slots (must be 0)'
SELECT count(*) AS overlap_pairs
FROM reservation_slots a
JOIN reservation_slots b
  ON a.court_id = b.court_id
 AND a.id < b.id
 AND a.reservation_id <> b.reservation_id
 AND tsrange(a.starts_at, a.ends_at, '[)') && tsrange(b.starts_at, b.ends_at, '[)')
WHERE a.status IN ('held', 'confirmed')
  AND b.status IN ('held', 'confirmed');

\echo '== active slots under a cancelled/expired reservation (must be 0)'
SELECT count(*) AS active_slots_under_terminal_reservation
FROM reservation_slots s
JOIN reservations r ON r.id = s.reservation_id
WHERE s.status IN ('held', 'confirmed')
  AND r.status IN ('cancelled', 'expired');

\echo '== rows in tables dropped by the change (must be 0 before, dropped after)'
SELECT t AS table_name,
       CASE WHEN to_regclass(t) IS NULL THEN 'dropped'
            ELSE (xpath('/row/c/text()', query_to_xml(format('SELECT count(*) AS c FROM %I', t), false, true, '')))[1]::text
       END AS row_count
FROM unnest(ARRAY['memberships', 'membership_plans', 'reserves']) AS t;

\echo '== btree_gist availability'
SELECT name, default_version, installed_version FROM pg_available_extensions WHERE name = 'btree_gist';
SELECT has_database_privilege(current_user, current_database(), 'CREATE') AS can_create_extension;

\echo '== foreign keys without an index on their leading columns'
SELECT c.conrelid::regclass AS table_name,
       a.attname AS column_name,
       c.conname AS constraint_name
FROM pg_constraint c
JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
WHERE c.contype = 'f'
  AND c.connamespace = 'public'::regnamespace
  AND NOT EXISTS (
    SELECT 1 FROM pg_index i
    WHERE i.indrelid = c.conrelid
      AND (i.indkey::int2[])[0:array_length(c.conkey, 1) - 1] = c.conkey
  )
ORDER BY 1, 2;

\echo '== CASCADE foreign keys from financial tables (45 before the change, 0 after)'
SELECT c.conrelid::regclass AS table_name, a.attname AS column_name, c.conname AS constraint_name
FROM pg_constraint c
JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
WHERE c.contype = 'f'
  AND c.confdeltype = 'c'
  AND c.conrelid::regclass::text IN (
    'accounts', 'audit_logs', 'beneficiaries', 'cash_shifts', 'elevated_audit_logs', 'entitlement_transfers',
    'finance_exceptions', 'journal_entries', 'journals', 'match_bills', 'monthly_invoices',
    'owner_payment_allocations', 'owner_payment_attempts', 'owner_payment_receipts', 'owner_refund_allocations',
    'owner_refund_evidence', 'owner_refund_obligations', 'payment_intents', 'payments', 'payouts',
    'platform_payment_attempts', 'platform_provider_receipts', 'platform_subscriptions', 'point_ledgers',
    'pos_order_items', 'pos_orders', 'pos_payments', 'provider_events', 'receiving_connection_evidence',
    'reconciliation_runs', 'refunds', 'service_tab_items', 'service_tabs', 'settlements', 'shift_reconciliations',
    'split_groups', 'split_shares', 'stock_movements', 'wallet_accounts', 'wallet_transactions'
  )
ORDER BY 1, 2;

\echo '== CHECK constraints / exclusion constraint'
SELECT count(*) FILTER (WHERE contype = 'c' AND conname LIKE '%\_check') AS status_checks,
       count(*) FILTER (WHERE contype = 'x' AND conname = 'reservation_slots_no_overlap') AS exclusion_constraint
FROM pg_constraint
WHERE connamespace = 'public'::regnamespace;

\echo '== distinct values per status column (design section 5)'
SELECT format(
  'SELECT %L AS status_column, string_agg(DISTINCT %I, %L ORDER BY %I) AS distinct_values FROM %I',
  table_name || '.' || column_name, column_name, ',', column_name, table_name
)
FROM information_schema.columns
WHERE table_schema = 'public'
  AND data_type IN ('character varying', 'text')
  AND (column_name = 'status' OR column_name LIKE '%\_status' OR column_name = 'state')
ORDER BY table_name, column_name
\gexec
