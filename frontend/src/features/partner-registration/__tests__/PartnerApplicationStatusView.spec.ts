import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref, computed } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import PartnerApplicationStatusView from '../views/PartnerApplicationStatusView.vue'
import { usePartnerRegistration } from '../composables/usePartnerRegistration'
import { useAuthStore } from '@/stores/auth'
import type { PartnerApplication } from '../types'

const mockPush = vi.fn()

vi.mock('vue-router', () => ({ useRouter: () => ({ push: mockPush }) }))
vi.mock('../composables/usePartnerRegistration')

describe('PartnerApplicationStatusView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  function createMockComposable(status: PartnerApplication['status']) {
    const review = {
      id: 1,
      decision: ['revision_required', 'supplementation_requested'].includes(status)
        ? 'revision_required'
        : status === 'rejected' ? 'reject' : 'approve',
      reason: 'Thông báo từ chuyên viên thẩm định.',
      reapplication_allowed: true,
      application_version: 1,
      created_at: '2026-09-10T10:00:00Z',
    }
    const mockApp: PartnerApplication = {
      id: 55,
      kyc_code: 'KYC-55',
      status,
      version: 1,
      is_current: true,
      business_type: 'company',
      business_name: 'San Cau Long Victory',
      legal_name: 'Victory Sports',
      tax_code: '0101010101',
      tax_status: null,
      representative_name: 'Tran C',
      id_card_number: '001099123456',
      id_card_number_masked: '001*****3456',
      bank_name: 'VCB',
      bank_code: 'VCB',
      bank_account_number: '12345678',
      bank_account_number_masked: '****5678',
      bank_account_holder: 'TRAN C',
      contact_name: 'Tran C',
      contact_phone: '0901234567',
      contact_email: 'c@victory.com',
      address_line: 'Victory Street',
      ward: null,
      district: null,
      city: 'Hanoi',
      postal_code: null,
      sports: ['badminton'],
      court_scale: 4,
      lighting_spec: null,
      investigation_status: null,
      rejection_reason: null,
      submitted_at: '2026-09-10T08:30:00Z',
      reviewed_at: '2026-09-10T10:00:00Z',
      documents: [],
      consents: [],
      review_histories: [review],
      created_at: '2026-09-10T08:00:00Z',
      updated_at: '2026-09-10T10:00:00Z',
    }

    const appRef = ref<PartnerApplication | null>(mockApp)
    const historyRef = ref(mockApp.review_histories)
    const fetchCurrent = vi.fn().mockImplementation(async () => {
      appRef.value = mockApp
      return mockApp
    })
    const fetchHistory = vi.fn().mockImplementation(async () => {
      historyRef.value = mockApp.review_histories
      return mockApp.review_histories
    })
    vi.mocked(usePartnerRegistration).mockReturnValue({
      application: appRef as any,
      history: historyRef as any,
      loading: ref(false) as any,
      saving: ref(false) as any,
      submitting: ref(false) as any,
      uploading: ref(false) as any,
      error: ref<string | null>(null) as any,
      fieldErrors: ref({}) as any,
      isDraft: computed(() => status === 'draft') as any,
      isPendingReview: computed(() => ['pending', 'pending_review'].includes(status)) as any,
      isSupplementationRequested: computed(() => ['revision_required', 'supplementation_requested'].includes(status)) as any,
      isApproved: computed(() => status === 'approved') as any,
      isRejected: computed(() => status === 'rejected') as any,
      clearErrors: vi.fn(),
      fetchCurrent,
      start: vi.fn(),
      saveDraft: vi.fn(),
      uploadDocument: vi.fn(),
      submit: vi.fn(),
      fetchHistory,
    })

    return { fetchCurrent, fetchHistory }
  }

  it('renders the pending review notice for the backend status', async () => {
    createMockComposable('pending')
    const wrapper = mount(PartnerApplicationStatusView)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Hồ sơ đang trong quá trình thẩm định')
    expect(wrapper.text()).toContain('Hồ sơ của bạn đã được khóa')
  })

  it('routes a correction request back to the editable application', async () => {
    createMockComposable('revision_required')
    const wrapper = mount(PartnerApplicationStatusView)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Chuyên viên yêu cầu bổ sung thông tin')
    await wrapper.find('button.bg-amber-600').trigger('click')
    expect(mockPush).toHaveBeenCalledWith('/partner-registration')
  })

  it('refreshes auth user and navigates to dashboard on approval', async () => {
    createMockComposable('approved')
    const authStore = useAuthStore()
    const loadSpy = vi.spyOn(authStore, 'load').mockResolvedValue({} as any)
    const wrapper = mount(PartnerApplicationStatusView)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Hồ sơ đã được phê duyệt thành công')
    await wrapper.find('button.bg-emerald-600').trigger('click')
    expect(loadSpy).toHaveBeenCalledWith(true)
    expect(mockPush).toHaveBeenCalledWith('/admin/organization')
  })
})
