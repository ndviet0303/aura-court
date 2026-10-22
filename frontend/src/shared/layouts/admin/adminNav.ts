/** Admin sidebar entries; AdminLayout filters them by role and by the tenant's capabilities. */
export interface NavItem {
  id: string
  path: string
  labelKey: string
  icon: string
  matchExact?: boolean
  badgeKey?: string
  /** Tenant capability the item needs; the route meta carries the same flag. */
  capability?: string
}

export interface NavGroup {
  id: string
  titleKey: string
  icon: string
  items: NavItem[]
}

// Standalone top item
export const dashboardItem: NavItem = {
  id: 'dashboard',
  path: '/admin',
  labelKey: 'admin.nav.dashboard',
  icon: 'dashboard',
  matchExact: true,
}

// Grouped collapsible menus
export const navGroups: NavGroup[] = [
  {
    id: 'courts',
    titleKey: 'admin.nav.courtsGroup',
    icon: 'sports_tennis',
    items: [
      {
        id: 'courts-manage',
        path: '/admin/manage',
        labelKey: 'admin.nav.manageCourts',
        icon: 'grid_view',
      },
      {
        id: 'courts-schedule',
        path: '/admin/schedule',
        labelKey: 'admin.nav.scheduleManage',
        icon: 'schedule',
      },
      {
        id: 'courts-contracts',
        path: '/admin/contracts',
        labelKey: 'admin.nav.recurringContracts',
        icon: 'contract',
      },
      {
        id: 'courts-venues',
        path: '/admin/venues',
        labelKey: 'admin.nav.manageVenues',
        icon: 'location_on',
      },
      {
        id: 'courts-affected',
        path: '/admin/affected-bookings',
        labelKey: 'admin.nav.affectedBookings',
        icon: 'event_busy',
      },
      {
        id: 'courts-pricing',
        path: '/admin/pricing',
        labelKey: 'admin.nav.pricingMatrix',
        icon: 'price_change',
      },
      {
        id: 'courts-promotions',
        path: '/admin/promotions',
        labelKey: 'admin.nav.promotionPrograms',
        icon: 'sell',
      },
    ],
  },
  {
    id: 'pos',
    titleKey: 'admin.nav.posGroup',
    icon: 'point_of_sale',
    items: [
      {
        id: 'pos-retail',
        path: '/admin/pos',
        labelKey: 'admin.nav.posRetail',
        icon: 'point_of_sale',
      },
      {
        id: 'pos-shifts',
        path: '/admin/shifts',
        labelKey: 'admin.nav.shiftsReconciliation',
        icon: 'receipt_long',
      },
      {
        id: 'pos-inventory',
        path: '/admin/inventory',
        labelKey: 'admin.nav.inventory',
        icon: 'inventory_2',
      },
      {
        id: 'pos-membership',
        path: '/admin/membership',
        labelKey: 'admin.nav.membership',
        icon: 'card_membership',
      },
    ],
  },
  {
    id: 'finance',
    titleKey: 'admin.nav.financeGroup',
    icon: 'account_balance_wallet',
    items: [
      {
        id: 'finance-payouts',
        path: '/admin/payouts',
        labelKey: 'admin.nav.payouts',
        icon: 'account_balance_wallet',
        capability: 'marketplace_payouts',
      },
      {
        id: 'finance-reconciliation',
        path: '/admin/reconciliation',
        labelKey: 'admin.nav.reconciliationAlerts',
        icon: 'analytics',
        capability: 'marketplace_payouts',
      },
      {
        id: 'finance-exceptions',
        path: '/admin/financial-exceptions',
        labelKey: 'admin.nav.financialExceptions',
        icon: 'shield',
        capability: 'marketplace_payouts',
      },
      {
        id: 'finance-payment-exceptions',
        path: '/admin/payment-exceptions',
        labelKey: 'admin.nav.paymentExceptions',
        icon: 'report',
        capability: 'direct_owner_payments',
      },
      // No capability: AdminLayout shows unlisted paths to platform admins only.
      {
        id: 'finance-platform-fees',
        path: '/admin/platform-fees',
        labelKey: 'admin.nav.platformFees',
        icon: 'sell',
      },
    ],
  },
  {
    id: 'tournaments',
    titleKey: 'admin.nav.tournamentGroup',
    icon: 'emoji_events',
    items: [
      {
        id: 'tournaments-bracket',
        path: '/admin/tournaments',
        labelKey: 'admin.nav.tournaments',
        icon: 'account_tree',
      },
    ],
  },
  {
    id: 'organization',
    titleKey: 'admin.nav.orgGroup',
    icon: 'corporate_fare',
    items: [
      {
        id: 'org-profile',
        path: '/admin/organization',
        labelKey: 'admin.nav.organization',
        icon: 'corporate_fare',
      },
      { id: 'org-staff', path: '/admin/staff', labelKey: 'admin.nav.staffManage', icon: 'badge' },
      {
        id: 'org-packages',
        path: '/admin/packages',
        labelKey: 'admin.nav.packages',
        icon: 'workspace_premium',
      },
      {
        id: 'org-finance',
        path: '/admin/finance',
        labelKey: 'admin.nav.ownerFinance',
        icon: 'monitoring',
      },
      {
        id: 'org-kyc',
        path: '/admin/kyc',
        labelKey: 'admin.nav.kycVerification',
        icon: 'verified_user',
      },
      { id: 'org-users', path: '/admin/users', labelKey: 'admin.nav.users', icon: 'group' },
      {
        id: 'org-monitoring',
        path: '/admin/monitoring/venues',
        labelKey: 'admin.nav.venueMonitoring',
        icon: 'travel_explore',
      },
      {
        id: 'org-remediations',
        path: '/admin/venue-remediations',
        labelKey: 'admin.nav.venueRemediations',
        icon: 'fact_check',
      },
      {
        id: 'org-disputes',
        path: '/admin/disputes',
        labelKey: 'admin.nav.disputes',
        icon: 'gavel',
      },
    ],
  },
]

// Owner-only: courts, contracts, venues, pricing, promotions, membership, organization profile & staff,
// payment exceptions, venue packages, finance
export const OWNER_ONLY_PATHS = [
  '/admin/manage',
  '/admin/contracts',
  '/admin/pricing',
  '/admin/promotions',
  '/admin/membership',
  '/admin/organization',
  '/admin/staff',
  '/admin/payment-exceptions',
  '/admin/packages',
  '/admin/finance',
]

// Owner or staff: schedule, venues (staff read-only), POS, shifts, inventory, tournaments
export const OWNER_OR_STAFF_PATHS = [
  '/admin/schedule',
  '/admin/venues',
  '/admin/affected-bookings',
  '/admin/pos',
  '/pos',
  '/admin/shifts',
  '/admin/inventory',
  '/admin/tournaments',
]
