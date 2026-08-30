import { ref, computed, onMounted } from 'vue'
import type {
  MembershipPlan,
  LoyaltyConversionConfig,
  MultiplierRule,
  PlanVersionAudit,
  MembershipKpiStats,
} from '../types/membership'
import { membershipApi } from '../api/membershipApi'

const DEFAULT_PLANS: MembershipPlan[] = [
  {
    id: 'silver-pass',
    name: 'Silver Pass',
    slug: 'silver-pass',
    code: 'SILVER',
    description: 'Gói cơ bản cho người chơi thường xuyên',
    price: 500000,
    billingCycle: 'monthly',
    version: 'v2.1',
    status: 'active',
    perks: [
      'Giảm 10% đặt sân các giờ off-peak',
      'Tích lũy x1.2 điểm thưởng',
      'Đặt sân trước 7 ngày',
    ],
    courtDiscountPercent: 10,
    pointMultiplier: 1.2,
    advanceBookingDays: 7,
    freeWeekendHours: 0,
    cancellationFeeWaived: false,
    activeSubscribers: 86,
    isPopular: false,
    updatedAt: '2026-08-15T10:00:00Z',
  },
  {
    id: 'gold-elite',
    name: 'Gold Elite',
    slug: 'gold-elite',
    code: 'GOLD',
    description: 'Trải nghiệm cao cấp và linh hoạt tối đa',
    price: 1200000,
    billingCycle: 'monthly',
    version: 'v3.0',
    status: 'active',
    perks: [
      'Giảm 20% đặt sân mọi khung giờ',
      'Tích lũy x2 điểm thưởng',
      'Đặt sân trước 14 ngày & Hủy không phí',
      'Tặng 2 giờ chơi cuối tuần mỗi tháng',
    ],
    courtDiscountPercent: 20,
    pointMultiplier: 2.0,
    advanceBookingDays: 14,
    freeWeekendHours: 2,
    cancellationFeeWaived: true,
    activeSubscribers: 142,
    isPopular: true,
    highlightColor: '#ffd1dc',
    updatedAt: '2026-08-30T10:45:00Z',
  },
]

const DEFAULT_LOYALTY_CONFIG: LoyaltyConversionConfig = {
  spendAmountVnd: 1000,
  pointsEarned: 1,
  pointsRedeemed: 100,
  rewardValueVnd: 5000,
  updatedAt: '2026-08-25T08:00:00Z',
}

const DEFAULT_MULTIPLIERS: MultiplierRule[] = [
  {
    id: 'mult-offpeak',
    name: 'Giờ Vàng (Off-peak)',
    description: 'T2-T6, 08:00 - 15:00',
    multiplier: 1.5,
    tag: 'Off-peak',
    schedule: 'Mon-Fri 08:00-15:00',
    isActive: true,
  },
  {
    id: 'mult-birthday',
    name: 'Sinh nhật hội viên',
    description: 'Áp dụng trong tháng sinh nhật',
    multiplier: 2.0,
    tag: 'Birthday',
    schedule: 'Birthday Month',
    isActive: true,
  },
  {
    id: 'mult-weekend-tourney',
    name: 'Tuần Lễ Giải Đấu Aura',
    description: 'T7-CN khi tham gia giải đấu cụm sân',
    multiplier: 2.5,
    tag: 'Tournament',
    schedule: 'Sat-Sun Tourney Days',
    isActive: false,
  },
]

const DEFAULT_AUDITS: PlanVersionAudit[] = [
  {
    id: 'aud-1',
    planId: 'gold-elite',
    planName: 'Gold Elite',
    version: 'v3.0',
    status: 'active',
    updatedAt: 'Hôm nay, 10:45 AM',
    updatedBy: 'Admin',
    changes: [
      'Thêm quyền lợi: Hủy không phí trước 24h',
      'Tăng giảm giá giờ vàng từ 15% lên 20%',
    ],
    activeSubscribersCount: 142,
  },
  {
    id: 'aud-2',
    planId: 'silver-pass',
    planName: 'Silver Pass',
    version: 'v2.1',
    status: 'active',
    updatedAt: '15 Th08, 2026',
    updatedBy: 'System',
    changes: [
      'Sửa lỗi hiển thị % giảm giá trong app. Không thay đổi policy.',
    ],
    activeSubscribersCount: 86,
  },
  {
    id: 'aud-3',
    planId: 'gold-elite-v2',
    planName: 'Gold Elite',
    version: 'v2.0',
    status: 'archived',
    updatedAt: '01 Th01, 2026',
    updatedBy: 'Admin',
    changes: [
      'Gói cũ duy trì giảm 15% giờ vàng và x1.5 điểm thưởng.',
    ],
    activeSubscribersCount: 34,
    note: 'Gói cũ đang duy trì cho thành viên gia hạn tự động.',
  },
]

const DEFAULT_STATS: MembershipKpiStats = {
  totalVipMembers: 228,
  monthlyRecurringRevenue: 213400000,
  totalPointsIssued: 485000,
  redemptionRatePercent: 68.4,
}

