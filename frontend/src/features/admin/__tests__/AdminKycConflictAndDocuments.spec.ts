import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useAdminKyc } from '../composables/useAdminKyc'
import { adminKycApi } from '../api/adminKycApi'
import type { HostKyc } from '../types/kyc'

vi.mock('../api/adminKycApi', () => ({
  adminKycApi: {
    getQueue: vi.fn(),
    getStats: vi.fn(),
    getDetail: vi.fn(),
    reviewKyc: vi.fn(),
    getDocumentUrl: vi.fn(),
  },
}))

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => key,
  }),
}))

describe('useAdminKyc review conflict & authorized documents', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  const mockKyc: HostKyc = {
    id: 101,
    kyc_code: 'KYC-101',
    business_type: 'enterprise',
    business_name: 'CLB The Thao 101',
    legal_name: 'Cong ty 101',
    tax_code: '010101',
    tax_status: 'valid',
    representative_name: 'Nguyen Van A',
    id_card_number: '123456789',
    id_card_front_url: '/front.jpg',
    business_license_url: '/license.jpg',
    bank_name: 'VCB',
    bank_code: 'VCB',
    bank_account_number: '999',
    bank_account_holder: 'A',
    napas_match_status: 'matched',
    court_scale: 4,
    lease_contract_status: 'valid',
    facility_images: [],
    ai_ocr_score: 95,
    face_match_score: 95,
    status: 'pending',
    submitted_at: '2026-09-10T00:00:00Z',
    version: 2,
    documents: [
      {
        id: 501,
        document_type: 'business_license',
        original_name: 'license.pdf',
        mime_type: 'application/pdf',
        file_size_bytes: 1024,
        version: 1,
      },
    ],
  }

  it('passes expected_version on review and detects 409 conflict', async () => {
    const composable = useAdminKyc()
    composable.selectedKyc.value = { ...mockKyc }

    // First call rejects with 409 Conflict
    vi.mocked(adminKycApi.reviewKyc).mockRejectedValueOnce({
      status: 409,
      message: 'Xung đột xét duyệt: Phiên bản không khớp.',
    })

    vi.mocked(adminKycApi.getDetail).mockResolvedValueOnce({
      data: { ...mockKyc, version: 3 },
    })

    await composable.handleApprove()

    expect(adminKycApi.reviewKyc).toHaveBeenCalledWith(
      101,
      expect.objectContaining({
        decision: 'approve',
        expected_version: 2,
      }),
    )

    // Handled conflict and refreshed
    expect(composable.notice.value).toContain('Xung đột xét duyệt')
    expect(adminKycApi.getDetail).toHaveBeenCalledWith(101)
    expect(composable.selectedKyc.value?.version).toBe(3)
  })

  it('queries authorized document URL when inspecting', async () => {
    const composable = useAdminKyc()
    composable.selectedKyc.value = { ...mockKyc }

    vi.mocked(adminKycApi.getDocumentUrl).mockResolvedValueOnce({
      url: 'https://storage.aura.test/signed/license.pdf',
    })

    await composable.inspectAuthorizedDocument({
      id: 501,
      title: 'Giấy phép kinh doanh',
      type: 'gpkd',
    })

    expect(adminKycApi.getDocumentUrl).toHaveBeenCalledWith(101, 501)
    expect(composable.isInspectModalOpen.value).toBe(true)
    expect(composable.inspectData.value?.imageUrl).toBe(
      'https://storage.aura.test/signed/license.pdf',
    )
  })
})
