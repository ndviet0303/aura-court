import { ref, computed } from 'vue'
import { socialApi } from '../api/socialApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type {
  SocialMatch,
  DropInSessionBill,
  DropInSessionCategory,
  BadmintonSkillLabel,
} from '../types/social'

export function useDropInSessionBill() {
  const loading = ref(false)
  const submitting = ref(false)
  const error = ref('')
  const conflict = ref('')
  const notice = ref('')

  const currentMatch = ref<SocialMatch | null>(null)
  const currentBill = ref<DropInSessionBill | null>(null)

  // Form Inputs
  const actualHeadcount = ref(4)
  const courtDurationMinutes = ref(120)
  const courtHourlyRate = ref(150000)
  const shuttlecockCount = ref(8)
  const shuttlecockUnitPrice = ref(25000)

  // Client-side realtime calculation
  const courtSubtotal = computed(() => {
    return Math.round((courtDurationMinutes.value * courtHourlyRate.value) / 60)
  })

  const shuttlecockSubtotal = computed(() => {
    return Math.round(shuttlecockCount.value * shuttlecockUnitPrice.value)
  })

  const totalAmount = computed(() => {
    return courtSubtotal.value + shuttlecockSubtotal.value
  })

  const perPersonAmount = computed(() => {
    const divisor = Math.max(1, actualHeadcount.value)
    return Math.round(totalAmount.value / divisor)
  })

  const isApproximate = computed(() => {
    const divisor = Math.max(1, actualHeadcount.value)
    return totalAmount.value % divisor !== 0
  })

  const isFinalized = computed(() => {
    return currentBill.value?.status === 'finalized'
  })

  async function loadBill(matchId: number | string) {
    loading.value = true
    error.value = ''
    conflict.value = ''
    try {
      const matchRes = await socialApi.getMatch(matchId)
      const m = matchRes?.match as unknown as Record<string, unknown> | undefined
      if (m) {
        currentMatch.value = {
          id: (m.id as number) || Number(matchId),
          title: (m.title as string) || '',
          sport_type: 'badminton',
          sport_name: 'Cầu lông',
          sport_icon: 'sports_tennis',
          venue_name:
            (((m.court as Record<string, unknown>)?.venue as Record<string, unknown>)
              ?.name as string) ||
            (m.venue_name as string) ||
            'Cơ sở',
          court_name:
            ((m.court as Record<string, unknown>)?.name as string) ||
            (m.court_name as string) ||
            'Sân',
          city: (m.city as string) || 'Hà Nội',
          starts_at: (m.starts_at as string) || '',
          ends_at: (m.ends_at as string) || undefined,
          joined_count: (m.joined_count as number) || 0,
          required_participants: (m.required_participants as number) || 4,
          visibility: 'public',
          host_name:
            (m.host_name as string) ||
            ((m.creator as Record<string, unknown>)?.name as string) ||
            'Chủ cơ sở',
          is_owner_hosted: true,
          category: (m.category as DropInSessionCategory) || 'giao lưu',
          skill_levels: (m.skill_levels as BadmintonSkillLabel[]) || [],
          status: (m.status as SocialMatch['status']) || 'open',
          participants: [],
        }
      }

      const billRes = await socialApi.getSessionBill(matchId)
      if (billRes?.bill) {
        currentBill.value = billRes.bill
        actualHeadcount.value = billRes.bill.actual_headcount || actualHeadcount.value
        courtDurationMinutes.value =
          billRes.bill.court_duration_minutes || courtDurationMinutes.value
        courtHourlyRate.value = billRes.bill.court_hourly_rate || courtHourlyRate.value
        shuttlecockCount.value = billRes.bill.shuttlecock_count ?? shuttlecockCount.value
        shuttlecockUnitPrice.value =
          billRes.bill.shuttlecock_unit_price ?? shuttlecockUnitPrice.value
      } else {
        currentBill.value = null
        if (currentMatch.value) {
          actualHeadcount.value = Math.max(1, currentMatch.value.joined_count || 4)
        }
      }
    } catch (e: unknown) {
      currentBill.value = null
      error.value = errorMessage(e, 'Không thể tải thông tin bảng chi phí.')
    } finally {
      loading.value = false
    }
  }

  async function endSession(matchId: number | string) {
    submitting.value = true
    error.value = ''
    try {
      await socialApi.endSession(matchId)
      if (currentMatch.value) {
        currentMatch.value.status = 'ended'
      }
      notice.value = 'Đã kết thúc buổi chơi. Bây giờ bạn có thể nhập bảng chi phí thực tế.'
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể kết thúc buổi chơi.')
    } finally {
      submitting.value = false
    }
  }

  async function saveDraft(matchId: number | string) {
    submitting.value = true
    error.value = ''
    conflict.value = ''
    try {
      const payload = {
        actual_headcount: actualHeadcount.value,
        court_duration_minutes: courtDurationMinutes.value,
        court_hourly_rate: courtHourlyRate.value,
        shuttlecock_count: Math.floor(shuttlecockCount.value),
        shuttlecock_unit_price: courtHourlyRate.value >= 0 ? shuttlecockUnitPrice.value : 0,
        version: currentBill.value?.version,
      }

      const res = await socialApi.saveDraftBill(matchId, payload)
      currentBill.value = res.bill
      notice.value = 'Đã lưu bản nháp chi phí thành công.'
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể lưu bản nháp chi phí.')
    } finally {
      submitting.value = false
    }
  }

  async function finalizeBill(matchId: number | string) {
    submitting.value = true
    error.value = ''
    conflict.value = ''
    try {
      const payload = {
        actual_headcount: actualHeadcount.value,
        court_duration_minutes: courtDurationMinutes.value,
        court_hourly_rate: courtHourlyRate.value,
        shuttlecock_count: Math.floor(shuttlecockCount.value),
        shuttlecock_unit_price: shuttlecockUnitPrice.value,
        version: currentBill.value?.version,
      }

      const res = await socialApi.finalizeSessionBill(matchId, payload)
      currentBill.value = res.bill
      notice.value = 'Chốt bảng chi phí buổi chơi thành công!'
    } catch (e: unknown) {
      const msg = errorMessage(e, 'Không thể chốt bảng chi phí.')
      if (msg.includes('mới hơn') || msg.includes('conflict') || msg.includes('chốt')) {
        conflict.value = msg
        // Reload latest from server
        await loadBill(matchId)
      } else {
        error.value = msg
      }
    } finally {
      submitting.value = false
    }
  }

  return {
    loading,
    submitting,
    error,
    conflict,
    notice,
    currentMatch,
    currentBill,
    actualHeadcount,
    courtDurationMinutes,
    courtHourlyRate,
    shuttlecockCount,
    shuttlecockUnitPrice,
    courtSubtotal,
    shuttlecockSubtotal,
    totalAmount,
    perPersonAmount,
    isApproximate,
    isFinalized,
    loadBill,
    endSession,
    saveDraft,
    finalizeBill,
  }
}
