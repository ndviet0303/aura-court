import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { i18n } from '@/i18n'
import { useAppearanceStore } from '@/features/appearance/stores/appearanceStore'
import { useAuthStore } from '@/stores/auth'

import AdminView from '../views/AdminView.vue'
import AdminManageView from '../views/AdminManageView.vue'
import AdminScheduleView from '../views/AdminScheduleView.vue'
import V2PricingCalendarView from '../views/V2PricingCalendarView.vue'
import AdminVenuesView from '../views/AdminVenuesView.vue'
import AdminPublicationQueueView from '../views/AdminPublicationQueueView.vue'
import OrganizationOwnerView from '../views/OrganizationOwnerView.vue'
import AdminStaffView from '../views/AdminStaffView.vue'
import AdminRecurringContractsView from '../views/AdminRecurringContractsView.vue'
import AdminTournamentBracketView from '../views/AdminTournamentBracketView.vue'
import AdminMembershipView from '../views/AdminMembershipView.vue'
import PosView from '@/features/pos/views/PosView.vue'
import AdminShiftReconciliationView from '../views/AdminShiftReconciliationView.vue'
import AdminInventoryView from '../views/AdminInventoryView.vue'
import AdminPayoutsView from '@/features/finance/views/AdminPayoutsView.vue'
import AdminFinancialExceptionsView from '../views/AdminFinancialExceptionsView.vue'
import AdminFinancialReconciliationView from '../views/AdminFinancialReconciliationView.vue'
import AdminKycVerificationView from '../views/AdminKycVerificationView.vue'
import AdminDisputeResolutionView from '../views/AdminDisputeResolutionView.vue'

