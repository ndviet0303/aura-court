import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminTournamentBracketView from '../AdminTournamentBracketView.vue'
import { i18n } from '@/i18n'
import { apiV2 } from '@/core/api/client'

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
  const mockTournamentPayload = {
    id: 1,
    name: 'Aura Summer Open 2026',
    status: 'open',
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
      {
        id: 5,
        seed: 2,
        name: 'Viktor Axelsen',
        club: 'Viking Smash',
        level: 'Lvl 5',
        checked_in: true,
        category: 'Đơn Nam Chuyên Nghiệp',
        phone: '0905 678 901',
        qr_code: 'ATH-005',
      },
    ],
    fixtures: [
      {
        id: 101,
        round: 1,
        round_name: 'Round of 16',
        position: 1,
        match_number: 1,
        court_id: 1,
        scheduled_time: '09:00',
        status: 'completed',
        player1: { id: 4, name: 'Chen Long' },
        player2: { id: 7, name: 'H. S. Prannoy' },
        player1_sets: [21, 21],
        player2_sets: [14, 18],
        winner_id: 4,
      },
      {
        id: 102,
        round: 1,
        round_name: 'Round of 16',
        position: 2,
        match_number: 2,
        court_id: 2,
        scheduled_time: '09:45',
        status: 'completed',
        player1: { id: 8, name: 'L. Sen' },
        player2: { id: 9, name: 'Chou Tien-Chen' },
        player1_sets: [19, 21, 21],
        player2_sets: [21, 17, 16],
        winner_id: 8,
      },
    ],
  }

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(apiV2.get).mockResolvedValue(mockTournamentPayload)
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

    const subNavBtns = wrapper.findAll('.sub-tab-btn')
    expect(subNavBtns.length).toBe(4)

    // Switch to Athlete Check-in
    await subNavBtns[1]?.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Điểm Danh Vận Động Viên')
    expect(wrapper.findAll('.athlete-feed-card').length).toBeGreaterThan(0)
    expect(wrapper.find('.radial-svg-gauge').exists()).toBe(true)

    // Switch to Live Referee Console
    await subNavBtns[2]?.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Live Match')
    expect(wrapper.findAll('.referee-player-card').length).toBe(2)

    // Switch to Overview
    await subNavBtns[3]?.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Tổng Quan Hoạt Động Giải Đấu')
    expect(wrapper.findAll('.kpi-card').length).toBe(4)
  })

  it('triggers bracket re-generation algorithm with feedback', async () => {
    vi.useFakeTimers()
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const regenBtn = wrapper.find('.btn-regenerate-bracket')
    expect(regenBtn.exists()).toBe(true)

    await regenBtn.trigger('click')
    vi.advanceTimersByTime(700)
    await flushPromises()

    expect(wrapper.text()).toContain('Đã tái tạo bảng phân nhánh')
    vi.useRealTimers()
  })

  it('opens match details modal and saves updated match score', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.modal-backdrop').exists()).toBe(false)

    // Click first match card
    const firstMatch = wrapper.find('.match-node-card')
    await firstMatch.trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-backdrop').exists()).toBe(true)
    expect(wrapper.find('.modal-card h2').text()).toContain('Cập Nhật Trận Đấu')

    // Click save
    const confirmBtn = wrapper.find('.btn-confirm')
    await confirmBtn.trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-backdrop').exists()).toBe(false)
  })

  it('filters athletes by status tab (All, Pending, Checked In, No-show)', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to check-in
    await wrapper.findAll('.sub-tab-btn')[1]?.trigger('click')
    await flushPromises()

    const filterPills = wrapper.findAll('.filter-pill-item')
    expect(filterPills.length).toBe(4)

    // Click Checked In
    await filterPills[2]?.trigger('click')
    await flushPromises()
    expect(wrapper.findAll('.athlete-feed-card.is-checked-in').length).toBeGreaterThan(0)

    // Click Pending
    await filterPills[1]?.trigger('click')
    await flushPromises()
    expect(wrapper.findAll('.athlete-feed-card:not(.is-checked-in)').length).toBeGreaterThan(0)
  })

  it('searches athletes dynamically by name or ID in check-in tab', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to check-in
    await wrapper.findAll('.sub-tab-btn')[1]?.trigger('click')
    await flushPromises()

    const searchInput = wrapper.find('.search-input-field')
    await searchInput.setValue('Sarah Jenkins')
    await flushPromises()

    expect(wrapper.text()).toContain('Sarah Jenkins')
    expect(wrapper.text()).toContain('Apex Club')
  })

  it('toggles athlete check-in status and updates circular gauge', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to check-in
    await wrapper.findAll('.sub-tab-btn')[1]?.trigger('click')
    await flushPromises()

    const checkinBtn = wrapper.findAll('.btn-round-checkin')[0]
    expect(checkinBtn?.exists()).toBe(true)

    await checkinBtn?.trigger('click')
    await flushPromises()

    expect(wrapper.find('.alert-pill').exists()).toBe(true)
  })

  it('opens and interacts with QR code scanner modal', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to check-in
    await wrapper.findAll('.sub-tab-btn')[1]?.trigger('click')
    await flushPromises()

    const scanQrBtn = wrapper.find('.btn-scan-qr')
    await scanQrBtn.trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-qr-scanner-card').exists()).toBe(true)
    expect(wrapper.find('.laser-scanline').exists()).toBe(true)

    // Click sample scan
    const sampleBtn = wrapper.find('.btn-sample-card')
    if (sampleBtn.exists()) {
      await sampleBtn.trigger('click')
      await flushPromises()
      expect(wrapper.find('.qr-scan-feedback-box').exists()).toBe(true)
    }
  })

  it('opens digital athlete accreditation pass modal', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to check-in
    await wrapper.findAll('.sub-tab-btn')[1]?.trigger('click')
    await flushPromises()

    const passBtn = wrapper.findAll('.quick-pill-btn')[1]
    if (passBtn) {
      await passBtn.trigger('click')
      await flushPromises()

      expect(wrapper.find('.modal-pass-card').exists()).toBe(true)
      expect(wrapper.find('.digital-badge-container').exists()).toBe(true)
      expect(wrapper.text()).toContain('AURA COURT CONTROL')
    }
  })

  it('increments and decrements score in live referee console', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to referee console
    await wrapper.findAll('.sub-tab-btn')[2]?.trigger('click')
    await flushPromises()

    expect(wrapper.find('.stitch-referee-header-row').exists()).toBe(true)
    const initialScoreP1 = Number(wrapper.find('#score-a').text())
    const bigScoreBtnP1 = wrapper.findAll('.stitch-big-score-btn')[0]

    await bigScoreBtnP1?.trigger('click')
    await flushPromises()

    const newScoreP1 = Number(wrapper.find('#score-a').text())
    expect(newScoreP1).toBe(initialScoreP1 + 1)

    // Decrement score
    const decBtnP1 = wrapper.findAll('.btn-stitch-decrease')[0]
    await decBtnP1?.trigger('click')
    await flushPromises()
    expect(Number(wrapper.find('#score-a').text())).toBe(initialScoreP1)
  })

  it('simulates and resolves score conflict banner in referee desk', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    await wrapper.findAll('.sub-tab-btn')[2]?.trigger('click')
    await flushPromises()

    expect(wrapper.find('#conflict-alert').exists()).toBe(false)

    // Trigger conflict simulation
    const conflictBtn = wrapper.find('.btn-test-conflict')
    await conflictBtn.trigger('click')
    await flushPromises()

    expect(wrapper.find('#conflict-alert').exists()).toBe(true)
    expect(wrapper.text()).toContain('Score Synchronization Conflict')

    // Accept remote score
    const acceptBtn = wrapper.find('.btn-accept-remote')
    await acceptBtn.trigger('click')
    await flushPromises()

    expect(wrapper.find('#conflict-alert').exists()).toBe(false)
  })

  it('switches ends and opens cards/penalties modal', async () => {
    const wrapper = mount(AdminTournamentBracketView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    await wrapper.findAll('.sub-tab-btn')[2]?.trigger('click')
    await flushPromises()

    // Switch ends
    const switchEndsBtn = wrapper.find('.btn-switch-ends')
    await switchEndsBtn.trigger('click')
    await flushPromises()

    expect(wrapper.find('.scoring-stage-arena.is-swapped-sides').exists()).toBe(true)

    // Open Cards modal
    const cardsBtn = wrapper.findAll('.btn-referee-action-sec')[0]
    await cardsBtn?.trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-penalty-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('Thẻ Phạt & Lỗi Trọng Tài')
  })
})
