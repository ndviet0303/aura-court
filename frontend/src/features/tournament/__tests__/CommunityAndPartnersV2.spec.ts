import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { i18n } from '@/i18n'
import { useAppearanceStore } from '@/features/appearance/stores/appearanceStore'
import { useAuthStore } from '@/stores/auth'
import TournamentsView from '../views/TournamentsView.vue'
import TournamentBracketView from '../views/TournamentBracketView.vue'
import LiveScoreView from '../views/LiveScoreView.vue'
import MatchmakingView from '../../social/views/MatchmakingView.vue'
import CreateMatchView from '../../social/views/CreateMatchView.vue'
import DropInSessionBillView from '../../social/views/DropInSessionBillView.vue'
import PartnerRegistrationView from '../../partner-registration/views/PartnerRegistrationView.vue'
import PartnerApplicationStatusView from '../../partner-registration/views/PartnerApplicationStatusView.vue'
import PlatformSubscriptionView from '../../platform-subscriptions/views/PlatformSubscriptionView.vue'

// Mock APIs to avoid unhandled network requests
vi.mock('../api/tournamentApi', () => ({
  tournamentApi: {
    getTournaments: vi.fn().mockResolvedValue([]),
    getCircuitLeaderboard: vi.fn().mockResolvedValue([]),
    getMyRegistrations: vi.fn().mockResolvedValue([]),
    getTournament: vi.fn().mockResolvedValue({ id: 1, name: 'Hanoi Open 2026' }),
    getBracket: vi.fn().mockResolvedValue({ rounds: [] }),
    getLiveScore: vi.fn().mockResolvedValue({ score: '0-0' }),
  },
}))

vi.mock('../../social/api/socialApi', () => ({
  socialApi: {
    getMatches: vi.fn().mockResolvedValue({ matches: [] }),
    getPartners: vi.fn().mockResolvedValue({ partners: [] }),
    getStats: vi.fn().mockResolvedValue({ total: 10, open: 5, joined: 2 }),
    getCourts: vi.fn().mockResolvedValue([]),
    getFriends: vi.fn().mockResolvedValue([]),
    getSessionBill: vi.fn().mockResolvedValue({
      bill: {
        id: 1,
        match_id: 101,
        actual_headcount: 4,
        court_duration_minutes: 120,
        court_hourly_rate: 150000,
        shuttlecock_count: 3,
        shuttlecock_unit_price: 30000,
        court_subtotal: 300000,
        shuttlecock_subtotal: 90000,
        total_amount: 390000,
        per_person_amount: 97500,
        is_approximate: false,
        status: 'draft',
      },
    }),
  },
}))

vi.mock('../../partner-registration/api', () => ({
  partnerRegistrationApi: {
    getCurrentApplication: vi.fn().mockResolvedValue({
      id: 1,
      status: 'draft',
      business_name: 'Aura Sport Hub',
      representative_name: 'Nguyen Van A',
    }),
    getApplicationHistory: vi.fn().mockResolvedValue([]),
  },
}))

vi.mock('../../platform-subscriptions/api', () => ({
  platformSubscriptionApi: {
    getPlans: vi.fn().mockResolvedValue([]),
    getCurrentSubscription: vi.fn().mockResolvedValue(null),
  },
}))

