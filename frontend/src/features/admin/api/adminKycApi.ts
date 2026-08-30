import { apiV2 } from '@/core/api/client'
import type { HostKyc, KycStats, KycFilterParams, ReviewKycPayload } from '../types/kyc'

export const INITIAL_MOCK_KYC_LIST: HostKyc[] = [
  {
    id: 1,
    kycCode: 'KYC-2026-0892',
    organizationId: 101,
    businessType: 'enterprise',
    businessName: 'Smash City Arena',
    legalName: 'CÔNG TY TNHH THỂ THAO SMASH CITY',
    taxCode: '0316589921',
    taxStatus: 'Đang hoạt động',
    representativeName: 'NGUYEN VAN AN',
    idCardNumber: '079092008892',
    idCardFrontUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuB6LCY5Wab4g75XB6-O8MsyL-yKcopxbisnzHoymCBF1lN3koA0n17JtNkGCgCVaw-RjqbWhEizCLVdyY9D5bc78qa36czOOIhA5G_ZscDaOVFZVzH2jvSAfnU9z9DEAyGpo0lrQsRMZpd-OQWPEkyHf2vsRrgU9T3K4vfAJH0rzYXPFCQyFNg_TFQLimkjYl7YeFEmzCYArK9sX0PwaqMJD3BeMFu1_bvXpm-xjmiCxA29Q69A7nuX',
    businessLicenseUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBcE3wM0Co_65aly8cLxgqstU8PZm7p_FG-Ze5ESczMpXKtmrzhgbsLNYOoym3dREvE5zbmojpsJMm905pDLzBLCb-TuQlkosZvQ-hV5mWQ6byVxjxgHIzLOhKRoiMpxEpbeZWuyq6XTWBTS-9QBhlgqb6VttdY0SfQTEIg0k4cX4ErvJf01fdWkzqpJML7yRB18xU6kpnBV9FdFFWI-P0u8GxWICjp77-CGi1t2nnUQb2rFovMNa7n',
    bankName: 'MBBank - Ngân hàng TMCP Quân Đội',
    bankCode: 'MBBANK',
    bankAccountNumber: '0909 888 999',
    bankAccountHolder: 'CONG TY TNHH THE THAO SMASH CITY',
    napasMatchStatus: 'matched',
    courtScale: 12,
    lightingSpec: 'Hệ thống đèn LED chống chói 1000W',
    leaseContractStatus: 'Hợp đồng 5 năm (đến 31/12/2030)',
    facilityImages: [
      'https://lh3.googleusercontent.com/aida-public/AB6AXuC200mOe1qui5O8M2nBK6gCXJNjfyxRW00Z7e68m6J0rNUqBJ4dn0j-8NEhHgnXlnp49WlO2OVp1xwRmi_3hRercULcx4K3Poj9TK3rRG3oHioElmhw9aUSOjt7NMBJfILKc5R3_EBQaboTYEp32dt2h_XYL10Z0jhK7RvygsnkcrVwjjV7sSVUJSvdhxQs90q_4v_nAScdRxO0R_NAVYCksV1sfl6wqGgRQNfAHHp1k6VYOM_9HB0iaQHRoxk58thHPQuPp-vvcP8',
      'https://lh3.googleusercontent.com/aida-public/AB6AXuAGQI6_P7AtDayPPFciTvl6xFEXsvrOkYUm7ZSDuSQdKnojzaIzkPAoGVJOwozx5VHnq2ieYTSWVnBcXPoXgorw8An8GMsDATKm8r1rOqIKvM2rh-pj06Erp0ECpig_eJ-pDgazz6GXeqX2jLGAgyWCfkquLkzNmzfrHUBDYzOfzbD8PC2sS5dCTJL9z5GKyx_TCZTOr8wsFTzer90W9lK3BqH6oYM27yvdVmZxMd9lUyT3ZMQCTprYuMg0tVNtYx9HVVVwuDP66Gs',
      'https://lh3.googleusercontent.com/aida-public/AB6AXuDDQcfJq3Am6GuRKjgofIsQZLx04D3GDUxXQWmT6AI6C14BbwCMhY47aB38FfUCLMVIFO0GPJRcQ99qLuQH-xBoVd1xoPFK2cydrMnBr-ckFQeKA2BkBzM51rF1OL65-AUAuceP_RTILASw_SjVzeRnYffadkBjzyCmSC2eOvmc9I3YpJp3TDcty4oTeKL7WdaL-xoElcP8zcApNvB63hMg4xB7ivn7MkLWHFHwPA3gMIj0XDBVYwwXcZNz29p-V9CuhYwRQFLbWYA',
    ],
    aiOcrScore: 99,
    faceMatchScore: 99.4,
    status: 'pending',
    checklistState: {
      repIdentityMatched: true,
      businessLicenseValid: true,
      bankAccountMatched: true,
      brandNameDiscrepancyAccepted: false,
      facilityInspected: true,
    },
    submittedAt: '2026-08-30T06:58:00Z',
  },
  {
    id: 2,
    kycCode: 'KYC-2026-0891',
    organizationId: 102,
    businessType: 'business_household',
    businessName: 'Sân Cầu Lông Tân Bình Sport',
    legalName: 'HỘ KINH DOANH TÂN BÌNH SPORT',
    taxCode: '8392104921',
    taxStatus: 'Đang hoạt động',
    representativeName: 'TRAN THI BICH',
    idCardNumber: '079194002341',
    idCardFrontUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuB6LCY5Wab4g75XB6-O8MsyL-yKcopxbisnzHoymCBF1lN3koA0n17JtNkGCgCVaw-RjqbWhEizCLVdyY9D5bc78qa36czOOIhA5G_ZscDaOVFZVzH2jvSAfnU9z9DEAyGpo0lrQsRMZpd-OQWPEkyHf2vsRrgU9T3K4vfAJH0rzYXPFCQyFNg_TFQLimkjYl7YeFEmzCYArK9sX0PwaqMJD3BeMFu1_bvXpm-xjmiCxA29Q69A7nuX',
    businessLicenseUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBcE3wM0Co_65aly8cLxgqstU8PZm7p_FG-Ze5ESczMpXKtmrzhgbsLNYOoym3dREvE5zbmojpsJMm905pDLzBLCb-TuQlkosZvQ-hV5mWQ6byVxjxgHIzLOhKRoiMpxEpbeZWuyq6XTWBTS-9QBhlgqb6VttdY0SfQTEIg0k4cX4ErvJf01fdWkzqpJML7yRB18xU6kpnBV9FdFFWI-P0u8GxWICjp77-CGi1t2nnUQb2rFovMNa7n',
    bankName: 'Techcombank - Ngân hàng TMCP Kỹ Thương',
    bankCode: 'TCB',
    bankAccountNumber: '1903 666 888',
    bankAccountHolder: 'TRAN THI BICH',
    napasMatchStatus: 'matched',
    courtScale: 6,
    leaseContractStatus: 'Chính chủ sở hữu mặt bằng',
    facilityImages: [],
    aiOcrScore: 98.2,
    faceMatchScore: 99.0,
    status: 'pending',
    checklistState: {
      repIdentityMatched: true,
      businessLicenseValid: true,
      bankAccountMatched: true,
      brandNameDiscrepancyAccepted: true,
      facilityInspected: true,
    },
    submittedAt: '2026-08-30T05:30:00Z',
  },
  {
    id: 3,
    kycCode: 'KYC-2026-0889',
    organizationId: 103,
    businessType: 'enterprise',
    businessName: 'Elite Badminton Club Q7',
    legalName: 'CÔNG TY CP THỂ THAO ELITE VIỆT NAM',
    taxCode: '0317892110',
    taxStatus: 'Đang hoạt động',
    representativeName: 'LE HOANG PHUC',
    idCardNumber: '079088001122',
    idCardFrontUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuB6LCY5Wab4g75XB6-O8MsyL-yKcopxbisnzHoymCBF1lN3koA0n17JtNkGCgCVaw-RjqbWhEizCLVdyY9D5bc78qa36czOOIhA5G_ZscDaOVFZVzH2jvSAfnU9z9DEAyGpo0lrQsRMZpd-OQWPEkyHf2vsRrgU9T3K4vfAJH0rzYXPFCQyFNg_TFQLimkjYl7YeFEmzCYArK9sX0PwaqMJD3BeMFu1_bvXpm-xjmiCxA29Q69A7nuX',
    businessLicenseUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBcE3wM0Co_65aly8cLxgqstU8PZm7p_FG-Ze5ESczMpXKtmrzhgbsLNYOoym3dREvE5zbmojpsJMm905pDLzBLCb-TuQlkosZvQ-hV5mWQ6byVxjxgHIzLOhKRoiMpxEpbeZWuyq6XTWBTS-9QBhlgqb6VttdY0SfQTEIg0k4cX4ErvJf01fdWkzqpJML7yRB18xU6kpnBV9FdFFWI-P0u8GxWICjp77-CGi1t2nnUQb2rFovMNa7n',
    bankName: 'Vietcombank - Chi nhánh Nam Sài Gòn',
    bankCode: 'VCB',
    bankAccountNumber: '0071 000 888 999',
    bankAccountHolder: 'CONG TY CP THE THAO ELITE VIET NAM',
    napasMatchStatus: 'matched',
    courtScale: 16,
    leaseContractStatus: 'Hợp đồng 10 năm',
    facilityImages: [],
    aiOcrScore: 94.5,
    faceMatchScore: 96.8,
    status: 'flagged',
    rejectionReason: 'Cảnh báo: Trùng địa chỉ khai báo với một cơ sở đã đăng ký khác trên hệ thống.',
    submittedAt: '2026-08-29T18:40:00Z',
  },
  {
    id: 4,
    kycCode: 'KYC-2026-0885',
    organizationId: 104,
    businessType: 'individual',
    businessName: 'VStar Arena Bình Thạnh',
    legalName: 'VÕ QUỐC TUẤN',
    taxCode: '8099218201',
    taxStatus: 'Đang hoạt động',
    representativeName: 'VO QUOC TUAN',
    idCardNumber: '079085009911',
    idCardFrontUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuB6LCY5Wab4g75XB6-O8MsyL-yKcopxbisnzHoymCBF1lN3koA0n17JtNkGCgCVaw-RjqbWhEizCLVdyY9D5bc78qa36czOOIhA5G_ZscDaOVFZVzH2jvSAfnU9z9DEAyGpo0lrQsRMZpd-OQWPEkyHf2vsRrgU9T3K4vfAJH0rzYXPFCQyFNg_TFQLimkjYl7YeFEmzCYArK9sX0PwaqMJD3BeMFu1_bvXpm-xjmiCxA29Q69A7nuX',
    businessLicenseUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBcE3wM0Co_65aly8cLxgqstU8PZm7p_FG-Ze5ESczMpXKtmrzhgbsLNYOoym3dREvE5zbmojpsJMm905pDLzBLCb-TuQlkosZvQ-hV5mWQ6byVxjxgHIzLOhKRoiMpxEpbeZWuyq6XTWBTS-9QBhlgqb6VttdY0SfQTEIg0k4cX4ErvJf01fdWkzqpJML7yRB18xU6kpnBV9FdFFWI-P0u8GxWICjp77-CGi1t2nnUQb2rFovMNa7n',
    bankName: 'ACB - Ngân hàng Á Châu',
    bankCode: 'ACB',
    bankAccountNumber: '2288 999 11',
    bankAccountHolder: 'VO QUOC TUAN',
    napasMatchStatus: 'matched',
    courtScale: 8,
    leaseContractStatus: 'Hợp đồng 3 năm',
    facilityImages: [],
    aiOcrScore: 91.0,
    faceMatchScore: 94.2,
    status: 'needs_supplement',
    rejectionReason: 'Thiếu bản chụp rõ nét 4 góc của Hợp đồng thuê địa điểm kinh doanh.',
    submittedAt: '2026-08-29T14:15:00Z',
  },
]

