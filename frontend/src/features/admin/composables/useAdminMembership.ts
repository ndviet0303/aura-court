import { ref, computed, onMounted } from 'vue'
import type {
  MembershipPlan,
  LoyaltyConversionConfig,
  MultiplierRule,
  PlanVersionAudit,
  MembershipKpiStats,
} from '../types/membership'
import { membershipApi } from '../api/membershipApi'
import { errorMessage } from '@/core/utils/errorMessage'

export function useAdminMembership() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const notice = ref<string | null>(null)

  const plans = ref<MembershipPlan[]>([])
  const loyaltyConfig = ref<LoyaltyConversionConfig>({
    spendAmountVnd: 1000,
    pointsEarned: 1,
    pointsRedeemed: 100,
    rewardValueVnd: 5000,
    updatedAt: new Date().toISOString(),
  })
  const multiplierRules = ref<MultiplierRule[]>([])
  const versionHistory = ref<PlanVersionAudit[]>([])
  const stats = ref<MembershipKpiStats>({
    totalVipMembers: 0,
    monthlyRecurringRevenue: 0,
    totalPointsIssued: 0,
    redemptionRatePercent: 0,
  })

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

      if (resPlans.status === 'fulfilled' && Array.isArray(resPlans.value)) {
        plans.value = resPlans.value
      }
      if (resConfig.status === 'fulfilled' && resConfig.value?.spendAmountVnd) {
        loyaltyConfig.value = resConfig.value
      }
      if (resRules.status === 'fulfilled' && Array.isArray(resRules.value)) {
        multiplierRules.value = resRules.value
      }
      if (resAudits.status === 'fulfilled' && Array.isArray(resAudits.value)) {
        versionHistory.value = resAudits.value
      }
      if (resStats.status === 'fulfilled' && resStats.value) {
        stats.value = resStats.value
      }
    } catch (e: unknown) {
      showError(errorMessage(e, 'Không thể tải thông tin gói hội viên và điểm thưởng.'))
    } finally {
      loading.value = false
    }
  }

  function openCreatePlanModal() {
    editingPlan.value = null
    isPlanModalOpen.value = true
  }

  function openEditPlanModal(plan: MembershipPlan) {
    editingPlan.value = { ...plan, perks: [...(plan.perks || [])] }
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
        await membershipApi.updatePlan(editingPlan.value.id, planData)
        showNotice(`Đã cập nhật thông tin gói ${planData.name || editingPlan.value.name}!`)
      } else if (editingPlan.value && createNewVersion) {
        const curVer = editingPlan.value.version || 'v1.0'
        const parts = curVer.replace('v', '').split('.')
        const major = parseInt(parts[0] || '1', 10)
        const minor = parseInt(parts[1] || '0', 10) + 1
        const nextVer = `v${major}.${minor}`

        await membershipApi.updatePlan(editingPlan.value.id, {
          ...planData,
          version: nextVer,
        })
        showNotice(`Đã lưu phiên bản mới ${nextVer} cho gói ${planData.name || editingPlan.value.name}!`)
      } else {
        await membershipApi.createPlan(planData)
        showNotice(`Đã tạo thành công gói ${planData.name}!`)
      }
      await loadData()
      isPlanModalOpen.value = false
    } catch (e: unknown) {
      showError(errorMessage(e, 'Có lỗi khi lưu thông tin gói hội viên.'))
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

  async function toggleRuleActive(ruleId: string) {
    const r = multiplierRules.value.find((item) => item.id === ruleId)
    if (r) {
      try {
        await membershipApi.updateMultiplierRule(ruleId, { isActive: !r.isActive })
        r.isActive = !r.isActive
        showNotice(`${r.isActive ? 'Đã kích hoạt' : 'Đã tạm dừng'} quy tắc: ${r.name}`)
      } catch (e: unknown) {
        showError(errorMessage(e, 'Không thể thay đổi trạng thái quy tắc.'))
      }
    }
  }

  async function saveRule(ruleData: Partial<MultiplierRule>) {
    loading.value = true
    try {
      if (editingRule.value) {
        await membershipApi.updateMultiplierRule(editingRule.value.id, ruleData)
        showNotice(`Đã cập nhật quy tắc: ${ruleData.name || editingRule.value.name}`)
      } else {
        await membershipApi.createMultiplierRule(ruleData)
        showNotice(`Đã thêm quy tắc nhân điểm: ${ruleData.name}`)
      }
      await loadData()
      isRuleModalOpen.value = false
    } catch (e: unknown) {
      showError(errorMessage(e, 'Có lỗi khi lưu quy tắc nhân điểm.'))
    } finally {
      loading.value = false
    }
  }

  async function deleteRule(ruleId: string) {
    loading.value = true
    try {
      await membershipApi.deleteMultiplierRule(ruleId)
      showNotice('Đã xóa quy tắc nhân điểm thành công!')
      await loadData()
    } catch (e: unknown) {
      showError(errorMessage(e, 'Không thể xóa quy tắc nhân điểm.'))
    } finally {
      loading.value = false
    }
  }

  async function updateLoyaltyRates(payload: Partial<LoyaltyConversionConfig>) {
    loading.value = true
    try {
      await membershipApi.updateLoyaltyConfig(payload)
      showNotice('Đã cập nhật cấu hình tỷ lệ tích điểm và đổi thưởng thành công!')
      await loadData()
    } catch (e: unknown) {
      showError(errorMessage(e, 'Không thể cập nhật cấu hình tích điểm.'))
    } finally {
      loading.value = false
    }
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
