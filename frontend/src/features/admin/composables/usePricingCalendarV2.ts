import { ref, computed } from 'vue'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Venue, SportZone, Court } from '@/features/court/types/court'
import type {
  PriceRuleItem,
  PriceRuleFormPayload,
  PriceQuoteResponse,
  PriceRuleType,
} from '../types/pricing'

export function usePricingCalendarV2() {
  const loading = ref(false)
  const saving = ref(false)
  const simulating = ref(false)
  const applyingPreset = ref(false)
  const notice = ref('')
  const error = ref('')

  const activeTab = ref<'rules' | 'matrix'>('rules')

  // Modals
  const showSimulatorModal = ref(false)
  const showRuleModal = ref(false)
  const showPresetsModal = ref(false)
  const editingRule = ref<PriceRuleItem | null>(null)

  // Data
  const rules = ref<PriceRuleItem[]>([])
  const venues = ref<Venue[]>([])
  const sportZones = ref<SportZone[]>([])
  const courts = ref<Court[]>([])

  // Filters
  const searchQuery = ref('')
  const selectedVenueId = ref<number | 'all'>('all')
  const selectedCourtId = ref<number | 'all'>('all')
  const selectedTypeFilter = ref<string>('all')
  const selectedStatusFilter = ref<string>('all')

  // Simulator results
  const quoteResult = ref<PriceQuoteResponse | null>(null)
  const quoteError = ref('')

  // Filtered rules
  const filteredRules = computed(() => {
    return rules.value.filter((rule) => {
      // Venue filter
      if (selectedVenueId.value !== 'all') {
        if (rule.venue_id && rule.venue_id !== selectedVenueId.value) {
          return false
        }
      }
      // Court filter
      if (selectedCourtId.value !== 'all') {
        if (rule.court_id && rule.court_id !== selectedCourtId.value) {
          return false
        }
      }
      // Type filter
      if (selectedTypeFilter.value !== 'all') {
        if (rule.type !== selectedTypeFilter.value) {
          return false
        }
      }
      // Status filter
      if (selectedStatusFilter.value === 'active' && !rule.is_active) {
        return false
      }
      if (selectedStatusFilter.value === 'inactive' && rule.is_active) {
        return false
      }
      // Search query
      if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim()
        const ruleName = (rule.name || '').toLowerCase()
        const typeName = (rule.type || '').toLowerCase()
        const venueName = (rule.venue?.name || '').toLowerCase()
        const courtName = (rule.court?.name || '').toLowerCase()
        const priceStr = String(rule.price)
        if (
          !ruleName.includes(q) &&
          !typeName.includes(q) &&
          !venueName.includes(q) &&
          !courtName.includes(q) &&
          !priceStr.includes(q)
        ) {
          return false
        }
      }
      return true
    })
  })

  // KPIs
  const totalRulesCount = computed(() => rules.value.length)
  const activeRulesCount = computed(() => rules.value.filter((r) => r.is_active).length)
  const peakRulesCount = computed(
    () =>
      rules.value.filter((r) => r.is_active && (r.type === 'peak' || r.type === 'surge')).length,
  )
  const holidayRulesCount = computed(
    () =>
      rules.value.filter(
        (r) =>
          r.is_active &&
          (r.type === 'holiday' || r.type === 'date_exception' || r.type === 'special_event'),
      ).length,
  )
  const averagePrice = computed(() => {
    const active = rules.value.filter((r) => r.is_active && r.price > 0)
    if (active.length === 0) return 0
    const sum = active.reduce((acc, curr) => acc + curr.price, 0)
    return Math.round(sum / active.length)
  })

  // Filtered courts based on selected venue
  const availableCourts = computed(() => {
    if (selectedVenueId.value === 'all') {
      return courts.value
    }
    return courts.value.filter((c) => (c.venue_id || c.venue?.id) === selectedVenueId.value)
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [rulesRes, venuesRes, courtsRes, zonesRes] = await Promise.allSettled([
        adminApi.getPriceRules<{ rules: PriceRuleItem[] }>(),
        adminApi.getVenues(),
        adminApi.getCourts(),
        adminApi.getSportZones(),
      ])

      if (rulesRes.status === 'fulfilled' && rulesRes.value?.rules) {
        rules.value = rulesRes.value.rules
      } else if (rulesRes.status === 'rejected') {
        // Fallback to pricing calendar if price-rules route differs
        const calRes = await adminApi
          .getPricingCalendar<{ rules: PriceRuleItem[] }>()
          .catch(() => null)
        if (calRes?.rules) {
          rules.value = calRes.rules
        }
      }

      if (venuesRes.status === 'rejected') {
        error.value = errorMessage(venuesRes.reason, 'Không thể tải danh sách cơ sở.')
      } else if (venuesRes.status === 'fulfilled' && Array.isArray(venuesRes.value)) {
        venues.value = venuesRes.value
      }

      if (courtsRes.status === 'fulfilled' && Array.isArray(courtsRes.value)) {
        courts.value = courtsRes.value
      }

      if (zonesRes.status === 'fulfilled' && Array.isArray(zonesRes.value)) {
        sportZones.value = zonesRes.value
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải dữ liệu ma trận giá.')
    } finally {
      loading.value = false
    }
  }

  async function saveRule(payload: PriceRuleFormPayload) {
    saving.value = true
    error.value = ''
    try {
      if (editingRule.value) {
        await adminApi.updatePriceRule(editingRule.value.id, payload)
        showNotice(`Đã cập nhật quy tắc giá "${payload.name || payload.type}"`)
      } else {
        await adminApi.createPriceRule(payload)
        showNotice(`Đã tạo mới quy tắc giá "${payload.name || payload.type}"`)
      }
      showRuleModal.value = false
      editingRule.value = null
      await loadData()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể lưu quy tắc giá.')
      throw e
    } finally {
      saving.value = false
    }
  }

  async function toggleRuleActive(rule: PriceRuleItem) {
    const updatedStatus = !rule.is_active
    rule.is_active = updatedStatus
    try {
      await adminApi.updatePriceRule(rule.id, { is_active: updatedStatus })
      showNotice(
        updatedStatus
          ? `Đã kích hoạt quy tắc #${rule.priority}`
          : `Đã tạm dừng quy tắc #${rule.priority}`,
      )
    } catch (e: unknown) {
      rule.is_active = !updatedStatus // revert
      error.value = errorMessage(e, 'Không thể thay đổi trạng thái quy tắc.')
    }
  }

  async function deleteRule(id: number) {
    try {
      await adminApi.deletePriceRule(id)
      showNotice('Đã xóa/vô hiệu hóa quy tắc giá.')
      await loadData()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể xóa quy tắc giá.')
    }
  }

  async function simulateQuote(
    courtId: number,
    dateStr: string,
    startTime: string,
    durationHours: number,
  ) {
    simulating.value = true
    quoteError.value = ''
    quoteResult.value = null

    try {
      // Build slots array in ISO string format (every 60 min or 30 min)
      const slots: string[] = []
      const [hStr, mStr] = startTime.split(':')
      const startHour = parseInt(hStr || '18', 10)
      const startMinute = parseInt(mStr || '0', 10)

      for (let i = 0; i < durationHours; i++) {
        const slotDate = new Date(
          `${dateStr}T${String(startHour + i).padStart(2, '0')}:${String(startMinute).padStart(2, '0')}:00`,
        )
        slots.push(slotDate.toISOString())
      }

      const res = await adminApi.quotePrice<{ quote: PriceQuoteResponse }>({
        court_id: courtId,
        slots,
      })

      if (res && res.quote) {
        quoteResult.value = res.quote
      }
    } catch (e: unknown) {
      quoteError.value = errorMessage(e, 'Không thể tính toán báo giá cho khung giờ này.')
    } finally {
      simulating.value = false
    }
  }

  async function applyPresetRules(
    presetId: string,
    targetVenueId?: number | null,
    targetCourtId?: number | null,
  ) {
    applyingPreset.value = true
    error.value = ''
    try {
      const selectedCourt = courts.value.find((c) => c.id === targetCourtId)
      const baseCourtPrice = 120000

      const rulePresets: Record<string, PriceRuleFormPayload[]> = {
        urban_peak: [
          {
            name: 'Giờ Vàng Buổi Tối (Peak 17:00 - 21:00)',
            type: 'peak',
            venue_id: targetVenueId || null,
            court_id: targetCourtId || null,
            starts_at: '17:00',
            ends_at: '21:00',
            price: Math.round(baseCourtPrice * 1.5),
            priority: 3,
            is_active: true,
          },
          {
            name: 'Khung Giờ Sáng Sớm (06:00 - 11:00)',
            type: 'weekly',
            venue_id: targetVenueId || null,
            court_id: targetCourtId || null,
            starts_at: '06:00',
            ends_at: '11:00',
            price: baseCourtPrice,
            priority: 2,
            is_active: true,
          },
          {
            name: 'Phụ Thu Cuối Tuần (T7 & CN)',
            type: 'weekly',
            weekday: 7,
            venue_id: targetVenueId || null,
            court_id: targetCourtId || null,
            price: Math.round(baseCourtPrice * 1.25),
            priority: 2,
            is_active: true,
          },
        ],
        student_discount: [
          {
            name: 'Ưu Đãi Giờ Trưa Học Sinh / Sinh Viên (11:00 - 14:00)',
            type: 'surge',
            venue_id: targetVenueId || null,
            court_id: targetCourtId || null,
            starts_at: '11:00',
            ends_at: '14:00',
            price: Math.round(baseCourtPrice * 0.75),
            priority: 4,
            is_active: true,
          },
        ],
        holiday_surcharge: [
          {
            name: 'Phụ Thu Ngày Lễ & Tết Toàn Hệ Thống (+35%)',
            type: 'holiday',
            venue_id: targetVenueId || null,
            court_id: targetCourtId || null,
            price: Math.round(baseCourtPrice * 1.35),
            priority: 5,
            is_active: true,
          },
        ],
        night_owl: [
          {
            name: 'Khung Giờ Đêm Tiết Kiệm (21:00 - 23:00)',
            type: 'peak',
            venue_id: targetVenueId || null,
            court_id: targetCourtId || null,
            starts_at: '21:00',
            ends_at: '23:00',
            price: Math.round(baseCourtPrice * 0.85),
            priority: 2,
            is_active: true,
          },
        ],
      }

      const selectedRules = rulePresets[presetId] || []
      for (const rulePayload of selectedRules) {
        await adminApi.createPriceRule(rulePayload)
      }

      showNotice(`Đã áp dụng thành công bộ quy tắc mẫu (${selectedRules.length} quy tắc)!`)
      showPresetsModal.value = false
      await loadData()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể áp dụng bộ quy tắc giá.')
    } finally {
      applyingPreset.value = false
    }
  }

  return {
    loading,
    saving,
    simulating,
    applyingPreset,
    notice,
    error,
    activeTab,
    showSimulatorModal,
    showRuleModal,
    showPresetsModal,
    editingRule,
    rules,
    venues,
    sportZones,
    courts,
    searchQuery,
    selectedVenueId,
    selectedCourtId,
    selectedTypeFilter,
    selectedStatusFilter,
    quoteResult,
    quoteError,
    filteredRules,
    totalRulesCount,
    activeRulesCount,
    peakRulesCount,
    holidayRulesCount,
    averagePrice,
    availableCourts,
    loadData,
    saveRule,
    toggleRuleActive,
    deleteRule,
    simulateQuote,
    applyPresetRules,
    showNotice,
  }
}