export const adminKycApi = {
  getQueue: async (params?: KycFilterParams): Promise<{ data: HostKyc[] }> => {
    try {
      const query = new URLSearchParams()
      if (params?.status && params.status !== 'all') query.set('status', params.status)
      if (params?.search) query.set('search', params.search)
      if (params?.perPage) query.set('per_page', String(params.perPage))

      const qs = query.toString()
      const res = await apiV2.get<{ data: HostKyc[] }>(`/admin/kyc${qs ? `?${qs}` : ''}`)
      if (res && res.data && res.data.length > 0) {
        return res
      }
    } catch {
      // fallback to mock data on network error or offline demo
    }

    let list = [...INITIAL_MOCK_KYC_LIST]
    if (params?.status && params.status !== 'all') {
      list = list.filter((item) => item.status === params.status)
    }
    if (params?.search) {
      const term = params.search.toLowerCase()
      list = list.filter(
        (item) =>
          item.kycCode.toLowerCase().includes(term) ||
          item.businessName.toLowerCase().includes(term) ||
          item.representativeName.toLowerCase().includes(term) ||
          item.taxCode.includes(term)
      )
    }

    return { data: list }
  },

  getStats: async (): Promise<{ stats: KycStats }> => {
    try {
      const res = await apiV2.get<{ stats: KycStats }>('/admin/kyc/stats')
      if (res && res.stats) {
        return res
      }
    } catch {
      // fallback mock
    }

    return {
      stats: {
        pendingCount: 12,
        aiConfidenceRate: 96.4,
        avgProcessingTimeMinutes: 14.2,
        flaggedCount: 2,
        approvedCount: 148,
        needsSupplementCount: 3,
      },
    }
  },

  getDetail: async (id: number): Promise<{ data: HostKyc }> => {
    try {
      const res = await apiV2.get<{ data: HostKyc }>(`/admin/kyc/${id}`)
      if (res && res.data) return res
    } catch {
      // fallback
    }

    const found = INITIAL_MOCK_KYC_LIST.find((item) => item.id === id) || INITIAL_MOCK_KYC_LIST[0]!
    return { data: found }
  },

  reviewKyc: async (id: number, payload: ReviewKycPayload): Promise<{ message: string; data: HostKyc }> => {
    try {
      const res = await apiV2.post<{ message: string; data: HostKyc }>(`/admin/kyc/${id}/review`, payload)
      if (res && res.data) return res
    } catch {
      // fallback local update
    }

    const item = INITIAL_MOCK_KYC_LIST.find((k) => k.id === id) || INITIAL_MOCK_KYC_LIST[0]!
    const updatedStatus =
      payload.decision === 'approve'
        ? 'approved'
        : payload.decision === 'reject'
        ? 'rejected'
        : 'needs_supplement'

    item.status = updatedStatus
    item.rejectionReason = payload.rejectionReason
    item.reviewerNotes = payload.reviewerNotes
    item.checklistState = { ...item.checklistState, ...payload.checklistState }
    item.reviewedAt = new Date().toISOString()
    item.reviewerName = 'Lê Thị Kim Oanh'

    const messages = {
      approve: 'Hồ sơ KYC đã được phê duyệt và kích hoạt chủ sân thành công.',
      reject: 'Đã từ chối hồ sơ KYC và gửi lý do thông báo.',
      request_supplement: 'Đã gửi yêu cầu bổ sung hồ sơ đến chủ sân.',
    }

    return {
      message: messages[payload.decision],
      data: item,
    }
  },
}