describe('Community & Partners V2 Adaptation (Tasks 7.1 - 7.3)', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', component: { template: '<div>Home</div>' } },
      { path: '/tournaments', component: TournamentsView },
      { path: '/tournaments/bracket', component: TournamentBracketView },
      { path: '/tournaments/live', component: LiveScoreView },
      { path: '/matchmaking', component: MatchmakingView },
      { path: '/social/create-match', component: CreateMatchView },
      { path: '/social/matches/:id/bill', component: DropInSessionBillView },
      { path: '/partner-registration', component: PartnerRegistrationView },
      { path: '/partner/application-status', component: PartnerApplicationStatusView },
      { path: '/partner/subscription', component: PlatformSubscriptionView },
    ],
  })

  beforeEach(() => {
    setActivePinia(createPinia())
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Partner User',
      email: 'partner@auracourt.vn',
      role: 'customer',
    } as any
  })

  it('renders TournamentsView and preserves state across V1 <-> V2 switches (Task 7.1)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(TournamentsView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          TournamentCard: true,
          TournamentFilters: true,
          TournamentRegisterModal: true,
          TournamentQuickBracketModal: true,
          CircuitLeaderboardCard: true,
          SkeletonLoader: true,
        },
      },
    })

    expect(wrapper.find('.tournaments-view-page').exists()).toBe(true)

    // Switch to V2
    appearance.setUiVersion('v2')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.tournaments-view-page').exists()).toBe(true)

    // Verify V2 CSS classes exist
    expect(erabookCss).toContain('.tournaments-hero-banner')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .tournament-card')
  })

  it('renders TournamentBracketView and LiveScoreView (Task 7.1)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v2')

    const bracketWrapper = mount(TournamentBracketView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          TournamentBracketHeader: true,
          TournamentPublicBracketTree: true,
          TournamentGroupStandingsTable: true,
          TournamentMatchDetailModal: true,
          SkeletonLoader: true,
        },
      },
    })
    expect(bracketWrapper.find('.tournament-bracket-view').exists()).toBe(true)

    const liveWrapper = mount(LiveScoreView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          LiveFixturesSelector: true,
          LiveMatchScoreboard: true,
          LiveMatchStatsCard: true,
          LivePlayByPlayTicker: true,
          LiveUmpireControlPanel: true,
          AuraButton: true,
          SkeletonLoader: true,
        },
      },
    })
    expect(liveWrapper.find('.live-score-view').exists()).toBe(true)
  })

  it('renders MatchmakingView and preserves state across V1 <-> V2 switches (Task 7.2)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(MatchmakingView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          MatchmakingHero: true,
          MatchmakingFilters: true,
          MatchCard: true,
          MatchJoinModal: true,
          PartnerFinderList: true,
          CommunityFeedPanel: true,
          SocialConnectionsPanel: true,
          SocialModerationModal: true,
          DropInSessionBillModal: true,
          SkeletonLoader: true,
        },
      },
    })

    expect(wrapper.find('.matchmaking-page-view').exists()).toBe(true)

    // Switch to V2
    appearance.setUiVersion('v2')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.matchmaking-page-view').exists()).toBe(true)

    // Verify V2 CSS classes
    expect(erabookCss).toContain('.matchmaking-hero-banner')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .match-card')
  })

  it('renders DropInSessionBillView and preserves bill state across V1 <-> V2 switches (Task 7.2)', async () => {
    await router.push('/social/matches/101/bill')
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(DropInSessionBillView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          AuraButton: true,
        },
      },
    })

    expect(wrapper.find('.drop-in-bill-page').exists()).toBe(true)

    // Switch to V2
    appearance.setUiVersion('v2')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.drop-in-bill-page').exists()).toBe(true)

    // Verify V2 CSS classes for bill summary
    expect(erabookCss).toContain('html[data-ui-version="v2"] .total-summary-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .total-val')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .share-price')
  })

  it('renders Partner and Platform Subscription views across V1 and V2 (Task 7.3)', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const regWrapper = mount(PartnerRegistrationView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          BusinessInfoStep: true,
          DocumentUploadStep: true,
          ConsentAndReviewStep: true,
        },
      },
    })
    expect(regWrapper.find('.partner-registration-view').exists()).toBe(true)

    appearance.setUiVersion('v2')
    await regWrapper.vm.$nextTick()
    expect(regWrapper.find('.partner-registration-view').exists()).toBe(true)

    const statusWrapper = mount(PartnerApplicationStatusView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          ApplicationHistoryTimeline: true,
        },
      },
    })
    expect(statusWrapper.find('.partner-status-view').exists()).toBe(true)

    const subWrapper = mount(PlatformSubscriptionView, {
      global: {
        plugins: [router, i18n],
        stubs: {
          PlanCatalogCard: true,
          SubscriptionStatusCard: true,
        },
      },
    })
    expect(subWrapper.find('.platform-subscription-view').exists()).toBe(true)

    // Verify V2 CSS classes
    expect(erabookCss).toContain('html[data-ui-version="v2"] .plan-catalog-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .subscription-status-card')
  })
})
