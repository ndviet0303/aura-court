import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, PriceSlot } from '@/features/court/types/court'

export function useAdminPricingMatrix() {
  const { t } = useI18n()

  const selectedCourtForPrice = ref<Court | null>(null)
  const showPriceModal = ref(false)
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

  const weekdayList = computed(() => [
    { id: 1, label: t('admin.manage.dayMon'), short: 'T2' },
    { id: 2, label: t('admin.manage.dayTue'), short: 'T3' },
    { id: 3, label: t('admin.manage.dayWed'), short: 'T4' },
    { id: 4, label: t('admin.manage.dayThu'), short: 'T5' },
    { id: 5, label: t('admin.manage.dayFri'), short: 'T6' },
    { id: 6, label: t('admin.manage.daySat'), short: 'T7', isWeekend: true },
    { id: 7, label: t('admin.manage.daySun'), short: 'CN', isWeekend: true },
  ])

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
    }),
  )

  const afternoonSlots = computed(() =>
    activeWeekdaySlots.value.filter((s) => {
      const h = parseInt(s.starts_at.slice(0, 2), 10)
      return h >= 12 && h < 17
    }),
  )

  const peakSlots = computed(() =>
    activeWeekdaySlots.value.filter((s) => {
      const h = parseInt(s.starts_at.slice(0, 2), 10)
      return h >= 17 && h < 22
    }),
  )

  const nightSlots = computed(() =>
    activeWeekdaySlots.value.filter((s) => {
      const h = parseInt(s.starts_at.slice(0, 2), 10)
      return h >= 22 || h < 6
    }),
  )

  async function openPriceModal(court: Court) {
    selectedCourtForPrice.value = court
    selectedWeekdayTab.value = 1
    bulkPrice.value = 100000
    bulkStartHour.value = 6
    bulkEndHour.value = 23
    bulkScope.value = 'current'
    inlineEditingSlotId.value = null
    priceError.value = ''
    priceNotice.value = ''
    showPriceModal.value = true

    if (!court.price_slots || court.price_slots.length === 0) {
      try {
        const slots = await adminApi.getCourtPrices(court.id)
        court.price_slots = slots || []
        court.price_slots_count = court.price_slots.length
      } catch (cause: unknown) {
        priceError.value = errorMessage(cause, t('admin.manage.loadDataError'))
      }
    }
  }

  async function applyBulkPricing(onReload?: () => Promise<void>) {
    if (!selectedCourtForPrice.value) return
    bulkSaving.value = true
    priceError.value = ''
    priceNotice.value = ''
    try {
      const court = selectedCourtForPrice.value
      let targetDays: number[] = []
      if (bulkScope.value === 'current') {
        targetDays =
          selectedWeekdayTab.value === 0 ? [1, 2, 3, 4, 5, 6, 7] : [selectedWeekdayTab.value]
      } else if (bulkScope.value === 'weekdays') {
        targetDays = [1, 2, 3, 4, 5]
      } else if (bulkScope.value === 'weekends') {
        targetDays = [6, 7]
      } else {
        targetDays = [1, 2, 3, 4, 5, 6, 7]
      }

      const res = await adminApi.bulkCreatePrices<{ message: string; slots?: PriceSlot[] }>(
        court.id,
        {
          weekdays: targetDays,
          start_hour: bulkStartHour.value,
          end_hour: bulkEndHour.value,
          price: bulkPrice.value,
        },
      )

      const count = targetDays.length * (bulkEndHour.value - bulkStartHour.value)
      priceNotice.value = res?.message || t('admin.manage.bulkPricingSuccess', { count })
      try {
        const freshSlots = await adminApi.getCourtPrices(court.id)
        court.price_slots = freshSlots || []
        court.price_slots_count = court.price_slots.length
      } catch {
        // ignore background refresh error
      }
      if (onReload) await onReload()
    } catch (cause: unknown) {
      priceError.value = errorMessage(cause, t('admin.manage.bulkPricingError'))
    } finally {
      bulkSaving.value = false
    }
  }

  async function deletePriceSlot(slotId: number, onReload?: () => Promise<void>) {
    if (!selectedCourtForPrice.value) return
    try {
      await adminApi.deletePrice(selectedCourtForPrice.value.id, slotId)
      priceNotice.value = t('admin.manage.deleteSlotSuccess')
      if (selectedCourtForPrice.value.price_slots) {
        selectedCourtForPrice.value.price_slots = selectedCourtForPrice.value.price_slots.filter(
          (s) => s.id !== slotId,
        )
        selectedCourtForPrice.value.price_slots_count =
          selectedCourtForPrice.value.price_slots.length
      }
      if (onReload) await onReload()
    } catch (cause: unknown) {
      priceError.value = errorMessage(cause, t('admin.manage.deleteSlotError'))
    }
  }

  return {
    selectedCourtForPrice,
    showPriceModal,
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
