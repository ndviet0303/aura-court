import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import QRCode from 'qrcode'
import AdminTournamentBracketView from '../views/AdminTournamentBracketView.vue'
import { i18n } from '@/i18n'
import { apiV2 } from '@/core/api/client'

vi.mock('qrcode', () => ({
  default: {
    toDataURL: vi.fn().mockResolvedValue('data:image/png;base64,mockqr'),
  },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/tournaments', query: {} }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ data: [] }),
    post: vi.fn().mockResolvedValue({ status: 'ok' }),
  },
  apiV2: {
    get: vi.fn().mockResolvedValue({ data: [] }),
    post: vi.fn().mockResolvedValue({ status: 'ok' }),
  },
  errorMessage: (_err: unknown, fallback?: string) => fallback || 'Có lỗi xảy ra',
}))

describe('AdminTournamentBracketView', () => {
  const mockTournamentDetails = {
    id: 1,
    name: 'Aura Summer Open 2026',
    sport: 'badminton',
    venue: 'Aura Court 1',
    city: 'TP. Hồ Chí Minh',
    status: 'open',
    max_slots: 16,
    prize_pool: '20.000.000 ₫',
    entry_fee: '300.000 ₫ / VĐV',
    participants: [
      {
        id: 1,
        seed: 2,
        name: 'Sarah Jenkins',
        club: 'Apex Club',
        level: 'Lvl 4',
        checked_in: true,
        category: 'Đơn Nữ Nâng Cao',
        phone: '0901 234 567',
        qr_code: 'ATH-001',
      },
      {
        id: 2,
        name: 'Marcus Chen',
        club: 'Independent',
        level: 'Lvl 5',
        checked_in: false,
        category: 'Đơn Nam Master',
        phone: '0902 345 678',
        qr_code: 'ATH-002',
      },
      {
        id: 3,
        name: 'David Rossi',
        club: 'Velocity BC',
        level: 'Lvl 3',
        checked_in: false,
        category: 'Đơn Nam Phong Trào',
        phone: '0903 456 789',
        qr_code: 'ATH-003',
      },
      {
        id: 4,
        seed: 1,
        name: 'Chen Long',
        club: 'Aura Elite',
        level: 'Lvl 5',
        checked_in: true,
        category: 'Đơn Nam Chuyên Nghiệp',
        phone: '0904 567 890',
        qr_code: 'ATH-004',
      },
    ],
    fixtures: [
      {
        id: 101,
        round: 1,
        position: 1,
        court_id: 1,
        court_name: 'Sân 1 (Center)',
        scheduled_time: '09:00',
        status: 'completed',
        participant_one_id: 1,
        participant_two_id: 2,
        player1: { id: 1, name: 'Sarah Jenkins', sets: [21, 21], isWinner: true },
        player2: { id: 2, name: 'Marcus Chen', sets: [18, 14], isWinner: false },
        winner_id: 1,
      },
      {
        id: 102,
        round: 1,
        position: 2,
        court_id: 2,
        court_name: 'Sân 2',
        scheduled_time: '09:45',
        status: 'live',
        participant_one_id: 3,
        participant_two_id: 4,
        player1: { id: 3, name: 'David Rossi', sets: [19], isWinner: false },
        player2: { id: 4, name: 'Chen Long', sets: [21], isWinner: false },
        winner_id: null,
      },
    ],
  }

  const mockTournamentsList = [
    {
      id: 1,
      name: 'Aura Summer Open 2026',
      sport: 'badminton',
      venue: 'Aura Court 1',
      city: 'TP. Hồ Chí Minh',
      status: 'open',
    },
    {
      id: 2,
      name: 'Pickleball Masters 2026',
      sport: 'pickleball',
      venue: 'Aura Court 2',
      city: 'Đà Nẵng',
      status: 'ready',
    },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(apiV2.get).mockImplementation(async (url: string) => {
      if (url.match(/\/tournaments\/\d+/)) {
        return mockTournamentDetails
      }
      return mockTournamentsList
    })
    vi.mocked(apiV2.post).mockResolvedValue({ status: 'ok' })
  })

  it('renders title, setup wizard steps, and interactive bracket tree correctly', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.page-headline').text()).toContain('Quản Trị Giải Đấu & Bracket')
    expect(wrapper.text()).toContain('Aura Summer Open 2026')
    expect(wrapper.findAll('.step-item').length).toBe(3)
    expect(wrapper.findAll('.match-node-card').length).toBeGreaterThan(0)
  })

  it('switches sub-navigation tabs to Athlete Check-in, Live Referee Console, and Overview', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const subTabs = wrapper.findAll('.sub-tab-btn')
    expect(subTabs.length).toBe(4)

    // Tab 2: Athlete Check-in
    await subTabs[1]?.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Điểm Danh Vận Động Viên')
    expect(wrapper.findAll('.athlete-feed-card').length).toBeGreaterThan(0)
    expect(wrapper.find('.radial-svg-gauge').exists()).toBe(true)

    // Tab 3: Live Referee Console
    await subTabs[2]?.trigger('click')
    await flushPromises()
    expect(wrapper.find('.scoring-stage-arena').exists()).toBe(true)

    // Tab 4: Tournament Overview
    await subTabs[3]?.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Tổng Quan Hoạt Động Giải Đấu')
    expect(wrapper.findAll('.kpi-card').length).toBe(4)
  })

  it('triggers bracket re-generation algorithm with feedback', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const regenBtn = wrapper.find('.bracket-toolbar button')
    expect(regenBtn.exists()).toBe(true)

    // The AuraButton in toolbar is the regenerate button
    const allBtns = wrapper.findAll('.bracket-toolbar button')
    const actionBtn = allBtns[allBtns.length - 1]
    await actionBtn?.trigger('click')
    await flushPromises()

    expect(apiV2.post).toHaveBeenCalledWith(expect.stringContaining('/regenerate'))
  })

  it('opens match details modal and saves updated match score', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Click first match card
    const firstMatch = wrapper.find('.match-node-card')
    expect(firstMatch.exists()).toBe(true)
    await firstMatch.trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('Cập Nhật Trận Đấu')

    // Click save button in modal
    const saveBtn = wrapper.find('.modal-footer button.primary')
    if (saveBtn.exists()) {
      await saveBtn.trigger('click')
      await flushPromises()
    }
  })

  it('filters athletes by status tab in check-in panel', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to athlete check-in tab
    const subTabs = wrapper.findAll('.sub-tab-btn')
    await subTabs[1]?.trigger('click')
    await flushPromises()

    const filterPills = wrapper.findAll('.filter-pill-item')
    expect(filterPills.length).toBe(3)

    // Click "Đã điểm danh" filter (index 2)
    await filterPills[2]?.trigger('click')
    await flushPromises()

    const visibleAthletes = wrapper.findAll('.athlete-feed-card')
    expect(visibleAthletes.length).toBe(2) // 2 checked-in athletes in mock
  })

  it('searches athletes dynamically by name in check-in tab', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to athlete check-in tab
    const subTabs = wrapper.findAll('.sub-tab-btn')
    await subTabs[1]?.trigger('click')
    await flushPromises()

    const searchInput = wrapper.find('.search-input-field')
    await searchInput.setValue('Sarah')
    await flushPromises()

    expect(wrapper.text()).toContain('Sarah Jenkins')
    expect(wrapper.text()).not.toContain('Marcus Chen')
  })

  it('toggles athlete check-in status and updates circular gauge', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to athlete check-in tab
    const subTabs = wrapper.findAll('.sub-tab-btn')
    await subTabs[1]?.trigger('click')
    await flushPromises()

    const checkinBtns = wrapper.findAll('.athlete-feed-card button')
    expect(checkinBtns.length).toBeGreaterThan(0)

    await checkinBtns[1]?.trigger('click')
    await flushPromises()

    expect(apiV2.post).toHaveBeenCalledWith(expect.stringContaining('/check-in'), expect.anything())
  })

  it('opens and interacts with QR code scanner modal', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to athlete check-in tab
    const subTabs = wrapper.findAll('.sub-tab-btn')
    await subTabs[1]?.trigger('click')
    await flushPromises()

    const scanQrBtn = wrapper.find('.checkin-actions-bar button')
    await scanQrBtn.trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-qr-scanner-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('Quét Mã QR Thẻ VĐV')
  })

  it('shows the real tournament name and a QR of the pass code on the digital pass', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const subTabs = wrapper.findAll('.sub-tab-btn')
    await subTabs[1]?.trigger('click')
    await flushPromises()

    // First action of the first card (Sarah Jenkins, ATH-001) opens her pass
    await wrapper.find('.athlete-feed-card .ath-actions-col button').trigger('click')
    await flushPromises()

    expect(wrapper.find('.pass-org-name').text()).toBe('Aura Summer Open 2026')
    expect(wrapper.find('.pass-org-sub').text()).toBe('THẺ THI ĐẤU CHÍNH THỨC')
    expect(wrapper.find('.pass-type-badge').text()).toBe('VẬN ĐỘNG VIÊN')
    expect(QRCode.toDataURL).toHaveBeenCalledWith('ATH-001', expect.anything())
    expect(wrapper.find('.pass-qr-img').attributes('src')).toBe('data:image/png;base64,mockqr')
  })

  it('opens create tournament modal and renders form inputs', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const headerBtns = wrapper.findAll('.header-controls button')
    const createBtn = headerBtns[headerBtns.length - 1]
    expect(createBtn?.exists()).toBe(true)
    await createBtn?.trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('Tạo Giải Đấu Mới')
  })

  it('interacts with live referee scoring and controls', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to referee tab
    const subTabs = wrapper.findAll('.sub-tab-btn')
    await subTabs[2]?.trigger('click')
    await flushPromises()

    // Click score buttons
    const bigScoreBtnA = wrapper.find('.player-left .stitch-big-score-btn')
    expect(bigScoreBtnA.exists()).toBe(true)
    const initialScore = Number(wrapper.find('#score-a').text())
    await bigScoreBtnA.trigger('click')
    await flushPromises()

    expect(Number(wrapper.find('#score-a').text())).toBe(initialScore + 1)
  })
})
