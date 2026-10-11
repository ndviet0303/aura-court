import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import VenueReadinessModal from '../components/venues/VenueReadinessModal.vue'
import AdminPublicationQueueView from '../views/AdminPublicationQueueView.vue'
import { venuePublicationApi } from '../api/venuePublicationApi'
import { i18n } from '@/i18n'

vi.mock('../api/venuePublicationApi', () => ({
  venuePublicationApi: {
    getReadiness: vi.fn(),
    getReceivingConnection: vi.fn(),
    createReceivingConnection: vi.fn(),
    rotateReceivingCredential: vi.fn(),
    selectReceivingConnection: vi.fn(),
    rotateReceivingApiToken: vi.fn(),
    getReceivingConnectionEvidence: vi.fn(),
    syncReceivingTransactions: vi.fn(),
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
    vi.mocked(venuePublicationApi.getReceivingConnection).mockResolvedValue({
      data: {
        id: 9,
        routing_id: 'route-9',
        provider: 'sepay',
        bank_code: 'MBBank',
        account_name: 'Aura Court Owner',
        account_number_masked: '****6789',
        status: 'verified',
        is_selected: true,
        credential_version: 1,
        credential_configured: true,
        query_api_configured: false,
        query_api_version: 0,
        verified_at: '2026-09-25T00:00:00Z',
        webhook_url: 'https://api.example.test/api/v1/webhooks/sepay/connections/route-9',
        onboarding: { instructions_vi: ['Bước kiểm tra'], instructions_en: ['Verification step'] },
      },
    } as any)
    vi.mocked(venuePublicationApi.getReceivingConnectionEvidence).mockResolvedValue({ data: [] })
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
      global: { plugins: [i18n] },
    })

    await wrapper.vm.$nextTick()
    await new Promise((r) => setTimeout(r, 10))

    expect(wrapper.text()).toContain('Đạt toàn bộ tiêu chuẩn xuất bản')

    const submitBtn = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Gửi yêu cầu xuất bản'))
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
      global: { plugins: [i18n] },
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

  it('keeps publication unavailable until the owner receiver is verified', async () => {
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
    vi.mocked(venuePublicationApi.getReceivingConnection).mockResolvedValueOnce({ data: null })
    vi.mocked(venuePublicationApi.createReceivingConnection).mockResolvedValueOnce({
      data: {
        id: 10,
        routing_id: 'route-10',
        provider: 'sepay',
        bank_code: 'MBBank',
        account_name: 'Aura Court Owner',
        account_number_masked: '****6789',
        status: 'draft',
        credential_version: 0,
        credential_configured: false,
        query_api_configured: false,
        query_api_version: 0,
        webhook_url: 'https://api.example.test/api/v1/webhooks/sepay/connections/route-10',
        onboarding: { instructions_vi: ['Bước kiểm tra'], instructions_en: ['Verification step'] },
      },
      message: 'created',
    } as any)

    const wrapper = mount(VenueReadinessModal, {
      props: {
        venue: { id: 10, name: 'Sân Cầu Lông Hà Nội', status: 'draft' },
        organizationId: 2,
        isOpen: true,
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    expect(wrapper.find('[data-testid="owner-receiving-connection-panel"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Tài khoản nhận tiền của chủ sân đã xác minh')
    const submitButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Gửi yêu cầu xuất bản'))
    expect(submitButton?.element.disabled).toBe(true)

    await wrapper.get('[data-testid="receiving-bank-code"]').setValue('MBBank')
    await wrapper.get('[data-testid="receiving-account-name"]').setValue('Aura Court Owner')
    await wrapper.get('[data-testid="receiving-account-number"]').setValue('0123456789')
    await wrapper.get('[data-testid="receiving-legal-owner-confirmed"]').setValue(true)
    await wrapper.get('[data-testid="receiving-connection-create"]').trigger('submit')
    await flushPromises()

    expect(venuePublicationApi.createReceivingConnection).toHaveBeenCalledWith(2, 10, {
      bank_code: 'MBBank',
      account_name: 'Aura Court Owner',
      account_number: '0123456789',
      legal_owner_confirmed: true,
    })
    expect(wrapper.text()).toContain('Bản nháp')
  })

  it('clears an entered webhook secret after rotating the credential', async () => {
    vi.mocked(venuePublicationApi.getReadiness).mockResolvedValueOnce({
      data: { is_ready: false, missing_items: [], checklist: {} as any },
    })
    vi.mocked(venuePublicationApi.getReceivingConnectionEvidence).mockResolvedValueOnce({
      data: [],
    })
    vi.mocked(venuePublicationApi.rotateReceivingCredential).mockResolvedValueOnce({
      data: {
        id: 9,
        routing_id: 'route-9',
        provider: 'sepay',
        bank_code: 'MBBank',
        account_name: 'Aura Court Owner',
        account_number_masked: '****6789',
        status: 'pending_verification',
        credential_version: 2,
        credential_configured: true,
        query_api_configured: false,
        query_api_version: 0,
        webhook_url: 'https://api.example.test/api/v1/webhooks/sepay/connections/route-9',
        onboarding: { instructions_vi: ['Bước kiểm tra'], instructions_en: ['Verification step'] },
      },
      message: 'rotated',
    } as any)

    const wrapper = mount(VenueReadinessModal, {
      props: {
        venue: { id: 10, name: 'Sân Cầu Lông Hà Nội', status: 'draft' },
        organizationId: 2,
        isOpen: true,
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    await wrapper
      .get('[data-testid="hmac-secret-input"]')
      .setValue('whsec_test_secret_value_which_is_long_enough')
    await wrapper.get('[data-testid="save-hmac-secret"]').trigger('click')
    await flushPromises()

    expect(venuePublicationApi.rotateReceivingCredential).toHaveBeenCalledWith(
      2,
      10,
      9,
      'whsec_test_secret_value_which_is_long_enough',
    )
    expect(
      (wrapper.get('[data-testid="hmac-secret-input"]').element as HTMLInputElement).value,
    ).toBe('')
    expect(wrapper.text()).toContain('Đang chờ giao dịch kiểm tra')
  })

  it('requires selecting a verified receiver and refreshes readiness after selection', async () => {
    vi.mocked(venuePublicationApi.getReadiness).mockResolvedValue({
      data: {
        is_ready: false,
        missing_items: [{ code: 'RECEIVING_CONNECTION_REQUIRED', message: 'Choose a receiver.' }],
        checklist: {} as any,
      },
    })
    vi.mocked(venuePublicationApi.getReceivingConnection).mockResolvedValue({
      data: {
        id: 9,
        routing_id: 'route-9',
        provider: 'sepay',
        bank_code: 'MBBank',
        account_name: 'Aura Court Owner',
        account_number_masked: '****6789',
        status: 'verified',
        is_selected: false,
        credential_version: 1,
        credential_configured: true,
        query_api_configured: false,
        query_api_version: 0,
        verified_at: '2026-09-25T00:00:00Z',
        webhook_url: 'https://api.example.test/api/v1/webhooks/sepay/connections/route-9',
        onboarding: { instructions_vi: ['Bước kiểm tra'], instructions_en: ['Verification step'] },
      },
    } as any)
    vi.mocked(venuePublicationApi.getReceivingConnectionEvidence).mockResolvedValue({ data: [] })
    vi.mocked(venuePublicationApi.selectReceivingConnection).mockResolvedValue({
      data: { id: 9, status: 'verified', is_selected: true } as any,
      message: 'Selected',
    })

    const wrapper = mount(VenueReadinessModal, {
      props: {
        venue: { id: 10, name: 'Sân Cầu Lông Hà Nội', status: 'draft' },
        organizationId: 2,
        isOpen: true,
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    const submitButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Gửi yêu cầu xuất bản'))
    expect(submitButton?.element.disabled).toBe(true)
    await wrapper.get('[data-testid="select-receiving-connection"]').trigger('click')
    await flushPromises()

    expect(venuePublicationApi.selectReceivingConnection).toHaveBeenCalledWith(2, 10, 9)
    expect(wrapper.find('[data-testid="selected-receiver-badge"]').exists()).toBe(true)
    expect(venuePublicationApi.getReadiness).toHaveBeenCalledTimes(1)
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

    const approveBtn = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Phê duyệt xuất bản'))
    expect(approveBtn).toBeDefined()
    await approveBtn?.trigger('click')

    expect(venuePublicationApi.reviewPublicationRevision).toHaveBeenCalledWith(88, {
      decision: 'approve',
      reason: undefined,
    })
  })
})
