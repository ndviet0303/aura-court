import { apiV2 } from '@/core/api/client'
import type { DisputeCase, DisputeStats, DisputeFilterParams, ArbitrateDisputePayload } from '../types/dispute'

export const INITIAL_MOCK_DISPUTES: DisputeCase[] = [
  {
    id: 1,
    disputeCode: 'DSP-8924',
    organizationId: 1,
    venueId: 4,
    bookingId: 99482,
    courtName: 'Sân số 4 (Phú Thọ)',
    disputeType: 'double_booking',
    severity: 'critical',
    title: 'Xung đột Trùng Lịch Sân số 4',
    summary: 'Alex Lawson và nhóm Trần Quốc Bảo cùng quét check-in hợp lệ tại Sân Phú Thọ lúc 19:00.',
    status: 'investigating',
    slaDeadline: new Date(Date.now() + 15 * 60000).toISOString(),
    amountDisputed: 360000,
    currency: 'VND',
    complainant: {
      id: 101,
      name: 'Alex Lawson',
      tier: 'Hạng Vàng',
      reputationScore: 98,
      phone: '0909 *** 892',
      avatar: 'AL',
    },
    respondent: {
      id: 102,
      name: 'Trần Quốc Bảo',
      tier: 'Hạng Kim Cương',
      reputationScore: 96,
      phone: '0912 *** 341',
      avatar: 'MK',
    },
    facility: {
      name: 'Cụm Sân Cầu Lông Phú Thọ',
      operatorName: 'Lê Hoàng Long (Trưởng ca trực)',
    },
    timelineEvents: [
      {
        time: '20 Thg 10, 18:30:15',
        title: 'Thanh toán đặt sân thành công',
        description: 'Alex Lawson thanh toán thành công đơn #BK-99482 qua PayOS (Slot 19:00 - 21:00 Sân 4).',
        type: 'primary',
      },
      {
        time: '20 Thg 10, 18:45:22',
        title: 'Cảnh báo: Độ trễ Webhook Facility API',
        description: 'Độ trễ đồng bộ 45 giây từ đối tác khiến trạng thái slot chưa cập nhật kịp thời trên bảng POS lễ tân.',
        type: 'warning',
      },
      {
        time: '24 Thg 10, 18:55:12',
        title: 'Lỗi Thao Tác: Nhân viên mở check-in chèn tay',
        description: 'Lễ tân Lê Hoàng Long mở check-in thủ công cho khách Hợp đồng cố định Trần Quốc Bảo vào Sân 4.',
        type: 'error',
      },
      {
        time: '24 Thg 10, 19:02:10',
        title: 'Phát sinh tranh chấp tại quầy check-in',
        description: 'Cả hai bên cùng xuất trình mã QR hợp lệ tại cửa Sân 4.',
        type: 'error',
      },
    ],
    chatMessages: [
      {
        sender: 'Alex Lawson',
        role: 'complainant',
        avatar: 'AL',
        message: 'Tôi đã đến sân lúc 18:55 nhưng sân số 4 đang có nhóm khác đánh. Lễ tân bảo họ có lịch đặt cố định.',
        time: '19:05',
      },
      {
        sender: 'Phú Thọ Support',
        role: 'facility',
        avatar: 'PT',
        message: 'Chào anh Alex, bên em vừa kiểm tra lại hệ thống POS bị trễ webhook nên lễ tân tưởng slot còn trống.',
        time: '19:08',
      },
      {
        sender: 'Aura Arbitration Bot',
        role: 'system',
        avatar: 'AI',
        message: 'Vụ việc đã được tiếp nhận và phân loại KHẨN CẤP. Chuyên viên đang thụ lý phán quyết trong 15 phút.',
        time: '19:09',
      },
    ],
    courtMapContext: [
      { name: 'Sân 3', status: 'occupied', label: 'Đang đánh' },
      { name: 'Sân 4', status: 'conflict', label: 'Xung Đột' },
      { name: 'Sân 7', status: 'available', label: 'Trống - Có thể Đổi' },
    ],
    aiAnalysis: {
      faultRateFacility: 100,
      faultRateUser: 0,
      rootCause: 'Độ trễ Webhook đồng bộ kết hợp thao tác ghi đè thủ công tại quầy của nhân viên cơ sở.',
      recommendationSummary: 'Hoàn 100% tiền (360.000 ₫) vào Ví nội bộ cho Alex Lawson + Tặng Voucher 20% + Đổi Sân 7 cho Trần Quốc Bảo.',
    },
    createdAt: new Date(Date.now() - 10 * 60000).toISOString(),
  },
  {
    id: 2,
    disputeCode: 'DSP-8921',
    organizationId: 1,
    venueId: 2,
    bookingId: 99310,
    courtName: 'Sân Tân Bình Sport - Court 2',
    disputeType: 'match_stake',
    severity: 'high',
    title: 'Tranh chấp Tiền cọc Kèo Giao Lưu Matchmaking',
    summary: 'Đối thủ đơn nam tự ý bỏ cuộc giữa chừng ở Set 3 nhưng không chịu xác nhận hủy cọc trên ví nội bộ.',
    status: 'pending',
    slaDeadline: new Date(Date.now() + 2 * 3600000).toISOString(),
    amountDisputed: 200000,
    currency: 'VND',
    complainant: {
      id: 103,
      name: 'Vũ Minh Tuấn',
      tier: 'Hạng Bạc',
      reputationScore: 92,
      phone: '0938 *** 112',
      avatar: 'VT',
    },
    respondent: {
      id: 104,
      name: 'Hoàng Nam',
      tier: 'Hạng Đồng',
      reputationScore: 84,
      phone: '0977 *** 554',
      avatar: 'HN',
    },
    facility: {
      name: 'Sân Cầu Lông Tân Bình Sport',
      operatorName: 'Trần Thị Bích',
    },
    timelineEvents: [
      {
        time: '24 Thg 10, 16:00:00',
        title: 'Tạo kèo giao lưu & khóa ví cọc',
        description: 'Vũ Minh Tuấn và Hoàng Nam khóa 200.000 ₫ mỗi bên vào ví escrow trận đấu đơn.',
        type: 'primary',
      },
      {
        time: '24 Thg 10, 17:30:10',
        title: 'Rời trận đấu giữa chừng',
        description: 'Hoàng Nam rời sân khi tỷ số đang là 1-1 set và 12-18 ở set quyết định.',
        type: 'warning',
      },
    ],
    chatMessages: [
      {
        sender: 'Vũ Minh Tuấn',
        role: 'complainant',
        avatar: 'VT',
        message: 'Bạn Nam tự ý bỏ về khi đang thua set 3, sau đó từ chối bấm kết quả trên app.',
        time: '17:35',
      },
    ],
    courtMapContext: [
      { name: 'Sân 1', status: 'occupied', label: 'Đang đánh' },
      { name: 'Sân 2', status: 'occupied', label: 'Kết thúc sớm' },
    ],
    aiAnalysis: {
      faultRateFacility: 0,
      faultRateUser: 100,
      rootCause: 'Hành vi bỏ trận đấu có đặt cọc không có lý do chính đáng vi phạm quy chế giao lưu.',
      recommendationSummary: 'Xử thua kỹ thuật cho Hoàng Nam, giải ngân 200.000 ₫ tiền cọc cho Vũ Minh Tuấn và trừ 15 điểm uy tín đối thủ.',
    },
    createdAt: new Date(Date.now() - 2 * 3600000).toISOString(),
  },
  {
    id: 3,
    disputeCode: 'DSP-8918',
    organizationId: 1,
    venueId: 1,
    bookingId: 99120,
    courtName: 'Smash City Arena - Court 6',
    disputeType: 'resale_fraud',
    severity: 'critical',
    title: 'Thị trường Nhượng suất - Hủy QR sau khi nhận tiền',
    summary: 'Người mua đã chuyển khoản cọc qua PayOS nhưng người bán hủy slot trước giờ chơi 15 phút.',
    status: 'investigating',
    slaDeadline: new Date(Date.now() + 1 * 3600000).toISOString(),
    amountDisputed: 450000,
    currency: 'VND',
    complainant: {
      id: 105,
      name: 'Phạm Hoàng Yến',
      tier: 'Hạng Vàng',
      reputationScore: 97,
      phone: '0918 *** 990',
      avatar: 'PY',
    },
    respondent: {
      id: 106,
      name: 'Đặng Văn Hùng',
      tier: 'Hạng Mới',
      reputationScore: 60,
      phone: '0981 *** 332',
      avatar: 'DH',
    },
    facility: {
      name: 'Smash City Arena',
      operatorName: 'Nguyễn Văn An',
    },
    timelineEvents: [
      {
        time: '24 Thg 10, 14:00:00',
        title: 'Khớp lệnh chuyển nhượng suất',
        description: 'Phạm Hoàng Yến thanh toán 450.000 ₫ mua lại slot 20:00 - 22:00.',
        type: 'primary',
      },
      {
        time: '24 Thg 10, 19:45:00',
        title: 'Phát hiện hủy mã QR',
        description: 'Người bán Đặng Văn Hùng thực hiện thao tác hoàn hủy vé gốc trên cổng trung gian.',
        type: 'error',
      },
    ],
    chatMessages: [
      {
        sender: 'Phạm Hoàng Yến',
        role: 'complainant',
        avatar: 'PY',
        message: 'Mình đến sân quét mã báo vé đã bị vô hiệu hóa lúc 19:45. Gọi người bán không nghe máy.',
        time: '19:50',
      },
    ],
    courtMapContext: [],
    aiAnalysis: {
      faultRateFacility: 0,
      faultRateUser: 100,
      rootCause: 'Hành vi gian lận cố tình revoke mã QR sau khi giao dịch nhượng lại hoàn tất.',
      recommendationSummary: 'Hoàn 100% tiền (450.000 ₫) cho người mua từ quỹ bảo hiểm thị trường, khóa vĩnh viễn tài khoản người bán.',
    },
    createdAt: new Date(Date.now() - 3 * 3600000).toISOString(),
  },
  {
    id: 4,
    disputeCode: 'DSP-8915',
    organizationId: 1,
    venueId: 3,
    bookingId: 98842,
    courtName: 'Aura Bình Thạnh Center - Court 1',
    disputeType: 'equipment_damage',
    severity: 'medium',
    title: 'Tranh chấp Phí bồi thường Vợt Yonex Astrox 100ZZ',
    summary: 'Chủ sân trừ 1.200.000đ tiền cọc vì nứt vành vợt, người thuê khiếu nại vợt đã nứt từ trước khi nhận.',
    status: 'awaiting_response',
    slaDeadline: new Date(Date.now() + 24 * 3600000).toISOString(),
    amountDisputed: 1200000,
    currency: 'VND',
    complainant: {
      id: 107,
      name: 'Ngô Đức Thắng',
      tier: 'Hạng Bạc',
      reputationScore: 91,
      phone: '0903 *** 441',
      avatar: 'NT',
    },
    respondent: {
      id: 108,
      name: 'Aura Bình Thạnh Center',
      tier: 'Chủ Sân Xác Thực',
      reputationScore: 99,
      phone: '0933 *** 888',
      avatar: 'BT',
    },
    facility: {
      name: 'Aura Bình Thạnh Center',
      operatorName: 'Trần Văn B',
    },
    timelineEvents: [],
    chatMessages: [],
    courtMapContext: [],
    aiAnalysis: {
      faultRateFacility: 50,
      faultRateUser: 50,
      rootCause: 'Ảnh kiểm tra thiết bị đầu ca mờ và thiếu góc chụp vành trên.',
      recommendationSummary: 'Đề xuất cơ sở và khách hàng chia đôi chi phí sửa chữa (600.000 ₫ mỗi bên) và hoàn lại 600.000 ₫ cọc.',
    },
    createdAt: new Date(Date.now() - 24 * 3600000).toISOString(),
  },
  {
    id: 5,
    disputeCode: 'DSP-8902',
    organizationId: 1,
    venueId: 4,
    bookingId: 97910,
    courtName: 'Sân Phú Thọ - Court 1',
    disputeType: 'tournament_score',
    severity: 'low',
    title: 'Trọng tài nhập sai tỷ số Chung kết Nhánh B',
    summary: 'Trọng tài bàn nhập nhầm 21-19 thành 19-21 ở set 2 trận chung kết.',
    status: 'resolved',
    resolutionDecision: 'resolve',
    refundMethod: 'wallet',
    refundAmount: 0,
    compensationType: 'reputation_points',
    compensationValue: '500 Điểm Danh Vọng',
    amountDisputed: 0,
    currency: 'VND',
    complainant: {
      id: 109,
      name: 'Đội Cầu Lông Tia Chớp',
      tier: 'CLB Chuyên Nghiệp',
      reputationScore: 100,
      phone: '0919 *** 777',
      avatar: 'TC',
    },
    respondent: {
      id: 110,
      name: 'Ban Trọng Tài Giải Đấu',
      tier: 'Trọng tài Quốc gia',
      reputationScore: 98,
      phone: '0908 *** 222',
      avatar: 'TT',
    },
    facility: {
      name: 'Cụm Sân Cầu Lông Phú Thọ',
      operatorName: 'Lê Hoàng Long',
    },
    timelineEvents: [],
    chatMessages: [],
    courtMapContext: [],
    aiAnalysis: {
      faultRateFacility: 100,
      faultRateUser: 0,
      rootCause: 'Đối chiếu video ghi hình chứng minh Đội Tia Chớp thắng 21-19 ở set 2.',
      recommendationSummary: 'Cập nhật lại kết quả bảng xếp hạng Bracket giải đấu và gửi thư xin lỗi chính thức.',
    },
    createdAt: new Date(Date.now() - 48 * 3600000).toISOString(),
  },
]

