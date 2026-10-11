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
    getApplicationHistory: vi.fn(),
  },
}))

function mockApplication(
  id: number,
  status: PartnerApplication['status'] = 'draft',
): PartnerApplication {
  return {
    id,
    kyc_code: `KYC-${id}`,
    status,
    version: 1,
    is_current: true,
    business_type: null,
    business_name: null,
    legal_name: null,
    tax_code: null,
    tax_status: null,
    representative_name: null,
    id_card_number: null,
    id_card_number_masked: null,
    bank_name: null,
    bank_code: null,
    bank_account_number: null,
    bank_account_number_masked: null,
    bank_account_holder: null,
    contact_name: null,
    contact_phone: null,
    contact_email: null,
    address_line: null,
    ward: null,
    district: null,
    city: null,
    postal_code: null,
    sports: [],
    court_scale: 1,
    lighting_spec: null,
    investigation_status: null,
    rejection_reason: null,
    submitted_at: null,
    reviewed_at: null,
    documents: [],
    consents: [],
    review_histories: [],
    created_at: '2026-09-10T00:00:00Z',
    updated_at: '2026-09-10T00:00:00Z',
  }
}

describe('usePartnerRegistration', () => {
  beforeEach(() => vi.clearAllMocks())

  it('fetches the server resource and computes status correctly', async () => {
    const application = mockApplication(10)
    vi.mocked(partnerRegistrationApi.getCurrentApplication).mockResolvedValueOnce({
      data: application,
    })

    const composable = usePartnerRegistration()
    expect(composable.application.value).toBeNull()

    const result = await composable.fetchCurrent()
    expect(result).toEqual(application)
    expect(composable.application.value).toEqual(application)
    expect(composable.isDraft.value).toBe(true)
    expect(composable.isPendingReview.value).toBe(false)
  })

  it('starts an application without sending unrecorded consent fields', async () => {
    const application = mockApplication(11)
    vi.mocked(partnerRegistrationApi.startApplication).mockResolvedValueOnce({
      data: application,
      message: 'Created',
    })

    const composable = usePartnerRegistration()
    const result = await composable.start()

    expect(result).toEqual(application)
    expect(composable.application.value?.id).toBe(11)
    expect(partnerRegistrationApi.startApplication).toHaveBeenCalledWith()
  })

  it('uses the backend upload field and refreshes the application version', async () => {
    const application = mockApplication(12)
    vi.mocked(partnerRegistrationApi.getCurrentApplication).mockResolvedValueOnce({
      data: application,
    })
    vi.mocked(partnerRegistrationApi.uploadDocument).mockResolvedValueOnce({
      data: {
        id: 7,
        document_type: 'representative_id_front',
        file_name: 'front.jpg',
        mime_type: 'image/jpeg',
        file_size: 128,
        version: 1,
        status: 'uploaded',
        read_url: '/signed-document',
        created_at: '2026-09-10T00:00:00Z',
        application_version: 3,
      },
      message: 'Uploaded',
    })

    const composable = usePartnerRegistration()
    await composable.fetchCurrent()
    const file = new File(['identity'], 'front.jpg', { type: 'image/jpeg' })
    await composable.uploadDocument(file, 'representative_id_front')

    const uploadCall = vi.mocked(partnerRegistrationApi.uploadDocument).mock.calls[0]
    if (!uploadCall) throw new Error('Upload call was not recorded')
    const formData = uploadCall[1]
    expect(formData.get('file')).toBe(file)
    expect(formData.get('document')).toBeNull()
    expect(composable.application.value?.version).toBe(3)
    expect(composable.application.value?.documents[0]?.file_name).toBe('front.jpg')
  })

  it('preserves validation field errors', async () => {
    vi.mocked(partnerRegistrationApi.startApplication).mockRejectedValueOnce({
      message: 'Validation failed',
      errors: { user: ['Vui lòng xác thực tài khoản.'] },
    })

    const composable = usePartnerRegistration()
    await expect(composable.start()).rejects.toBeDefined()
    expect(composable.fieldErrors.value.user).toContain('Vui lòng xác thực tài khoản.')
  })
})
