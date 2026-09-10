import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import VenueReadinessModal from '../components/venues/VenueReadinessModal.vue'
import AdminPublicationQueueView from '../views/AdminPublicationQueueView.vue'
import { venuePublicationApi } from '../api/venuePublicationApi'

vi.mock('../api/venuePublicationApi', () => ({
  venuePublicationApi: {
    getReadiness: vi.fn(),
    submitPublication: vi.fn(),
    setOperationalStatus: vi.fn(),
    getPublicationQueue: vi.fn(),
    getPublicationRevision: vi.fn(),
    reviewPublicationRevision: vi.fn(),
  },
}))

describe('VenueReadinessModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders readiness checklist and triggers submit publication when ready', async () => {
    vi.mocked(venuePublicationApi.getReadiness).mockResolvedValueOnce({
      data: {
        is_ready: true,
        missing_items: [],
        checklist: {
          partner_active: true,
          subscription_entitled: true,
          venue_metadata: true,
          active_courts: true,
          court_pricing: true,
          policies_configured: true,
        },
      },
    })

    vi.mocked(venuePublicationApi.submitPublication).mockResolvedValueOnce({
      data: { id: 1, revision_number: 1, status: 'pending' } as any,
      message: 'Đã gửi yêu cầu xuất bản',
    })

    const wrapper = mount(VenueReadinessModal, {
      props: {
        venue: { id: 10, name: 'Sân Cầu Lông Hà Nội', status: 'draft' },
        organizationId: 2,
        isOpen: true,
      },
    })

    await wrapper.vm.$nextTick()
    await new Promise((r) => setTimeout(r, 10))

    expect(wrapper.text()).toContain('Đạt toàn bộ tiêu chuẩn xuất bản')

    const submitBtn = wrapper.findAll('button').find((b) => b.text().includes('Gửi yêu cầu xuất bản'))
    expect(submitBtn).toBeDefined()
    await submitBtn?.trigger('click')

    expect(venuePublicationApi.submitPublication).toHaveBeenCalledWith(2, 10)
    expect(wrapper.emitted('submitted')).toBeTruthy()
  })

  it('allows operational pause on published venues', async () => {
    vi.mocked(venuePublicationApi.getReadiness).mockResolvedValueOnce({
      data: {
        is_ready: true,
        missing_items: [],
        checklist: {
          partner_active: true,
          subscription_entitled: true,
          venue_metadata: true,
          active_courts: true,
          court_pricing: true,
          policies_configured: true,
        },
      },
    })

    vi.mocked(venuePublicationApi.setOperationalStatus).mockResolvedValueOnce({
      data: {},
      message: 'Cập nhật thành công',
    })

    const wrapper = mount(VenueReadinessModal, {
      props: {
        venue: { id: 10, name: 'Sân Cầu Lông Hà Nội', status: 'published' },
        organizationId: 2,
        isOpen: true,
      },
    })

    await wrapper.vm.$nextTick()
    await new Promise((r) => setTimeout(r, 10))

    const pauseBtn = wrapper.findAll('button').find((b) => b.text().includes('Tạm dừng nhận khách'))
    expect(pauseBtn).toBeDefined()
    await pauseBtn?.trigger('click')

    expect(venuePublicationApi.setOperationalStatus).toHaveBeenCalledWith(2, 10, {
      status: 'temporarily_closed',
      reason: 'Tạm đóng bảo trì vận hành',
    })
    expect(wrapper.emitted('statusChanged')).toBeTruthy()
  })
})

describe('AdminPublicationQueueView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders pending publication revisions and handles approval', async () => {
    vi.mocked(venuePublicationApi.getPublicationQueue).mockResolvedValueOnce({
      data: [
        {
          id: 88,
          venue_id: 10,
          revision_number: 1,
          status: 'pending',
          configuration_snapshot: {
            venue: { id: 10, name: 'Sân Sao Mai', address: '123 Kim Ma' },
            courts: [{ id: 1, name: 'Sân 1', price_slots: [] }],
          },
          submitted_by: 5,
          submitted_at: '2026-09-10T10:00:00Z',
        },
      ],
      meta: { current_page: 1, total: 1, last_page: 1 },
    })

    vi.mocked(venuePublicationApi.reviewPublicationRevision).mockResolvedValueOnce({
      data: {} as any,
      message: 'Phê duyệt thành công',
    })

    const wrapper = mount(AdminPublicationQueueView)
    await wrapper.vm.$nextTick()
    await new Promise((r) => setTimeout(r, 10))

    expect(wrapper.text()).toContain('Sân Sao Mai')
    expect(wrapper.text()).toContain('Rev 1')

    const approveBtn = wrapper.findAll('button').find((b) => b.text().includes('Phê duyệt xuất bản'))
    expect(approveBtn).toBeDefined()
    await approveBtn?.trigger('click')

    expect(venuePublicationApi.reviewPublicationRevision).toHaveBeenCalledWith(88, {
      decision: 'approve',
      reason: undefined,
    })
  })
})