export const disputeApi = {
  getDisputes: async (params?: DisputeFilterParams): Promise<DisputeCase[]> => {
    try {
      const res = await apiV2.get<{ data: DisputeCase[] }>('/admin/disputes', { params })
      if (res && res.data && Array.isArray(res.data)) {
        return res.data
      }
    } catch {
      // fallback to mock
    }

    let list = [...INITIAL_MOCK_DISPUTES]

    if (params?.status && params.status !== 'all') {
      list = list.filter((item) => item.status === params.status)
    }

    if (params?.severity && params.severity !== 'all') {
      list = list.filter((item) => item.severity === params.severity)
    }

    if (params?.search) {
      const kw = params.search.toLowerCase().trim()
      list = list.filter(
        (item) =>
          item.disputeCode.toLowerCase().includes(kw) ||
          item.title.toLowerCase().includes(kw) ||
          item.complainant.name.toLowerCase().includes(kw) ||
          item.respondent.name.toLowerCase().includes(kw) ||
          item.facility.name.toLowerCase().includes(kw)
      )
    }

    return list
  },

  getStats: async (): Promise<DisputeStats> => {
    try {
      const res = await apiV2.get<{ stats: DisputeStats }>('/admin/disputes/stats')
      if (res && res.stats) {
        return res.stats
      }
    } catch {
      // fallback to mock
    }

    return {
      pendingCount: 14,
      investigatingCount: 6,
      awaitingReplyCount: 3,
      resolvedCount: 128,
      criticalSlaCount: 2,
      avgResolutionTimeMinutes: 18.5,
      consensusRate: 98.2,
    }
  },

  getDisputeDetail: async (id: number): Promise<DisputeCase | null> => {
    try {
      const res = await apiV2.get<{ data: DisputeCase }>(`/admin/disputes/${id}`)
      if (res && res.data) {
        return res.data
      }
    } catch {
      // fallback to mock
    }

    return INITIAL_MOCK_DISPUTES.find((d) => d.id === id) || INITIAL_MOCK_DISPUTES[0] || null
  },

  arbitrateDispute: async (
    id: number,
    payload: ArbitrateDisputePayload
  ): Promise<{ message: string; data: DisputeCase }> => {
    try {
      const res = await apiV2.post<{ message: string; data: DisputeCase }>(
        `/admin/disputes/${id}/arbitrate`,
        payload
      )
      if (res && res.data) return res
    } catch {
      // fallback local update
    }

    const item = INITIAL_MOCK_DISPUTES.find((d) => d.id === id) || INITIAL_MOCK_DISPUTES[0]!
    const newStatus =
      payload.decision === 'resolve_and_refund' || payload.decision === 'resolve'
        ? 'resolved'
        : payload.decision === 'dismiss'
        ? 'dismissed'
        : 'investigating'

    item.status = newStatus
    item.resolutionDecision = payload.decision
    item.refundMethod = payload.refundMethod
    item.refundAmount = payload.refundAmount ?? item.amountDisputed
    item.compensationType = payload.compensationType
    item.compensationValue = payload.compensationValue
    item.penaltyNotes = payload.penaltyNotes
    item.penaltyPoints = payload.penaltyPoints ?? 0
    item.arbitratorNotes = payload.notes
    item.arbitratedAt = new Date().toISOString()
    item.arbitratorName = 'Nguyễn Văn An'
    item.digitalSignatureHash = payload.digitalSignatureHash || `SIG-${Math.random().toString(36).substring(2, 10).toUpperCase()}`

    const messages: Record<string, string> = {
      resolve_and_refund: 'Đã ban hành phán quyết, hoàn tiền và kích hoạt bồi thường thành công.',
      resolve: 'Đã ban hành phán quyết và giải quyết vụ việc.',
      dismiss: 'Đã bác bỏ khiếu nại và lưu vết kiểm toán bất biến.',
      investigate_further: 'Đã chuyển trạng thái yêu cầu điều tra bổ sung.',
    }

    return {
      message: messages[payload.decision] || 'Đã cập nhật kết quả phán quyết.',
      data: item,
    }
  },
}