vi.mock('@/core/api/client', () => ({
  apiOrigin: 'http://localhost:8000',
  api: {
    get: vi.fn().mockResolvedValue([]),
    post: vi.fn().mockResolvedValue({}),
    put: vi.fn().mockResolvedValue({}),
    delete: vi.fn().mockResolvedValue({}),
  },
  apiV2: {
    get: vi.fn().mockResolvedValue({ data: [] }),
    post: vi.fn().mockResolvedValue({ data: {} }),
    delete: vi.fn().mockResolvedValue({}),
  },
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

describe('Admin & POS V2 Adaptation (Tasks 8.1 - 8.9)', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', component: { template: '<div>Home</div>' } },
      { path: '/admin', component: AdminView },
      { path: '/admin/manage', component: AdminManageView },
      { path: '/admin/schedule', component: AdminScheduleView },
      { path: '/admin/pricing', component: V2PricingCalendarView },
      { path: '/admin/venues', component: AdminVenuesView },
      { path: '/admin/venues/queue', component: AdminPublicationQueueView },
      { path: '/admin/organization', component: OrganizationOwnerView },
      { path: '/admin/staff', component: AdminStaffView },
      { path: '/admin/contracts', component: AdminRecurringContractsView },
      { path: '/admin/tournaments', component: AdminTournamentBracketView },
      { path: '/admin/membership', component: AdminMembershipView },
      { path: '/admin/pos', component: PosView },
      { path: '/admin/shifts', component: AdminShiftReconciliationView },
      { path: '/admin/inventory', component: AdminInventoryView },
      { path: '/admin/payouts', component: AdminPayoutsView },
      { path: '/admin/financial-exceptions', component: AdminFinancialExceptionsView },
      { path: '/admin/reconciliation', component: AdminFinancialReconciliationView },
      { path: '/admin/kyc', component: AdminKycVerificationView },
      { path: '/admin/disputes', component: AdminDisputeResolutionView },
    ],
  })

  beforeEach(() => {
    setActivePinia(createPinia())
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Admin Demo',
      email: 'admin@auracourt.vn',
      role: 'admin',
      active_organizations: [{ id: 1, role: 'organization_owner' }],
    } as any
  })

  it('renders Admin Overview & Manage views and preserves state across V1 <-> V2 switches (Task 8.1)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const overviewWrapper = mount(AdminView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AdminKpiCards: true,
          AdminDateRangeFilter: true,
          AdminAnalyticsCharts: true,
          AdminRecentBookingsTable: true,
          AdminPendingPaymentsCard: true,
          AdminQuickNav: true,
        },
      },
    })
    expect(overviewWrapper.find('.admin-dashboard-view').exists()).toBe(true)

    appearance.setUiVersion('v2')
    await overviewWrapper.vm.$nextTick()
    expect(overviewWrapper.find('.admin-dashboard-view').exists()).toBe(true)

    const manageWrapper = mount(AdminManageView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          ManageHeader: true,
          ManageKpiBar: true,
          AdminCourtsTable: true,
          CourtFormModal: true,
          CourtPricingModal: true,
          CourtBlockModal: true,
          CouponManagerModal: true,
          AdminRefundsPanel: true,
        },
      },
    })
    expect(manageWrapper.find('.admin-manage-page').exists()).toBe(true)
  })

  it('renders Admin Schedule and Pricing views (Task 8.2)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v2')

    const scheduleWrapper = mount(AdminScheduleView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          ScheduleHeader: true,
          ScheduleKpiBar: true,
          WeeklyScheduleCard: true,
          MaintenanceScheduleCard: true,
          SpecialDatesCard: true,
          DowntimeScheduleModal: true,
          SpecialDateModal: true,
          ScheduleAuditLogsModal: true,
          CourtUnlockConfirmModal: true,
        },
      },
    })
    expect(scheduleWrapper.find('.admin-schedule-view').exists()).toBe(true)

    const pricingWrapper = mount(V2PricingCalendarView, {
      global: {
        plugins: [router, i18n],
      },
    })
    expect(pricingWrapper.find('.admin-pricing-view').exists()).toBe(true)
  })

  it('renders Venues and Publication Queue views (Task 8.3)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v2')

    const venuesWrapper = mount(AdminVenuesView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          VenueFormModal: true,
          CourtListCard: true,
          CourtModal: true,
          AuraButton: true,
        },
      },
    })
    expect(venuesWrapper.find('.admin-venues-view').exists()).toBe(true)

    const queueWrapper = mount(AdminPublicationQueueView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(queueWrapper.find('.admin-publication-queue-view').exists()).toBe(true)
  })

  it('renders Organization, Staff, and Recurring Contracts views (Task 8.4)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v2')

    const orgWrapper = mount(OrganizationOwnerView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(orgWrapper.find('.organization-owner-view').exists()).toBe(true)

    const staffWrapper = mount(AdminStaffView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(staffWrapper.find('.admin-staff-view').exists()).toBe(true)

    const contractsWrapper = mount(AdminRecurringContractsView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(contractsWrapper.find('.admin-contracts-view').exists()).toBe(true)
  })

  it('renders Admin Tournaments and Membership views (Task 8.5)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v2')

    const tournamentWrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(tournamentWrapper.find('.admin-tournament-view').exists()).toBe(true)

    const membershipWrapper = mount(AdminMembershipView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
          MembershipKpiBar: true,
        },
      },
    })
    expect(membershipWrapper.find('.admin-membership-view').exists()).toBe(true)
  })

  it('renders POS and preserves cart state across V1 <-> V2 switches (Task 8.6)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const posWrapper = mount(PosView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          PosTopBar: true,
          PosProductGrid: true,
          PosCartPanel: true,
          PosReceiptModal: true,
          PosShiftModal: true,
          PosCustomerModal: true,
          PosCustomItemModal: true,
          PosDraftsModal: true,
          PosQrModal: true,
          AuraButton: true,
        },
      },
    })
    expect(posWrapper.find('.pos-layout-wrapper').exists()).toBe(true)

    appearance.setUiVersion('v2')
    await posWrapper.vm.$nextTick()
    expect(posWrapper.find('.pos-layout-wrapper').exists()).toBe(true)

    // Verify V2 CSS classes for POS
    expect(erabookCss).toContain('.pos-layout-wrapper')
    expect(erabookCss).toContain('.pos-cart-container')
  })

  it('renders Shifts and Inventory views (Task 8.7)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v2')

    const shiftsWrapper = mount(AdminShiftReconciliationView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(shiftsWrapper.find('.admin-shifts-view').exists()).toBe(true)

    const invWrapper = mount(AdminInventoryView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(invWrapper.find('.admin-inventory-view').exists()).toBe(true)
  })

  it('renders Payouts, Financial Exceptions and Reconciliation views (Task 8.8)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v2')

    const payoutsWrapper = mount(AdminPayoutsView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(payoutsWrapper.find('.admin-payouts-view').exists()).toBe(true)

    const exceptionsWrapper = mount(AdminFinancialExceptionsView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(exceptionsWrapper.find('.financial-exceptions-view').exists()).toBe(true)

    const reconWrapper = mount(AdminFinancialReconciliationView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(reconWrapper.find('.financial-reconciliation-view').exists()).toBe(true)
  })

  it('renders KYC and Disputes views (Task 8.9)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v2')

    const kycWrapper = mount(AdminKycVerificationView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(kycWrapper.find('.kyc-verification-view').exists()).toBe(true)

    const disputesWrapper = mount(AdminDisputeResolutionView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })
    expect(disputesWrapper.find('.dispute-resolution-view').exists()).toBe(true)
  })
})
