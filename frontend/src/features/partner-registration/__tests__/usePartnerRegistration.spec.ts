import { describe, it, expect, vi, beforeEach } from 'vitest'
import { usePartnerRegistration } from '../composables/usePartnerRegistration'
import { partnerRegistrationApi } from '../api/partnerRegistrationApi'
import type { PartnerApplication } from '../types'

vi.mock('../api/partnerRegistrationApi', () => ({
  partnerRegistrationApi: {
    getCurrentApplication: vi.fn(),
    startApplication: vi.fn(),
    saveDraft: vi.fn(),
    uploadDocument: vi.fn(),
    submitApplication: vi.fn(),
    supplementApplication: vi.fn(),
    getApplicationHistory: vi.fn(),
  },
}))

describe('usePartnerRegistration', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches current application and computes status correctly', async () => {
    const mockApp: PartnerApplication = {
      id: 10,
      user_id: 1,
      status: 'draft',
      version: 1,
      business_type: null,
      business_name: null,
      tax_code: null,
      contact_name: null,
      contact_phone: null,
      contact_email: null,
      metadata: null,
      draft_payload: null,
      documents: [],
      consents: [],
      review_history: [],
      created_at: '2026-09-10T00:00:00Z',
      updated_at: '2026-09-10T00:00:00Z',
    }

    vi.mocked(partnerRegistrationApi.getCurrentApplication).mockResolvedValueOnce({ data: mockApp })

    const composable = usePartnerRegistration()
    expect(composable.application.value).toBeNull()

    const result = await composable.fetchCurrent()
    expect(result).toEqual(mockApp)
    expect(composable.application.value).toEqual(mockApp)
    expect(composable.isDraft.value).toBe(true)
    expect(composable.isPendingReview.value).toBe(false)
  })

  it('starts application and populates state', async () => {
    const mockApp: PartnerApplication = {
      id: 11,
      user_id: 2,
      status: 'draft',
      version: 1,
      business_type: null,
      business_name: null,
      tax_code: null,
      contact_name: null,
      contact_phone: null,
      contact_email: null,
      metadata: { consent_terms: true, consent_privacy: true },
      draft_payload: null,
      documents: [],
      consents: [],
      review_history: [],
      created_at: '2026-09-10T00:00:00Z',
      updated_at: '2026-09-10T00:00:00Z',
    }

    vi.mocked(partnerRegistrationApi.startApplication).mockResolvedValueOnce({
      data: mockApp,
      message: 'Created',
    })

    const composable = usePartnerRegistration()
    const result = await composable.start({
      consent_terms: true,
      consent_privacy: true,
      terms_version: 'v1.0',
    })

    expect(result).toEqual(mockApp)
    expect(composable.application.value?.id).toBe(11)
  })

  it('handles validation field errors gracefully', async () => {
    vi.mocked(partnerRegistrationApi.startApplication).mockRejectedValueOnce({
      message: 'Validation failed',
      errors: {
        consent_terms: ['Bạn phải đồng ý với điều khoản dịch vụ.'],
      },
    })

    const composable = usePartnerRegistration()
    await expect(
      composable.start({ consent_terms: false, consent_privacy: false })
    ).rejects.toBeDefined()

    expect(composable.fieldErrors.value.consent_terms).toContain(
      'Bạn phải đồng ý với điều khoản dịch vụ.'
    )
  })
})
