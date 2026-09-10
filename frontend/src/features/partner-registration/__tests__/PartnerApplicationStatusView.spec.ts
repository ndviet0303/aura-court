import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref, computed } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import PartnerApplicationStatusView from '../views/PartnerApplicationStatusView.vue'
import { usePartnerRegistration } from '../composables/usePartnerRegistration'
import { useAuthStore } from '@/stores/auth'
import type { PartnerApplication } from '../types'

const mockPush = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
}))

vi.mock('../composables/usePartnerRegistration')

describe('PartnerApplicationStatusView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  function createMockComposable(status: PartnerApplication['status']) {
    const mockApp: PartnerApplication = {
      id: 55,
      user_id: 1,
      status,
      version: 1,
      business_type: 'enterprise',
      business_name: 'San Cau Long Victory',
      tax_code: '0101010101',
      contact_name: 'Tran C',
      contact_phone: '0901234567',
      contact_email: 'c@victory.com',
      metadata: null,
      draft_payload: null,
      documents: [],
      consents: [],
      review_history: [
        {
          id: 1,
          application_id: 55,
          decision: status === 'supplementation_requested' ? 'supplement' : status === 'rejected' ? 'reject' : 'approve',
          reason: 'Thông báo từ chuyên viên thẩm định.',
          reviewed_by: 99,
          reviewed_at: '2026-09-10T10:00:00Z',
        },
      ],
      created_at: '2026-09-10T08:00:00Z',
      updated_at: '2026-09-10T10:00:00Z',
    }

    const appRef = ref<PartnerApplication | null>(mockApp)
    const historyRef = ref(mockApp.review_history)
    const fetchCurrent = vi.fn().mockImplementation(async () => {
      appRef.value = mockApp
      return mockApp
    })
    const fetchHistory = vi.fn().mockImplementation(async () => {
      historyRef.value = mockApp.review_history
      return mockApp.review_history
    })
    const supplement = vi.fn().mockResolvedValue({ ...mockApp, status: 'pending_review' })

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
      isPendingReview: computed(() => status === 'pending_review') as any,
      isSupplementationRequested: computed(() => status === 'supplementation_requested') as any,
      isApproved: computed(() => status === 'approved') as any,
      isRejected: computed(() => status === 'rejected') as any,
      clearErrors: vi.fn(),
      fetchCurrent,
      start: vi.fn(),
      saveDraft: vi.fn(),
      uploadDocument: vi.fn(),
      submit: vi.fn(),
      supplement,
      fetchHistory,
    })

    return { mockApp, fetchCurrent, fetchHistory, supplement }
  }

  it('renders pending edit prevention notice during active review', async () => {
    createMockComposable('pending_review')

    const wrapper = mount(PartnerApplicationStatusView)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Hồ sơ đang trong quá trình thẩm định')
    expect(wrapper.text()).toContain('Hồ sơ của bạn đã được khóa')
  })

  it('handles supplementation submission workflow', async () => {
    const { supplement } = createMockComposable('supplementation_requested')

    const wrapper = mount(PartnerApplicationStatusView)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Chuyên viên yêu cầu bổ sung thông tin')

    const textarea = wrapper.find('textarea')
    await textarea.setValue('Đã bổ sung giấy tờ xác minh bổ sung.')

    const submitBtn = wrapper.find('button.bg-amber-600')
    await submitBtn.trigger('click')

    expect(supplement).toHaveBeenCalledWith({ notes: 'Đã bổ sung giấy tờ xác minh bổ sung.' })
  })

  it('refreshes auth user and navigates to dashboard on approval', async () => {
    createMockComposable('approved')
    const authStore = useAuthStore()
    const loadSpy = vi.spyOn(authStore, 'load').mockResolvedValue({} as any)

    const wrapper = mount(PartnerApplicationStatusView)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Hồ sơ đã được phê duyệt thành công')

    const dashboardBtn = wrapper.find('button.bg-emerald-600')
    await dashboardBtn.trigger('click')

    expect(loadSpy).toHaveBeenCalledWith(true)
    expect(mockPush).toHaveBeenCalledWith('/admin/organization')
  })
})
