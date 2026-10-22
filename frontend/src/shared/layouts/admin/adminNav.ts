export interface NavItem {
  id: string
  path: string
  labelKey: string
  icon: string
  matchExact?: boolean
  badgeKey?: string
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
      },
      {
        id: 'finance-reconciliation',
        path: '/admin/reconciliation',
        labelKey: 'admin.nav.reconciliationAlerts',
        icon: 'analytics',
      },
      {
        id: 'finance-exceptions',
        path: '/admin/financial-exceptions',
        labelKey: 'admin.nav.financialExceptions',
        icon: 'shield',
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
        id: 'org-kyc',
        path: '/admin/kyc',
        labelKey: 'admin.nav.kycVerification',
        icon: 'verified_user',
      },
      { id: 'org-users', path: '/admin/users', labelKey: 'admin.nav.users', icon: 'group' },
      {
        id: 'org-disputes',
        path: '/admin/disputes',
        labelKey: 'admin.nav.disputes',
        icon: 'gavel',
      },
    ],
  },
]

// Owner-only: courts, contracts, venues, pricing, promotions, membership, organization profile & staff
export const OWNER_ONLY_PATHS = [
  '/admin/manage',
  '/admin/contracts',
  '/admin/venues',
  '/admin/pricing',
  '/admin/promotions',
  '/admin/membership',
  '/admin/organization',
  '/admin/staff',
]

// Owner or staff: schedule, POS, shifts, inventory, tournaments
export const OWNER_OR_STAFF_PATHS = [
  '/admin/schedule',
  '/admin/pos',
  '/pos',
  '/admin/shifts',
  '/admin/inventory',
  '/admin/tournaments',
]
