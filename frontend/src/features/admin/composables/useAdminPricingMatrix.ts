import { ref, computed } from 'vue'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, PriceSlot } from '@/features/court/types/court'

export function useAdminPricingMatrix() {
  const selectedCourtForPrice = ref<Court | null>(null)
  const selectedWeekdayTab = ref<number>(1)
  const bulkScope = ref<'current' | 'weekdays' | 'weekends' | 'all'>('current')
  const bulkStartHour = ref<number>(6)
  const bulkEndHour = ref<number>(23)
  const bulkPrice = ref<number>(100000)
  const inlineEditingSlotId = ref<number | null>(null)
  const inlineEditingPrice = ref<number>(100000)
  const bulkSaving = ref<boolean>(false)
  const priceError = ref<string>('')
  const priceNotice = ref<string>('')

  const weekdayList = [
    { id: 1, label: 'Thứ 2', short: 'T2' },
    { id: 2, label: 'Thứ 3', short: 'T3' },
    { id: 3, label: 'Thứ 4', short: 'T4' },
    { id: 4, label: 'Thứ 5', short: 'T5' },
    { id: 5, label: 'Thứ 6', short: 'T6' },
    { id: 6, label: 'Thứ 7', short: 'T7', isWeekend: true },
    { id: 7, label: 'Chủ nhật', short: 'CN', isWeekend: true },
  ]

  const modalPriceSlots = computed(() => {
    if (!selectedCourtForPrice.value?.price_slots) return []
    return [...selectedCourtForPrice.value.price_slots].sort((a, b) => {
      if (a.weekday !== b.weekday) return a.weekday - b.weekday
      return a.starts_at.localeCompare(b.starts_at)
    })
  })

  const activeWeekdaySlots = computed(() => {
    const all = modalPriceSlots.value
    if (selectedWeekdayTab.value === 0) return all
    return all.filter((s) => s.weekday === selectedWeekdayTab.value)
  })

  const modalPricingStats = computed(() => {
    const slots = activeWeekdaySlots.value
    if (!slots.length) return { count: 0, min: 0, max: 0, avg: 0 }
    const prices = slots.map((s) => s.price)
    const min = Math.min(...prices)
    const max = Math.max(...prices)
    const avg = Math.round(prices.reduce((a, b) => a + b, 0) / prices.length)
    return { count: slots.length, min, max, avg }
  })

  const morningSlots = computed(() =>
    activeWeekdaySlots.value.filter((s) => {
      const h = parseInt(s.starts_at.slice(0, 2), 10)
      return h >= 6 && h < 12
    })
  )

  const afternoonSlots = computed(() =>
    activeWeekdaySlots.value.filter((s) => {
      const h = parseInt(s.starts_at.slice(0, 2), 10)
      return h >= 12 && h < 17
    })
  )

  const peakSlots = computed(() =>
    activeWeekdaySlots.value.filter((s) => {
      const h = parseInt(s.starts_at.slice(0, 2), 10)
      return h >= 17 && h < 22
    })
  )

  const nightSlots = computed(() =>
    activeWeekdaySlots.value.filter((s) => {
      const h = parseInt(s.starts_at.slice(0, 2), 10)
      return h >= 22 || h < 6
    })
  )

  function openPriceModal(court: Court) {
    selectedCourtForPrice.value = court
    selectedWeekdayTab.value = 1
    bulkPrice.value = 100000
    bulkStartHour.value = 6
    bulkEndHour.value = 23
    bulkScope.value = 'current'
    inlineEditingSlotId.value = null
    priceError.value = ''
    priceNotice.value = ''
  }

  async function applyBulkPricing(isDemo = false, onReload?: () => Promise<void>) {
    if (!selectedCourtForPrice.value) return
    bulkSaving.value = true
    priceError.value = ''
    priceNotice.value = ''
    try {
      const court = selectedCourtForPrice.value
      let targetDays: number[] = []
      if (bulkScope.value === 'current') {
        targetDays = selectedWeekdayTab.value === 0 ? [1, 2, 3, 4, 5, 6, 7] : [selectedWeekdayTab.value]
      } else if (bulkScope.value === 'weekdays') {
        targetDays = [1, 2, 3, 4, 5]
      } else if (bulkScope.value === 'weekends') {
        targetDays = [6, 7]
      } else {
        targetDays = [1, 2, 3, 4, 5, 6, 7]
      }

      const generatedSlots: Array<{ weekday: number; starts_at: string; ends_at: string; price: number }> = []
      for (const day of targetDays) {
        for (let h = bulkStartHour.value; h < bulkEndHour.value; h++) {
          generatedSlots.push({
            weekday: day,
            starts_at: `${String(h).padStart(2, '0')}:00`,
            ends_at: `${String(h + 1).padStart(2, '0')}:00`,
            price: bulkPrice.value,
          })
        }
      }

      if (isDemo) {
        let currentSlots = court.price_slots ? [...court.price_slots] : []
        for (const gen of generatedSlots) {
          const idx = currentSlots.findIndex(
            (s) => s.weekday === gen.weekday && s.starts_at === gen.starts_at && s.ends_at === gen.ends_at
          )
          if (idx >= 0 && currentSlots[idx]) {
            currentSlots[idx].price = gen.price
          } else {
            currentSlots.push({
              id: -Date.now() - Math.floor(Math.random() * 10000),
              court_id: court.id,
              weekday: gen.weekday,
              starts_at: gen.starts_at,
              ends_at: gen.ends_at,
              price: gen.price,
            })
          }
        }
        court.price_slots = currentSlots
        priceNotice.value = `Đã mô phỏng cập nhật bảng giá (${generatedSlots.length} khung giờ).`
        return
      }

      for (const slot of generatedSlots) {
        await adminApi.createPrice(court.id, slot)
      }
      priceNotice.value = `Đã thiết lập giá hàng loạt (${generatedSlots.length} khung giờ) thành công.`
      if (onReload) await onReload()
    } catch (cause: unknown) {
      priceError.value = errorMessage(cause, 'Không thể áp dụng bảng giá hàng loạt.')
    } finally {
      bulkSaving.value = false
    }
  }

  async function deletePriceSlot(slotId: number, isDemo = false, onReload?: () => Promise<void>) {
    if (!selectedCourtForPrice.value) return
    try {
      if (isDemo) {
        selectedCourtForPrice.value.price_slots = selectedCourtForPrice.value.price_slots?.filter((s) => s.id !== slotId)
        priceNotice.value = 'Đã mô phỏng xoá khung giá.'
        return
      }
      await adminApi.deletePrice(selectedCourtForPrice.value.id, slotId)
      priceNotice.value = 'Đã xoá khung giá.'
      if (onReload) await onReload()
    } catch (cause: unknown) {
      priceError.value = errorMessage(cause, 'Không thể xoá khung giá.')
    }
  }

  return {
    selectedCourtForPrice,
    selectedWeekdayTab,
    bulkScope,
    bulkStartHour,
    bulkEndHour,
    bulkPrice,
    inlineEditingSlotId,
    inlineEditingPrice,
    bulkSaving,
    priceError,
    priceNotice,
    weekdayList,
    modalPriceSlots,
    activeWeekdaySlots,
    modalPricingStats,
    morningSlots,
    afternoonSlots,
    peakSlots,
    nightSlots,
    openPriceModal,
    applyBulkPricing,
    deletePriceSlot,
  }
}