export function useAdminMembership() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const notice = ref<string | null>(null)

  const plans = ref<MembershipPlan[]>(DEFAULT_PLANS)
  const loyaltyConfig = ref<LoyaltyConversionConfig>(DEFAULT_LOYALTY_CONFIG)
  const multiplierRules = ref<MultiplierRule[]>(DEFAULT_MULTIPLIERS)
  const versionHistory = ref<PlanVersionAudit[]>(DEFAULT_AUDITS)
  const stats = ref<MembershipKpiStats>(DEFAULT_STATS)

  // Modals state
  const isPlanModalOpen = ref(false)
  const isRuleModalOpen = ref(false)
  const isVersionModalOpen = ref(false)
  const isHistoryModalOpen = ref(false)

  const editingPlan = ref<MembershipPlan | null>(null)
  const editingRule = ref<MultiplierRule | null>(null)
  const targetPlanForVersion = ref<MembershipPlan | null>(null)

  function showNotice(msg: string) {
    notice.value = msg
    error.value = null
    setTimeout(() => {
      if (notice.value === msg) notice.value = null
    }, 4000)
  }

  function showError(msg: string) {
    error.value = msg
    notice.value = null
    setTimeout(() => {
      if (error.value === msg) error.value = null
    }, 5000)
  }

  async function loadData() {
    loading.value = true
    error.value = null
    try {
      const [resPlans, resConfig, resRules, resAudits, resStats] = await Promise.allSettled([
        membershipApi.getPlans(),
        membershipApi.getLoyaltyConfig(),
        membershipApi.getMultiplierRules(),
        membershipApi.getVersionHistory(),
        membershipApi.getStats(),
      ])

      if (resPlans.status === 'fulfilled' && Array.isArray(resPlans.value) && resPlans.value.length > 0) {
        plans.value = resPlans.value
      }
      if (resConfig.status === 'fulfilled' && resConfig.value && resConfig.value.spendAmountVnd) {
        loyaltyConfig.value = resConfig.value
      }
      if (resRules.status === 'fulfilled' && Array.isArray(resRules.value) && resRules.value.length > 0) {
        multiplierRules.value = resRules.value
      }
      if (resAudits.status === 'fulfilled' && Array.isArray(resAudits.value) && resAudits.value.length > 0) {
        versionHistory.value = resAudits.value
      }
      if (resStats.status === 'fulfilled' && resStats.value && resStats.value.totalVipMembers) {
        stats.value = resStats.value
      }
    } catch (e: any) {
      // Fallback kept to mock defaults
    } finally {
      loading.value = false
    }
  }

  function openCreatePlanModal() {
    editingPlan.value = null
    isPlanModalOpen.value = true
  }

  function openEditPlanModal(plan: MembershipPlan) {
    editingPlan.value = { ...plan, perks: [...plan.perks] }
    isPlanModalOpen.value = true
  }

  function openManageVersionModal(plan: MembershipPlan) {
    targetPlanForVersion.value = plan
    isVersionModalOpen.value = true
  }

  async function savePlan(planData: Partial<MembershipPlan>, createNewVersion = false) {
    loading.value = true
    try {
      if (editingPlan.value && !createNewVersion) {
        // Update in-place
        const idx = plans.value.findIndex((p) => p.id === editingPlan.value?.id)
        if (idx !== -1) {
          plans.value[idx] = {
            ...plans.value[idx],
            ...planData,
            updatedAt: new Date().toISOString(),
          } as MembershipPlan
        }
        showNotice(`Đã cập nhật thông tin gói ${planData.name || editingPlan.value.name}!`)
      } else if (editingPlan.value && createNewVersion) {
        // Increment version e.g. v2.1 -> v2.2 or v3.0 -> v3.1
        const curVer = editingPlan.value.version || 'v1.0'
        const parts = curVer.replace('v', '').split('.')
        const major = parseInt(parts[0] || '1', 10)
        const minor = parseInt(parts[1] || '0', 10) + 1
        const nextVer = `v${major}.${minor}`

        const updatedPlan: MembershipPlan = {
          ...editingPlan.value,
          ...planData,
          version: nextVer,
          updatedAt: new Date().toISOString(),
        } as MembershipPlan

        const idx = plans.value.findIndex((p) => p.id === editingPlan.value?.id)
        if (idx !== -1) {
          plans.value[idx] = updatedPlan
        }

        // Add version history record
        versionHistory.value.unshift({
          id: `aud-${Date.now()}`,
          planId: updatedPlan.id,
          planName: updatedPlan.name,
          version: nextVer,
          status: 'active',
          updatedAt: 'Vừa xong',
          updatedBy: 'Admin',
          changes: [
            `Cập nhật giá niêm yết: ${new Intl.NumberFormat('vi-VN').format(updatedPlan.price)}đ`,
            `Điều chỉnh quyền lợi gói và lưu thành ${nextVer}`,
          ],
          activeSubscribersCount: updatedPlan.activeSubscribers || 0,
        })

        showNotice(`Đã lưu phiên bản mới ${nextVer} cho gói ${updatedPlan.name}!`)
      } else {
        // Create brand new plan
        const newId = `plan-${Date.now()}`
        const newPlan: MembershipPlan = {
          id: newId,
          name: planData.name || 'Gói Thành Viên Mới',
          slug: (planData.name || 'goi-moi').toLowerCase().replace(/\s+/g, '-'),
          code: (planData.code || 'VIP').toUpperCase(),
          description: planData.description || 'Gói quyền lợi ưu đãi cho thành viên',
          price: planData.price || 800000,
          billingCycle: planData.billingCycle || 'monthly',
          version: 'v1.0',
          status: 'active',
          perks: planData.perks?.length ? planData.perks : ['Ưu đãi đặt sân giờ vàng', 'Tích điểm thành viên'],
          courtDiscountPercent: planData.courtDiscountPercent || 15,
          pointMultiplier: planData.pointMultiplier || 1.5,
          advanceBookingDays: planData.advanceBookingDays || 7,
          freeWeekendHours: planData.freeWeekendHours || 0,
          cancellationFeeWaived: planData.cancellationFeeWaived || false,
          activeSubscribers: 0,
          updatedAt: new Date().toISOString(),
        }
        plans.value.push(newPlan)

        versionHistory.value.unshift({
          id: `aud-${Date.now()}`,
          planId: newId,
          planName: newPlan.name,
          version: 'v1.0',
          status: 'active',
          updatedAt: 'Vừa xong',
          updatedBy: 'Admin',
          changes: ['Khởi tạo gói hội viên mới'],
          activeSubscribersCount: 0,
        })

        showNotice(`Đã tạo thành công gói ${newPlan.name}!`)
      }
      isPlanModalOpen.value = false
    } catch (e: any) {
      showError(e?.message || 'Có lỗi khi lưu thông tin gói hội viên.')
    } finally {
      loading.value = false
    }
  }

  function openCreateRuleModal() {
    editingRule.value = null
    isRuleModalOpen.value = true
  }

  function openEditRuleModal(rule: MultiplierRule) {
    editingRule.value = { ...rule }
    isRuleModalOpen.value = true
  }

  function toggleRuleActive(ruleId: string) {
    const r = multiplierRules.value.find((item) => item.id === ruleId)
    if (r) {
      r.isActive = !r.isActive
      showNotice(`${r.isActive ? 'Đã kích hoạt' : 'Đã tạm dừng'} quy tắc: ${r.name}`)
    }
  }

  function saveRule(ruleData: Partial<MultiplierRule>) {
    if (editingRule.value) {
      const idx = multiplierRules.value.findIndex((r) => r.id === editingRule.value?.id)
      if (idx !== -1 && multiplierRules.value[idx]) {
        multiplierRules.value[idx] = {
          ...multiplierRules.value[idx],
          ...ruleData,
        } as MultiplierRule
      }
      showNotice(`Đã cập nhật quy tắc: ${ruleData.name || editingRule.value.name}`)
    } else {
      const newRule: MultiplierRule = {
        id: `rule-${Date.now()}`,
        name: ruleData.name || 'Quy tắc nhân điểm mới',
        description: ruleData.description || 'Áp dụng theo điều kiện cấu hình',
        multiplier: ruleData.multiplier || 1.5,
        tag: ruleData.tag || 'Special',
        schedule: ruleData.schedule || 'Tất cả các ngày',
        isActive: ruleData.isActive ?? true,
      }
      multiplierRules.value.push(newRule)
      showNotice(`Đã thêm quy tắc nhân điểm: ${newRule.name}`)
    }
    isRuleModalOpen.value = false
  }

  function deleteRule(ruleId: string) {
    const idx = multiplierRules.value.findIndex((r) => r.id === ruleId)
    if (idx !== -1 && multiplierRules.value[idx]) {
      const name = multiplierRules.value[idx]?.name || ''
      multiplierRules.value.splice(idx, 1)
      showNotice(`Đã xóa quy tắc nhân điểm: ${name}`)
    }
  }

  function updateLoyaltyRates(payload: Partial<LoyaltyConversionConfig>) {
    loyaltyConfig.value = {
      ...loyaltyConfig.value,
      ...payload,
      updatedAt: new Date().toISOString(),
    }
    showNotice('Đã cập nhật cấu hình tỷ lệ tích điểm và đổi thưởng thành công!')
  }

  const activePlans = computed(() => plans.value.filter((p) => p.status === 'active'))

  onMounted(() => {
    loadData()
  })

  return {
    loading,
    error,
    notice,
    plans,
    activePlans,
    loyaltyConfig,
    multiplierRules,
    versionHistory,
    stats,
    isPlanModalOpen,
    isRuleModalOpen,
    isVersionModalOpen,
    isHistoryModalOpen,
    editingPlan,
    editingRule,
    targetPlanForVersion,
    loadData,
    openCreatePlanModal,
    openEditPlanModal,
    openManageVersionModal,
    savePlan,
    openCreateRuleModal,
    openEditRuleModal,
    toggleRuleActive,
    saveRule,
    deleteRule,
    updateLoyaltyRates,
    showNotice,
    showError,
  }
}
