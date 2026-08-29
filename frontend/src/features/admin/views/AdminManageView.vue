<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, CourtBlock, PriceSlot, SportZone, Venue } from '@/features/court/types/court'
import type { Refund } from '@/features/booking/types/booking'
import {
  TimePicker,
  DateTimePicker,
  SelectDropdown,
  AuraButton,
  SkeletonLoader,
} from '@/shared/components'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency, formatDateTime } from '@/i18n'

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email?.endsWith('@demo.auracourt.vn'))

// Types
type CourtForm = { name: string; venue_id: number; sport_zone_id: number | null; description: string; image_url: string; is_active: boolean }
type PriceForm = { weekday: number; starts_at: string; ends_at: string; price: number }
type BlockForm = { starts_at: string; ends_at: string; reason: string }
type Coupon = { id: number; code: string; discount_type: 'fixed' | 'percent'; discount_value: number; usage_count: number; usage_limit?: number | null; is_active: boolean }

// State
const courts = ref<Court[]>([])
const venues = ref<Venue[]>([])
const sportZones = ref<SportZone[]>([])
const refunds = ref<Refund[]>([])
const coupons = ref<Coupon[]>([])
const error = ref('')
const notice = ref('')
const saving = ref(false)
const loading = ref(false)

// Filter & Search
const searchQuery = ref('')
const selectedVenueFilter = ref<number | 'all'>('all')
const statusFilter = ref<'all' | 'active' | 'inactive'>('all')

// Modals State
const showCourtModal = ref(false)
const editingCourt = ref<number | null>(null)
const courtForm = ref<CourtForm>({ name: '', venue_id: 0, sport_zone_id: null, description: '', image_url: '', is_active: true })

const showPriceModal = ref(false)
const selectedCourtForPrice = ref<Court | null>(null)
const priceForm = ref<PriceForm>({ weekday: 1, starts_at: '06:00', ends_at: '22:00', price: 120000 })

const showBlockModal = ref(false)
const selectedCourtForBlock = ref<Court | null>(null)
const blockForm = ref<BlockForm>({ starts_at: '', ends_at: '', reason: 'Bảo dưỡng định kỳ' })

const showCouponModal = ref(false)
const couponForm = ref({ code: '', discount_type: 'percent' as 'fixed' | 'percent', discount_value: 10, usage_limit: 20 })

const activeVenues = computed(() => venues.value.filter((venue) => venue.is_active))

const venueDropdownOptions = computed(() =>
  activeVenues.value.map((venue) => ({
    value: venue.id,
    label: venue.name,
    sublabel: venue.address || undefined,
    icon: 'location_on',
  }))
)

const sportZoneDropdownOptions = computed(() => {
  const filtered = sportZones.value.filter(z => z.venue_id === courtForm.value.venue_id && z.is_active)
  return [
    { value: 0, label: 'Mặc định (Khu Cầu Lông)', icon: 'sports_tennis' },
    ...filtered.map(z => ({
      value: z.id,
      label: `${z.name} (${z.slot_duration}p)`,
      icon: 'category',
    }))
  ]
})


const venueFilterOptions = computed(() => [
  { value: 'all', label: `Tất cả địa điểm (${venues.value.length})`, icon: 'domain' },
  ...venues.value.map((v) => ({
    value: v.id,
    label: v.name,
    sublabel: v.address || undefined,
    icon: 'location_on',
  })),
])

const statusFilterOptions = [
  { value: 'all', label: 'Tất cả trạng thái', icon: 'filter_list' },
  { value: 'active', label: 'Đang mở cửa', icon: 'check_circle' },
  { value: 'inactive', label: 'Đã tạm ngừng', icon: 'pause_circle' },
]

const bulkScopeOptions = computed(() => [
  {
    value: 'current',
    label: selectedWeekdayTab.value === 0 ? 'Tất cả 7 ngày trong tuần' : `Chỉ áp dụng cho Thứ ${selectedWeekdayTab.value}`,
    icon: 'today',
  },
  { value: 'weekdays', label: 'Thứ 2 đến Thứ 6 (Ngày trong tuần)', icon: 'date_range' },
  { value: 'weekends', label: 'Thứ 7 & Chủ Nhật (Cuối tuần)', icon: 'event' },
  { value: 'all', label: 'Tất cả các ngày (Cả tuần)', icon: 'calendar_month' },
])

const bulkStartHourOptions = Array.from({ length: 18 }, (_, i) => ({
  value: i + 5,
  label: `${String(i + 5).padStart(2, '0')}:00`,
}))

const bulkEndHourOptions = computed(() =>
  Array.from({ length: 18 }, (_, i) => ({
    value: i + 6,
    label: `${String(i + 6).padStart(2, '0')}:00`,
  })).filter((opt) => opt.value > bulkStartHour.value)
)

const discountTypeOptions = [
  { value: 'percent', label: 'Phần trăm (%)', icon: 'percent' },
  { value: 'fixed', label: 'Số tiền cố định (₫)', icon: 'payments' },
]

const selectedVenueForCourt = computed(() =>
  activeVenues.value.find((v) => v.id === courtForm.value.venue_id)
)

const courtImagePresets = [
  {
    name: 'Yonex BWF',
    sub: 'Thảm chuẩn 5 lớp',
    url: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=800&q=80',
  },
  {
    name: 'Thảm Xanh',
    sub: 'Sân đôi phong trào',
    url: 'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?auto=format&fit=crop&w=800&q=80',
  },
  {
    name: 'Indoor VIP',
    sub: 'Nhà thi đấu kín',
    url: 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=800&q=80',
  },
  {
    name: 'Đèn LED Pro',
    sub: 'Chiếu sáng 500 Lux',
    url: 'https://images.unsplash.com/photo-1613918108466-292b78a8ef95?auto=format&fit=crop&w=800&q=80',
  },
]

const courtDescriptionTags = [
  { label: 'Thảm BWF 5 lớp tiêu chuẩn', icon: 'layers' },
  { label: 'Đèn LED chống chói 500 Lux', icon: 'light_mode' },
  { label: 'Điều hòa không khí trung tâm', icon: 'ac_unit' },
  { label: 'Ghế nghỉ trọng tài & VĐV', icon: 'chair' },
  { label: 'Bảng điểm điện tử LED', icon: 'scoreboard' },
  { label: 'Thảm giảm chấn bảo vệ khớp gối', icon: 'fitness_center' },
]

function toggleDescriptionTag(tagLabel: string) {
  if (!courtForm.value.description) {
    courtForm.value.description = tagLabel
    return
  }
  if (courtForm.value.description.includes(tagLabel)) {
    courtForm.value.description = courtForm.value.description
      .replace(new RegExp(`(^|,\\s*)` + tagLabel.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&')), '')
      .replace(/^,\s*/, '')
      .trim()
  } else {
    courtForm.value.description = `${courtForm.value.description}, ${tagLabel}`
  }
}

// R2 Upload State & Handlers
const uploadingImage = ref(false)
const uploadError = ref('')
const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

function triggerFileInput() {
  fileInput.value?.click()
}

async function handleFileUpload(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  uploadError.value = ''
  uploadingImage.value = true

  if (isDemoAdmin.value) {
    setTimeout(() => {
      courtForm.value.image_url = URL.createObjectURL(file)
      uploadingImage.value = false
    }, 600)
    return
  }

  try {
    const formData = new FormData()
    formData.append('image', file)
    const res = await adminApi.uploadImage(formData)
    courtForm.value.image_url = res.url
  } catch (err: unknown) {
    uploadError.value = errorMessage(err, 'Không thể tải ảnh lên Cloudflare R2.')
  } finally {
    uploadingImage.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

function onDragOver(e: DragEvent) {
  e.preventDefault()
  isDragging.value = true
}

function onDragLeave(e: DragEvent) {
  e.preventDefault()
  isDragging.value = false
}

function onDrop(e: DragEvent) {
  e.preventDefault()
  isDragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (!file) return
  const fakeEvent = { target: { files: [file] } } as unknown as Event
  handleFileUpload(fakeEvent)
}

// Filtered Courts
const filteredCourts = computed(() => {
  return courts.value.filter((c) => {
    const matchVenue = selectedVenueFilter.value === 'all' || c.venue?.id === selectedVenueFilter.value
    const matchStatus = statusFilter.value === 'all' || (statusFilter.value === 'active' ? c.is_active !== false : c.is_active === false)
    const matchSearch = !searchQuery.value.trim() || c.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || (c.venue?.name || '').toLowerCase().includes(searchQuery.value.toLowerCase())
    return matchVenue && matchStatus && matchSearch
  })
})

// Stats
const totalCourtsCount = computed(() => courts.value.length)
const activeCourtsCount = computed(() => courts.value.filter((c) => c.is_active !== false).length)
const totalBlocksCount = computed(() => courts.value.reduce((sum, c) => sum + (c.blocks?.length || 0), 0))
const pendingRefundsCount = computed(() => refunds.value.length)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [loadedCourts, loadedVenues, loadedZones, loadedRefunds, loadedCoupons] = await Promise.all([
      adminApi.getCourts(),
      adminApi.getVenues(),
      adminApi.getSportZones(),
      adminApi.getRefunds<{ data: Refund[] }>(),
      adminApi.getCoupons<{ data: Coupon[] }>(),
    ])
    courts.value = loadedCourts
    venues.value = loadedVenues
    sportZones.value = loadedZones || []
    refunds.value = loadedRefunds.data || []
    coupons.value = loadedCoupons.data || []
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không tải được dữ liệu quản trị.')
  } finally {
    loading.value = false
  }
}

// Court CRUD Modal
function startCreateCourt() {
  editingCourt.value = null
  courtForm.value = {
    name: '',
    venue_id: activeVenues.value[0]?.id || 0,
    sport_zone_id: 0,
    description: '',
    image_url: '',
    is_active: true,
  }
  showCourtModal.value = true
}

function startEditCourt(court: Court) {
  editingCourt.value = court.id
  courtForm.value = {
    name: court.name,
    venue_id: court.venue?.id || activeVenues.value[0]?.id || 0,
    sport_zone_id: court.sport_zone_id || 0,
    description: court.description || '',
    image_url: court.image_url || '',
    is_active: court.is_active !== false,
  }
  showCourtModal.value = true
}

async function saveCourt() {
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    const payload = {
      ...courtForm.value,
      sport_zone_id: courtForm.value.sport_zone_id ? courtForm.value.sport_zone_id : null,
      image_url: courtForm.value.image_url || null,
    }
    if (isDemoAdmin.value) {
      if (editingCourt.value) {
        courts.value = courts.value.map((c) =>
          c.id === editingCourt.value
            ? {
                ...c,
                ...payload,
                venue: venues.value.find((v) => v.id === payload.venue_id) || c.venue,
                sport_zone: sportZones.value.find((z) => z.id === payload.sport_zone_id) || null,
                image_url: payload.image_url || undefined,
              }
            : c
        )
        notice.value = 'Đã mô phỏng cập nhật sân (không ghi vào dữ liệu thật).'
      } else {
        const syntheticId = -Date.now()
        const newCourt: Court = {
          id: syntheticId,
          name: payload.name,
          venue_id: payload.venue_id,
          sport_zone_id: payload.sport_zone_id,
          venue: venues.value.find((v) => v.id === payload.venue_id) || {
            id: payload.venue_id,
            name: 'Địa điểm đã chọn',
            slug: 'selected-venue',
            address: 'Địa chỉ mẫu',
            google_maps_url: 'https://maps.app.goo.gl/demo',
            is_active: true,
            share_url: `${window.location.origin}/venues/selected-venue/directions`,
          },
          sport_zone: sportZones.value.find((z) => z.id === payload.sport_zone_id) || null,
          description: payload.description || undefined,
          image_url: payload.image_url || undefined,
          is_active: payload.is_active,
          price_slots: [],
          blocks: [],
        }
        courts.value = [newCourt, ...courts.value]
        notice.value = 'Đã mô phỏng tạo sân mới (không ghi vào dữ liệu thật).'
      }
      showCourtModal.value = false
      return
    }

    if (editingCourt.value) {
      await adminApi.updateCourt(editingCourt.value, payload)
      notice.value = 'Đã cập nhật sân thành công.'
    } else {
      await adminApi.createCourt(payload)
      notice.value = 'Đã tạo sân mới thành công.'
    }
    showCourtModal.value = false
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể lưu thông tin sân.')
  } finally {
    saving.value = false
  }
}

async function toggleCourtStatus(court: Court) {
  const nextStatus = court.is_active === false
  if (isDemoAdmin.value) {
    court.is_active = nextStatus
    notice.value = nextStatus ? 'Đã mô phỏng mở lại sân.' : 'Đã mô phỏng ngừng mở sân.'
    return
  }
  try {
    if (nextStatus) {
      await adminApi.updateCourt(court.id, { is_active: true })
      notice.value = `Đã mở lại ${court.name}.`
    } else {
      await adminApi.deleteCourt(court.id)
      notice.value = `Đã ngừng mở ${court.name}.`
    }
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể thay đổi trạng thái sân.')
  }
}

// Price Slot Modal State & Methods
const selectedWeekdayTab = ref<number>(1)
const bulkScope = ref<'current' | 'weekdays' | 'weekends' | 'all'>('current')
const bulkStartHour = ref<number>(6)
const bulkEndHour = ref<number>(23)
const bulkPrice = ref<number>(100000)
const inlineEditingSlotId = ref<number | null>(null)
const inlineEditingPrice = ref<number>(100000)
const bulkSaving = ref<boolean>(false)

const formattedBulkPrice = computed({
  get: () => {
    if (bulkPrice.value == null || isNaN(bulkPrice.value) || bulkPrice.value === 0) return ''
    return Number(bulkPrice.value).toLocaleString('vi-VN')
  },
  set: (val: string) => {
    const numeric = parseInt(val.replace(/\D/g, ''), 10)
    bulkPrice.value = isNaN(numeric) ? 0 : numeric
  },
})

const formattedInlinePrice = computed({
  get: () => {
    if (inlineEditingPrice.value == null || isNaN(inlineEditingPrice.value) || inlineEditingPrice.value === 0) return ''
    return Number(inlineEditingPrice.value).toLocaleString('vi-VN')
  },
  set: (val: string) => {
    const numeric = parseInt(val.replace(/\D/g, ''), 10)
    inlineEditingPrice.value = isNaN(numeric) ? 0 : numeric
  },
})

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

const morningSlots = computed(() => activeWeekdaySlots.value.filter((s) => {
  const h = parseInt(s.starts_at.slice(0, 2), 10)
  return h >= 6 && h < 12
}))

const afternoonSlots = computed(() => activeWeekdaySlots.value.filter((s) => {
  const h = parseInt(s.starts_at.slice(0, 2), 10)
  return h >= 12 && h < 17
}))

const peakSlots = computed(() => activeWeekdaySlots.value.filter((s) => {
  const h = parseInt(s.starts_at.slice(0, 2), 10)
  return h >= 17 && h < 22
}))

const nightSlots = computed(() => activeWeekdaySlots.value.filter((s) => {
  const h = parseInt(s.starts_at.slice(0, 2), 10)
  return h >= 22 || h < 6
}))

function openPriceModal(court: Court) {
  selectedCourtForPrice.value = court
  selectedWeekdayTab.value = 1
  priceForm.value = { weekday: 1, starts_at: '06:00', ends_at: '07:00', price: 100000 }
  bulkPrice.value = 100000
  bulkStartHour.value = 6
  bulkEndHour.value = 23
  bulkScope.value = 'current'
  inlineEditingSlotId.value = null
  showPriceModal.value = true
}

async function applyBulkPricing() {
  if (!selectedCourtForPrice.value) return
  bulkSaving.value = true
  error.value = ''
  notice.value = ''
  try {
    const court = selectedCourtForPrice.value
    let targetWeekdays: number[] = []
    if (bulkScope.value === 'current') {
      targetWeekdays = selectedWeekdayTab.value === 0 ? [1, 2, 3, 4, 5, 6, 7] : [selectedWeekdayTab.value]
    } else if (bulkScope.value === 'weekdays') {
      targetWeekdays = [1, 2, 3, 4, 5]
    } else if (bulkScope.value === 'weekends') {
      targetWeekdays = [6, 7]
    } else {
      targetWeekdays = [1, 2, 3, 4, 5, 6, 7]
    }

    if (isDemoAdmin.value) {
      const prices = [...(court.price_slots || [])]
      for (const w of targetWeekdays) {
        for (let h = bulkStartHour.value; h < bulkEndHour.value; h++) {
          const startsAt = `${String(h).padStart(2, '0')}:00`
          const endsAt = `${String(h + 1).padStart(2, '0')}:00`
          const idx = prices.findIndex((p) => p.weekday === w && p.starts_at.slice(0, 5) === startsAt)
          const item = {
            id: idx >= 0 ? prices[idx]!.id : -Math.floor(Date.now() + Math.random() * 1000),
            court_id: court.id,
            weekday: w,
            starts_at: startsAt,
            ends_at: endsAt,
            price: bulkPrice.value,
            is_active: true,
          }
          if (idx >= 0) prices[idx] = item
          else prices.push(item)
        }
      }
      court.price_slots = prices
      notice.value = `Đã mô phỏng áp dụng mức giá ${formatCurrency(bulkPrice.value)}/giờ.`
      return
    }

    const res = await adminApi.bulkCreatePrices(court.id, {
      weekdays: targetWeekdays,
      start_hour: bulkStartHour.value,
      end_hour: bulkEndHour.value,
      price: bulkPrice.value,
    })
    notice.value = res.message || 'Đã cập nhật khung giá hàng loạt.'
    await load()
    selectedCourtForPrice.value = courts.value.find((c) => c.id === court.id) || court
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể cập nhật khung giá hàng loạt.')
  } finally {
    bulkSaving.value = false
  }
}

async function applyPreset(type: 'standard' | 'peak' | 'weekend') {
  if (!selectedCourtForPrice.value) return
  if (type === 'standard') {
    bulkScope.value = 'weekdays'
    bulkStartHour.value = 6
    bulkEndHour.value = 17
    bulkPrice.value = 100000
  } else if (type === 'peak') {
    bulkScope.value = 'weekdays'
    bulkStartHour.value = 17
    bulkEndHour.value = 22
    bulkPrice.value = 130000
  } else if (type === 'weekend') {
    bulkScope.value = 'weekends'
    bulkStartHour.value = 6
    bulkEndHour.value = 23
    bulkPrice.value = 140000
  }
  await applyBulkPricing()
}

function startInlineEdit(slot: PriceSlot) {
  inlineEditingSlotId.value = slot.id || null
  inlineEditingPrice.value = slot.price
}

function cancelInlineEdit() {
  inlineEditingSlotId.value = null
}

async function saveInlineEdit(slot: PriceSlot) {
  if (!selectedCourtForPrice.value) return
  saving.value = true
  error.value = ''
  try {
    const court = selectedCourtForPrice.value
    const startsAt = slot.starts_at.slice(0, 5)
    const endsAt = slot.ends_at.slice(0, 5)

    if (isDemoAdmin.value) {
      const prices = [...(court.price_slots || [])]
      const idx = prices.findIndex((p) => p.id === slot.id)
      if (idx >= 0) {
        prices[idx] = { ...prices[idx]!, price: inlineEditingPrice.value }
        court.price_slots = prices
      }
      inlineEditingSlotId.value = null
      notice.value = `Đã cập nhật giá khung ${startsAt} - ${endsAt}.`
      return
    }

    if (slot.id && slot.id > 0) {
      await adminApi.updatePrice(court.id, slot.id, { price: inlineEditingPrice.value })
    } else {
      await adminApi.createPrice(court.id, {
        weekday: slot.weekday,
        starts_at: startsAt,
        ends_at: endsAt,
        price: inlineEditingPrice.value,
      })
    }

    inlineEditingSlotId.value = null
    notice.value = `Đã cập nhật giá khung ${startsAt} - ${endsAt}.`
    await load()
    selectedCourtForPrice.value = courts.value.find((c) => c.id === court.id) || court
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể cập nhật giá.')
  } finally {
    saving.value = false
  }
}

async function quickAdjustPrice(slot: PriceSlot, delta: number) {
  inlineEditingPrice.value = Math.max(0, slot.price + delta)
  await saveInlineEdit(slot)
}

async function savePriceSlot() {
  if (!selectedCourtForPrice.value) return
  saving.value = true
  error.value = ''
  try {
    const court = selectedCourtForPrice.value
    if (isDemoAdmin.value) {
      const prices = [...(court.price_slots || [])]
      const index = prices.findIndex(
        (p) => p.weekday === priceForm.value.weekday && p.starts_at.slice(0, 5) === priceForm.value.starts_at
      )
      const next = { ...priceForm.value, id: index >= 0 ? prices[index]?.id : -Date.now(), court_id: court.id, is_active: true }
      if (index >= 0) prices[index] = next
      else prices.push(next)
      court.price_slots = prices
      notice.value = `Đã mô phỏng lưu giá cho ${court.name}.`
      return
    }

    await adminApi.createPrice(court.id, priceForm.value)
    notice.value = `Đã lưu khung giá cho ${court.name}.`
    await load()
    selectedCourtForPrice.value = courts.value.find((c) => c.id === court.id) || court
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể lưu khung giá.')
  } finally {
    saving.value = false
  }
}

async function deletePriceSlot(price: PriceSlot) {
  if (!selectedCourtForPrice.value || !price.id || !confirm(`Xóa khung giá ${price.starts_at.slice(0, 5)} - ${price.ends_at.slice(0, 5)}?`)) return
  const court = selectedCourtForPrice.value
  if (isDemoAdmin.value) {
    court.price_slots = (court.price_slots || []).filter((item) => item.id !== price.id)
    notice.value = 'Đã mô phỏng xóa khung giá.'
    return
  }
  try {
    await adminApi.deletePrice(court.id, price.id)
    notice.value = 'Đã xóa khung giá.'
    await load()
    selectedCourtForPrice.value = courts.value.find((c) => c.id === court.id) || court
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể xóa khung giá.')
  }
}

// Block Modal
function openBlockModal(court: Court) {
  selectedCourtForBlock.value = court
  const now = new Date()
  const startIso = new Date(now.getTime() + 3600000).toISOString().slice(0, 16)
  const endIso = new Date(now.getTime() + 7200000).toISOString().slice(0, 16)
  blockForm.value = { starts_at: startIso, ends_at: endIso, reason: 'Bảo dưỡng định kỳ' }
  showBlockModal.value = true
}

async function saveBlock() {
  if (!selectedCourtForBlock.value) return
  if (!blockForm.value.starts_at || !blockForm.value.ends_at) {
    error.value = 'Vui lòng nhập đầy đủ thời gian khóa sân.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const court = selectedCourtForBlock.value
    if (isDemoAdmin.value) {
      court.blocks = [...(court.blocks || []), { ...blockForm.value, id: -Date.now(), court_id: court.id }]
      notice.value = `Đã mô phỏng khóa lịch ${court.name}.`
      showBlockModal.value = false
      return
    }

    await adminApi.createBlock(court.id, blockForm.value)
    notice.value = `Đã khóa lịch ${court.name}.`
    showBlockModal.value = false
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể khóa lịch.')
  } finally {
    saving.value = false
  }
}

async function deleteBlock(court: Court, block: CourtBlock) {
  if (!confirm('Gỡ bỏ lịch khóa này và mở lại khung giờ cho khách?')) return
  if (isDemoAdmin.value) {
    court.blocks = (court.blocks || []).filter((item) => item.id !== block.id)
    notice.value = 'Đã mô phỏng mở khóa lịch.'
    return
  }
  try {
    await adminApi.deleteBlock(court.id, block.id)
    notice.value = 'Đã mở khóa lịch.'
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể mở khóa lịch.')
  }
}

// Refund Management
async function processRefund(refund: Refund, status: 'processed' | 'rejected') {
  if (isDemoAdmin.value) {
    refunds.value = refunds.value.filter((item) => item.id !== refund.id)
    notice.value = status === 'processed' ? 'Đã mô phỏng duyệt hoàn tiền.' : 'Đã mô phỏng từ chối hoàn tiền.'
    return
  }
  try {
    await adminApi.processRefund(refund.id, { status })
    notice.value = status === 'processed' ? 'Đã duyệt hoàn tiền thành công.' : 'Đã từ chối hoàn tiền.'
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể xử lý hoàn tiền.')
  }
}

// Coupon Modal
function openCreateCouponModal() {
  couponForm.value = { code: '', discount_type: 'percent', discount_value: 10, usage_limit: 20 }
  showCouponModal.value = true
}

async function createCoupon() {
  saving.value = true
  error.value = ''
  try {
    if (isDemoAdmin.value) {
      coupons.value.push({
        id: -Date.now(),
        code: couponForm.value.code.toUpperCase(),
        discount_type: couponForm.value.discount_type,
        discount_value: couponForm.value.discount_value,
        usage_limit: couponForm.value.usage_limit,
        usage_count: 0,
        is_active: true,
      })
      notice.value = 'Đã mô phỏng tạo mã giảm giá.'
      showCouponModal.value = false
      return
    }

    await adminApi.createCoupon({
      ...couponForm.value,
      code: couponForm.value.code.toUpperCase(),
    })
    notice.value = 'Đã tạo mã giảm giá thành công.'
    showCouponModal.value = false
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể tạo mã giảm giá.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await load()
  if (route.query.venue_id) {
    const vId = Number(route.query.venue_id)
    if (!Number.isNaN(vId)) {
      selectedVenueFilter.value = vId
    }
  }
})
</script>

<template>
  <div class="admin-manage-content-wrap">
    <!-- Header Section -->
    <header class="admin-manage-header">
      <div class="header-content">
        <div class="eyebrow-tag">
          <span class="material-symbols-outlined icon-16">sports_tennis</span>
          <span>VẬN HÀNH · MANAGEMENT</span>
        </div>
        <h1 class="page-title">Quản lý Sân đấu & Bảng giá</h1>
        <p class="muted max-w-2xl">
          Quản lý toàn bộ danh sách sân, thiết lập bảng giá theo khung giờ, lên lịch khóa bảo trì và duyệt yêu cầu hoàn tiền.
        </p>
      </div>

      <div class="header-actions">
        <AuraButton
          variant="primary"
          size="pill"
          icon="add"
          @click="startCreateCourt"
        >
          Thêm sân mới
        </AuraButton>
      </div>
    </header>

    <!-- Stats Summary Row (Loading Skeleton or Real Data) -->
    <section v-if="loading" class="stats-overview-grid">
      <div v-for="i in 4" :key="i" class="stat-card skeleton-stat-card">
        <SkeletonLoader type="avatar" />
        <div class="skeleton-stat-lines">
          <SkeletonLoader type="text" width="80px" height="12px" />
          <SkeletonLoader type="title" width="45px" height="24px" />
        </div>
      </div>
    </section>

    <section v-else class="stats-overview-grid">
      <div class="stat-card">
        <div class="stat-icon-wrap icon-primary">
          <span class="material-symbols-outlined">sports_tennis</span>
        </div>
        <div>
          <span class="stat-label">Tổng số sân</span>
          <h3 class="stat-number">{{ totalCourtsCount }}</h3>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap icon-success">
          <span class="material-symbols-outlined">check_circle</span>
        </div>
        <div>
          <span class="stat-label">Sân đang mở</span>
          <h3 class="stat-number">{{ activeCourtsCount }}</h3>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap icon-warning">
          <span class="material-symbols-outlined">build</span>
        </div>
        <div>
          <span class="stat-label">Đợt khóa bảo trì</span>
          <h3 class="stat-number">{{ totalBlocksCount }}</h3>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap icon-error">
          <span class="material-symbols-outlined">currency_exchange</span>
        </div>
        <div>
          <span class="stat-label">Yêu cầu hoàn tiền</span>
          <h3 class="stat-number">{{ pendingRefundsCount }}</h3>
        </div>
      </div>
    </section>

    <!-- Notifications & Alert Banners -->
    <div v-if="notice" class="notice-banner">
      <span class="material-symbols-outlined text-green">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="error-banner">
      <span class="material-symbols-outlined text-error">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="toolbar-filter-card">
      <div class="search-input-box">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          placeholder="Tìm theo tên sân hoặc địa điểm..."
        />
      </div>

      <div class="filter-controls-group">
        <div class="filter-select-wrapper">
          <SelectDropdown
            v-model="selectedVenueFilter"
            :options="venueFilterOptions"
            variant="bordered"
            aria-label="Lọc theo địa điểm"
          />
        </div>

        <div class="filter-select-wrapper">
          <SelectDropdown
            v-model="statusFilter"
            :options="statusFilterOptions"
            variant="bordered"
            aria-label="Lọc theo trạng thái"
          />
        </div>
      </div>
    </div>

    <!-- Loading Skeleton Grid -->
    <section v-if="loading" class="courts-cards-grid">
      <div v-for="i in 6" :key="i" class="court-manage-card skeleton-admin-card">
        <div class="skeleton-admin-media">
          <SkeletonLoader type="rect" height="160px" rounded="0" />
        </div>
        <div class="court-card-body">
          <SkeletonLoader type="title" width="75%" height="20px" />
          <SkeletonLoader type="text" width="90%" height="13px" />
          <div class="skeleton-badges-row">
            <SkeletonLoader type="rect" width="100px" height="24px" rounded="9999px" />
            <SkeletonLoader type="rect" width="90px" height="24px" rounded="9999px" />
          </div>
          <div class="skeleton-actions-row">
            <SkeletonLoader type="rect" height="34px" rounded="8px" />
          </div>
        </div>
      </div>
    </section>

    <!-- Courts Cards Grid -->
    <section v-else class="courts-cards-grid">
      <article
        v-for="court in filteredCourts"
        :key="court.id"
        class="court-manage-card"
        :class="{ 'is-inactive': court.is_active === false }"
      >
        <!-- Thumbnail & Badges -->
        <div class="court-card-media">
          <img
            :src="court.image_url || '/aura/court-1.jpg'"
            :alt="court.name"
            class="court-card-img"
          />
          <div class="media-overlay"></div>

          <!-- Active status toggle button -->
          <div class="status-toggle-corner">
            <button
              type="button"
              class="status-badge-btn"
              :class="court.is_active !== false ? 'badge-active' : 'badge-inactive'"
              @click="toggleCourtStatus(court)"
            >
              <span class="status-dot"></span>
              <span>{{ court.is_active !== false ? 'Đang mở' : 'Đã ngừng' }}</span>
            </button>
          </div>

          <div class="media-bottom-info">
            <span class="venue-name-chip">
              <span class="material-symbols-outlined icon-14">location_on</span>
              {{ court.venue?.name || 'Aura Club' }}
            </span>
          </div>
        </div>

        <!-- Court Body Details -->
        <div class="court-card-body">
          <h2 class="court-title-text">{{ court.name }}</h2>
          <p class="court-description-text">
            {{ court.description || 'Chưa có mô tả chi tiết cho sân đấu này.' }}
          </p>

          <!-- Price & Blocks Badges -->
          <div class="court-meta-chips">
            <span class="meta-chip">
              <span class="material-symbols-outlined icon-16 text-pink">sell</span>
              <strong>{{ court.price_slots?.length || 0 }}</strong> khung giá
            </span>
            <span class="meta-chip" :class="{ 'chip-warning': court.blocks?.length }">
              <span class="material-symbols-outlined icon-16">block</span>
              <strong>{{ court.blocks?.length || 0 }}</strong> lịch khóa
            </span>
          </div>

          <!-- Price Slots Preview -->
          <div v-if="court.price_slots?.length" class="price-slots-preview">
            <span
              v-for="p in (court.price_slots || []).slice(0, 3)"
              :key="p.id || `${p.weekday}-${p.starts_at}`"
              class="mini-price-pill"
            >
              T{{ p.weekday }} {{ p.starts_at.slice(0, 5) }} · {{ formatCurrency(p.price) }}
            </span>
            <span v-if="(court.price_slots?.length || 0) > 3" class="mini-price-pill more-pill">
              +{{ (court.price_slots?.length || 0) - 3 }} khung nữa
            </span>
          </div>

          <!-- Active Blocks Preview -->
          <div v-if="court.blocks?.length" class="blocks-preview-box">
            <div v-for="b in court.blocks" :key="b.id" class="mini-block-item">
              <span class="material-symbols-outlined icon-14 text-error">lock</span>
              <span class="text-xs truncate">{{ b.reason || 'Bảo trì' }} ({{ formatDateTime(b.starts_at) }})</span>
              <button
                type="button"
                class="btn-quick-unlock"
                title="Mở khóa lịch"
                @click="deleteBlock(court, b)"
              >
                ×
              </button>
            </div>
          </div>

          <!-- Action Buttons Bar -->
          <div class="court-actions-toolbar">
            <button
              type="button"
              class="card-action-btn primary-action"
              @click="openPriceModal(court)"
            >
              <span class="material-symbols-outlined icon-16">price_change</span>
              <span>Cài đặt giá</span>
            </button>

            <button
              type="button"
              class="card-action-btn secondary-action"
              @click="openBlockModal(court)"
            >
              <span class="material-symbols-outlined icon-16">lock_clock</span>
              <span>Khóa sân</span>
            </button>

            <button
              type="button"
              class="card-action-btn icon-only-action"
              title="Chỉnh sửa sân"
              @click="startEditCourt(court)"
            >
              <span class="material-symbols-outlined icon-18">edit</span>
            </button>
          </div>
        </div>
      </article>
    </section>

    <!-- Empty State -->
    <div v-if="!loading && !filteredCourts.length" class="empty-state-card">
      <span class="material-symbols-outlined empty-icon">sports_tennis</span>
      <h3>Không tìm thấy sân phù hợp</h3>
      <p class="muted">Thử thay đổi bộ lọc hoặc thêm sân mới.</p>
      <button type="button" class="btn btn-primary mt-3" @click="startCreateCourt">
        + Thêm sân mới
      </button>
    </div>

    <!-- Management Sections Row (Refunds & Coupons) -->
    <div class="bottom-management-grid">
      <!-- Section 1: Refund Requests -->
      <section class="panel-card refunds-panel">
        <div class="panel-head-row">
          <div class="panel-title-group">
            <span class="material-symbols-outlined text-coral icon-22">currency_exchange</span>
            <h2 class="panel-title">Yêu cầu hoàn tiền</h2>
          </div>
          <span class="badge badge-error">{{ refunds.length }} đang chờ</span>
        </div>

        <div v-if="!refunds.length" class="empty-panel-box">
          <span class="material-symbols-outlined icon-24 text-muted">check_circle</span>
          <p class="muted text-sm">Không có yêu cầu hoàn tiền nào đang chờ xử lý.</p>
        </div>

        <div v-else class="table-wrap">
          <table class="aura-admin-table">
            <thead>
              <tr>
                <th>Mã đặt sân</th>
                <th>Khách hàng</th>
                <th>Số tiền</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="refund in refunds" :key="refund.id">
                <td>
                  <span class="code-badge">{{ refund.booking?.code || 'BK-AURA' }}</span>
                </td>
                <td>
                  <div class="user-cell">
                    <span class="user-name-text">{{ refund.booking?.user?.name || 'Khách' }}</span>
                    <span class="user-email-text">{{ refund.booking?.user?.email || '' }}</span>
                  </div>
                </td>
                <td>
                  <strong class="refund-amount-text">{{ formatCurrency(refund.amount) }}</strong>
                </td>
                <td class="table-actions-td">
                  <div class="table-actions-wrap">
                    <button
                      type="button"
                      class="btn-table-action btn-approve"
                      @click="processRefund(refund, 'processed')"
                    >
                      Duyệt
                    </button>
                    <button
                      type="button"
                      class="btn-table-action btn-reject"
                      @click="processRefund(refund, 'rejected')"
                    >
                      Từ chối
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Section 2: Coupons & Discounts -->
      <section class="panel-card coupons-panel">
        <div class="panel-head-row">
          <div class="panel-title-group">
            <span class="material-symbols-outlined text-pink icon-22">confirmation_number</span>
            <h2 class="panel-title">Mã giảm giá & Ưu đãi</h2>
          </div>
          <button
            type="button"
            class="circle-add-btn"
            title="Tạo mã giảm giá"
            @click="openCreateCouponModal"
          >
            <span class="material-symbols-outlined icon-20">add</span>
          </button>
        </div>

        <div class="coupons-chips-grid">
          <div
            v-for="coupon in coupons"
            :key="coupon.id"
            class="coupon-ticket-card"
          >
            <div class="ticket-left">
              <span class="ticket-code">{{ coupon.code }}</span>
              <span class="ticket-discount">
                {{ coupon.discount_type === 'percent' ? `Giảm ${coupon.discount_value}%` : `Giảm ${formatCurrency(coupon.discount_value)}` }}
              </span>
            </div>
            <div class="ticket-right">
              <span class="ticket-usage">
                {{ coupon.usage_count }} / {{ coupon.usage_limit || '∞' }} lượt
              </span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- MODAL 1: Create / Edit Court -->
    <div v-if="showCourtModal" class="modal-backdrop" @click.self="showCourtModal = false">
      <div class="aura-court-modal">
        <!-- Header -->
        <div class="aura-modal-header">
          <div class="aura-header-left">
            <div class="aura-icon-box">
              <span class="material-symbols-outlined">sports_tennis</span>
            </div>
            <div>
              <h3 class="aura-modal-title">{{ editingCourt ? 'Cập nhật sân thi đấu' : 'Thêm sân thi đấu mới' }}</h3>
              <p class="aura-modal-sub">Cấu hình thông số và tải ảnh lưu trữ Cloudflare R2</p>
            </div>
          </div>
          <button type="button" class="aura-btn-close" aria-label="Đóng" @click="showCourtModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="aura-modal-body" @submit.prevent="saveCourt">
          <!-- Row 1: Tên sân & Cụm sân -->
          <div class="aura-form-grid-2">
            <div class="aura-field">
              <label class="aura-label">
                <span>Tên sân thi đấu <span class="text-pink">*</span></span>
              </label>
              <input
                v-model="courtForm.name"
                type="text"
                class="aura-input"
                placeholder="VD: Sân 1 - Yonex BWF"
                required
                maxlength="120"
              />
            </div>

            <div class="aura-field">
              <div class="aura-label-row">
                <span class="aura-label">Cụm sân trực thuộc <span class="text-pink">*</span></span>
                <RouterLink to="/admin/venues" class="aura-text-btn text-[11px]">+ Tạo cụm sân</RouterLink>
              </div>
              <SelectDropdown
                v-model="courtForm.venue_id"
                :options="venueDropdownOptions"
                placeholder="Chọn cụm sân cơ sở..."
                variant="bordered"
              />
            </div>

            <div class="aura-field">
              <div class="aura-label-row">
                <span class="aura-label">Phân khu môn thể thao</span>
                <RouterLink to="/admin/venues" class="aura-text-btn text-[11px]">+ Quản lý khu</RouterLink>
              </div>
              <SelectDropdown
                v-model="courtForm.sport_zone_id"
                :options="sportZoneDropdownOptions"
                placeholder="Chọn phân khu môn thể thao..."
                variant="bordered"
              />
            </div>
          </div>


          <!-- Row 2: Cloudflare R2 Upload Image Box -->
          <div class="aura-field">
            <div class="aura-label-row">
              <span class="aura-label">Hình ảnh sân (Cloudflare R2)</span>
              <button
                v-if="courtForm.image_url"
                type="button"
                class="aura-text-btn"
                @click="courtForm.image_url = ''"
              >
                Xóa ảnh
              </button>
            </div>

            <input
              ref="fileInput"
              type="file"
              accept="image/*"
              style="display: none;"
              @change="handleFileUpload"
            />

            <!-- Preview if image selected -->
            <div v-if="courtForm.image_url" class="aura-img-preview-card" @click="triggerFileInput">
              <img :src="courtForm.image_url" alt="Ảnh sân" class="aura-preview-img" />
              <div class="aura-preview-overlay">
                <span class="aura-r2-tag">
                  <span class="material-symbols-outlined text-xs">cloud_done</span>
                  <span>Cloudflare R2</span>
                </span>
                <span class="aura-change-hint">
                  <span class="material-symbols-outlined text-sm">photo_camera</span>
                  <span>Đổi ảnh khác</span>
                </span>
              </div>
            </div>

            <!-- Upload Dropzone if no image -->
            <div
              v-else
              class="aura-upload-dropzone"
              :class="{ 'is-dragging': isDragging }"
              @click="triggerFileInput"
              @dragover="onDragOver"
              @dragleave="onDragLeave"
              @drop="onDrop"
            >
              <div class="aura-upload-icon-circle">
                <span v-if="uploadingImage" class="material-symbols-outlined animate-spin text-lg text-pink">progress_activity</span>
                <span v-else class="material-symbols-outlined text-xl text-pink">cloud_upload</span>
              </div>
              <div class="aura-upload-text">
                <p class="aura-upload-title">{{ uploadingImage ? 'Đang tải ảnh lên Cloudflare R2...' : 'Tải ảnh sân lên Cloudflare R2' }}</p>
                <p class="aura-upload-hint">Kéo thả hoặc nhấp để chọn tệp (JPG, PNG, WebP tối đa 10MB)</p>
              </div>
            </div>

            <!-- Quick Presets -->
            <div class="aura-presets-bar">
              <span class="aura-presets-label">Mẫu nhanh:</span>
              <div class="aura-presets-list">
                <button
                  v-for="preset in courtImagePresets"
                  :key="preset.name"
                  type="button"
                  class="aura-preset-chip"
                  :class="{ active: courtForm.image_url === preset.url }"
                  @click="courtForm.image_url = preset.url"
                >
                  {{ preset.name }}
                </button>
              </div>
            </div>
          </div>

          <!-- Row 3: Mô tả & Tiện ích sân -->
          <div class="aura-field">
            <label class="aura-label">Mô tả & Tiện ích tiêu chuẩn</label>
            <textarea
              v-model="courtForm.description"
              class="aura-textarea"
              rows="2"
              placeholder="VD: Thảm BWF 5 lớp, đèn LED chống chói 500 Lux, điều hòa trung tâm..."
            ></textarea>
          </div>

          <!-- Row 4: Status Toggle Row -->
          <div class="aura-status-box">
            <div class="aura-status-text">
              <span class="aura-status-name">Cho phép mở đặt lịch trực tuyến</span>
              <span class="aura-status-desc">Hiển thị công khai trên ứng dụng để khách hàng đặt sân</span>
            </div>
            <label class="aura-switch">
              <input v-model="courtForm.is_active" type="checkbox" />
              <span class="aura-slider"></span>
            </label>
          </div>

          <!-- Footer -->
          <div class="aura-modal-footer">
            <button
              type="button"
              class="aura-btn-secondary"
              @click="showCourtModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="submit"
              class="aura-btn-primary"
              :disabled="saving || uploadingImage || !activeVenues.length"
            >
              <span v-if="saving" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
              <span>{{ saving ? t('common.submitting') : (editingCourt ? 'Cập nhật sân' : 'Lưu sân mới') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: Court Pricing Setup (Enhanced Aura Modern) -->
    <div v-if="showPriceModal" class="modal-backdrop" @click.self="showPriceModal = false">
      <div class="aura-pricing-modal-card">
        <!-- Header -->
        <div class="pricing-modal-header">
          <div class="pricing-title-group">
            <div class="pricing-icon-badge">
              <span class="material-symbols-outlined">payments</span>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="pricing-modal-title">Cài đặt bảng giá: {{ selectedCourtForPrice?.name }}</h3>
                <span class="court-venue-badge">
                  <span class="material-symbols-outlined text-xs">location_on</span>
                  {{ selectedCourtForPrice?.venue?.name || 'Aura Court' }}
                </span>
              </div>
              <p class="pricing-modal-subtitle">
                Quản lý khung giá theo từng thứ trong tuần, thiết lập giờ vàng/cao điểm hoặc áp dụng giá hàng loạt.
              </p>
            </div>
          </div>
          <button type="button" class="pricing-btn-close" @click="showPriceModal = false" aria-label="Đóng">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <!-- Metrics Overview Bar -->
        <div class="pricing-metrics-bar">
          <div class="metric-item">
            <span class="metric-label">Số khung giờ</span>
            <span class="metric-value">{{ modalPricingStats.count }} / 17 khung</span>
          </div>
          <div class="metric-item">
            <span class="metric-label">Giá thấp nhất</span>
            <span class="metric-value text-emerald">{{ modalPricingStats.count ? formatCurrency(modalPricingStats.min) : '--' }}</span>
          </div>
          <div class="metric-item">
            <span class="metric-label">Giá cao nhất</span>
            <span class="metric-value text-pink-accent">{{ modalPricingStats.count ? formatCurrency(modalPricingStats.max) : '--' }}</span>
          </div>
          <div class="metric-item">
            <span class="metric-label">Giá trung bình</span>
            <span class="metric-value text-primary">{{ modalPricingStats.count ? formatCurrency(modalPricingStats.avg) : '--' }}</span>
          </div>
        </div>

        <!-- Weekday Navigation Tabs -->
        <div class="weekday-tabs-nav">
          <button
            type="button"
            class="weekday-tab-pill"
            :class="{ active: selectedWeekdayTab === 0 }"
            @click="selectedWeekdayTab = 0"
          >
            <span class="tab-main-text">Tất cả các thứ</span>
            <span class="tab-count-badge">{{ modalPriceSlots.length }}</span>
          </button>
          <button
            v-for="day in weekdayList"
            :key="day.id"
            type="button"
            class="weekday-tab-pill"
            :class="{ active: selectedWeekdayTab === day.id, 'weekend-pill': day.isWeekend }"
            @click="selectedWeekdayTab = day.id"
          >
            <span class="tab-main-text">{{ day.label }}</span>
            <span v-if="day.isWeekend" class="tab-tag">Cuối tuần</span>
            <span class="tab-count-badge">
              {{ modalPriceSlots.filter((s) => s.weekday === day.id).length }}
            </span>
          </button>
        </div>

        <!-- Modal Body Content -->
        <div class="pricing-modal-body">
          
          <!-- Section 1: Quick Presets & Bulk Tool -->
          <div class="pricing-tool-panel">
            <div class="tool-panel-header">
              <div class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-pink-accent">bolt</span>
                <span class="font-bold text-sm">Công cụ áp dụng nhanh & Cập nhật hàng loạt</span>
              </div>
              <!-- Presets -->
              <div class="presets-row">
                <span class="text-xs text-muted">Mẫu cấu hình nhanh:</span>
                <button type="button" class="btn-preset" @click="applyPreset('standard')">
                  🌅 Giờ thường (100k)
                </button>
                <button type="button" class="btn-preset btn-preset-peak" @click="applyPreset('peak')">
                  ⚡ Giờ cao điểm (130k)
                </button>
                <button type="button" class="btn-preset btn-preset-weekend" @click="applyPreset('weekend')">
                  🎉 Cuối tuần (140k)
                </button>
              </div>
            </div>

            <!-- Bulk Form -->
            <form class="bulk-form-grid" @submit.prevent="applyBulkPricing">
              <div class="bulk-form-field">
                <label class="bulk-label">Phạm vi áp dụng:</label>
                <SelectDropdown
                  v-model="bulkScope"
                  :options="bulkScopeOptions"
                  variant="bordered"
                />
              </div>

              <div class="bulk-form-field">
                <label class="bulk-label">Khung giờ:</label>
                <div class="hour-range-inputs">
                  <div class="flex-1">
                    <SelectDropdown
                      v-model="bulkStartHour"
                      :options="bulkStartHourOptions"
                      variant="bordered"
                    />
                  </div>
                  <span class="range-sep">→</span>
                  <div class="flex-1">
                    <SelectDropdown
                      v-model="bulkEndHour"
                      :options="bulkEndHourOptions"
                      variant="bordered"
                    />
                  </div>
                </div>
              </div>

              <div class="bulk-form-field price-input-col">
                <label class="bulk-label">Mức giá áp dụng (₫/giờ):</label>
                <div class="price-input-wrapper">
                  <input
                    v-model="formattedBulkPrice"
                    type="text"
                    inputmode="numeric"
                    class="bulk-input-price"
                    placeholder="VD: 100.000"
                    required
                  />
                  <div class="quick-price-chips">
                    <button type="button" class="price-chip" @click="bulkPrice = 80000">80k</button>
                    <button type="button" class="price-chip" @click="bulkPrice = 100000">100k</button>
                    <button type="button" class="price-chip" @click="bulkPrice = 120000">120k</button>
                    <button type="button" class="price-chip" @click="bulkPrice = 140000">140k</button>
                    <button type="button" class="price-chip" @click="bulkPrice = 160000">160k</button>
                  </div>
                </div>
              </div>

              <div class="bulk-action-col">
                <button
                  type="submit"
                  class="btn-apply-bulk"
                  :disabled="bulkSaving"
                >
                  <span v-if="bulkSaving" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
                  <span v-else class="material-symbols-outlined text-sm">auto_fix_high</span>
                  <span>{{ bulkSaving ? 'Đang cập nhật…' : 'Áp dụng khung giá' }}</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Section 2: Detailed Hourly Slots Grid -->
          <div class="hourly-matrix-section">
            <div class="matrix-header-row">
              <div class="flex items-center gap-2">
                <h4 class="matrix-title">
                  {{ selectedWeekdayTab === 0 ? 'Toàn bộ khung giờ đã thiết lập' : `Chi tiết 17 khung giờ (${weekdayList.find(w => w.id === selectedWeekdayTab)?.label || ''})` }}
                </h4>
                <span class="active-count-tag">{{ activeWeekdaySlots.length }} khung hoạt động</span>
              </div>
              <span class="muted text-xs">💡 Bấm vào mức giá để chỉnh sửa trực tiếp tại chỗ</span>
            </div>

            <!-- Slots Grid Groups -->
            <div v-if="activeWeekdaySlots.length" class="sessions-container">
              
              <!-- Sáng -->
              <div v-if="morningSlots.length" class="session-block">
                <div class="session-label">
                  <span class="session-icon">🌅</span>
                  <span>Buổi sáng (06:00 – 12:00)</span>
                </div>
                <div class="hourly-slots-grid">
                  <div
                    v-for="slot in morningSlots"
                    :key="slot.id"
                    class="hour-slot-card"
                    :class="{ 'editing-card': inlineEditingSlotId === slot.id }"
                  >
                    <div class="slot-card-top">
                      <span v-if="selectedWeekdayTab === 0" class="slot-weekday-tag">T{{ slot.weekday }}</span>
                      <span class="slot-time-badge">{{ slot.starts_at.slice(0, 5) }} – {{ slot.ends_at.slice(0, 5) }}</span>
                    </div>

                    <!-- Inline edit mode -->
                    <div v-if="inlineEditingSlotId === slot.id" class="inline-edit-box">
                      <input
                        v-model="formattedInlinePrice"
                        type="text"
                        inputmode="numeric"
                        class="inline-price-input"
                        placeholder="VD: 100.000"
                        @keyup.enter="saveInlineEdit(slot)"
                        @keyup.esc="cancelInlineEdit"
                        autoFocus
                      />
                      <div class="inline-actions">
                        <button type="button" class="btn-inline-save" @click="saveInlineEdit(slot)" title="Lưu">
                          <span class="material-symbols-outlined icon-16">check</span>
                        </button>
                        <button type="button" class="btn-inline-cancel" @click="cancelInlineEdit" title="Hủy">
                          <span class="material-symbols-outlined icon-16">close</span>
                        </button>
                      </div>
                    </div>

                    <!-- Normal display mode -->
                    <div v-else class="slot-card-body" @click="startInlineEdit(slot)">
                      <div class="price-display-box">
                        <span class="price-num">{{ formatCurrency(slot.price) }}</span>
                        <span class="price-unit">/h</span>
                      </div>
                      <div class="slot-card-actions" @click.stop>
                        <button type="button" class="btn-mini-adjust" @click="quickAdjustPrice(slot, -10000)" title="Giảm 10k">-10k</button>
                        <button type="button" class="btn-mini-adjust" @click="quickAdjustPrice(slot, 10000)" title="Tăng 10k">+10k</button>
                        <button type="button" class="btn-mini-del" @click="deletePriceSlot(slot)" title="Xóa">
                          <span class="material-symbols-outlined icon-14">delete</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Chiều -->
              <div v-if="afternoonSlots.length" class="session-block">
                <div class="session-label">
                  <span class="session-icon">☀️</span>
                  <span>Buổi chiều (12:00 – 17:00)</span>
                </div>
                <div class="hourly-slots-grid">
                  <div
                    v-for="slot in afternoonSlots"
                    :key="slot.id"
                    class="hour-slot-card"
                    :class="{ 'editing-card': inlineEditingSlotId === slot.id }"
                  >
                    <div class="slot-card-top">
                      <span v-if="selectedWeekdayTab === 0" class="slot-weekday-tag">T{{ slot.weekday }}</span>
                      <span class="slot-time-badge">{{ slot.starts_at.slice(0, 5) }} – {{ slot.ends_at.slice(0, 5) }}</span>
                    </div>

                    <div v-if="inlineEditingSlotId === slot.id" class="inline-edit-box">
                      <input
                        v-model="formattedInlinePrice"
                        type="text"
                        inputmode="numeric"
                        class="inline-price-input"
                        placeholder="VD: 100.000"
                        @keyup.enter="saveInlineEdit(slot)"
                        @keyup.esc="cancelInlineEdit"
                      />
                      <div class="inline-actions">
                        <button type="button" class="btn-inline-save" @click="saveInlineEdit(slot)" title="Lưu">
                          <span class="material-symbols-outlined icon-16">check</span>
                        </button>
                        <button type="button" class="btn-inline-cancel" @click="cancelInlineEdit" title="Hủy">
                          <span class="material-symbols-outlined icon-16">close</span>
                        </button>
                      </div>
                    </div>

                    <div v-else class="slot-card-body" @click="startInlineEdit(slot)">
                      <div class="price-display-box">
                        <span class="price-num">{{ formatCurrency(slot.price) }}</span>
                        <span class="price-unit">/h</span>
                      </div>
                      <div class="slot-card-actions" @click.stop>
                        <button type="button" class="btn-mini-adjust" @click="quickAdjustPrice(slot, -10000)">-10k</button>
                        <button type="button" class="btn-mini-adjust" @click="quickAdjustPrice(slot, 10000)">+10k</button>
                        <button type="button" class="btn-mini-del" @click="deletePriceSlot(slot)">
                          <span class="material-symbols-outlined icon-14">delete</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Giờ vàng / Tối -->
              <div v-if="peakSlots.length" class="session-block peak-session-block">
                <div class="session-label">
                  <span class="session-icon">⚡</span>
                  <span class="font-bold text-pink-accent">Giờ cao điểm tối (17:00 – 22:00)</span>
                  <span class="peak-tag">PEAK HOURS</span>
                </div>
                <div class="hourly-slots-grid">
                  <div
                    v-for="slot in peakSlots"
                    :key="slot.id"
                    class="hour-slot-card peak-card"
                    :class="{ 'editing-card': inlineEditingSlotId === slot.id }"
                  >
                    <div class="slot-card-top">
                      <span v-if="selectedWeekdayTab === 0" class="slot-weekday-tag">T{{ slot.weekday }}</span>
                      <span class="slot-time-badge peak-time-badge">{{ slot.starts_at.slice(0, 5) }} – {{ slot.ends_at.slice(0, 5) }}</span>
                    </div>

                    <div v-if="inlineEditingSlotId === slot.id" class="inline-edit-box">
                      <input
                        v-model="formattedInlinePrice"
                        type="text"
                        inputmode="numeric"
                        class="inline-price-input"
                        placeholder="VD: 100.000"
                        @keyup.enter="saveInlineEdit(slot)"
                        @keyup.esc="cancelInlineEdit"
                      />
                      <div class="inline-actions">
                        <button type="button" class="btn-inline-save" @click="saveInlineEdit(slot)" title="Lưu">
                          <span class="material-symbols-outlined icon-16">check</span>
                        </button>
                        <button type="button" class="btn-inline-cancel" @click="cancelInlineEdit" title="Hủy">
                          <span class="material-symbols-outlined icon-16">close</span>
                        </button>
                      </div>
                    </div>

                    <div v-else class="slot-card-body" @click="startInlineEdit(slot)">
                      <div class="price-display-box">
                        <span class="price-num text-pink-accent">{{ formatCurrency(slot.price) }}</span>
                        <span class="price-unit">/h</span>
                      </div>
                      <div class="slot-card-actions" @click.stop>
                        <button type="button" class="btn-mini-adjust" @click="quickAdjustPrice(slot, -10000)">-10k</button>
                        <button type="button" class="btn-mini-adjust" @click="quickAdjustPrice(slot, 10000)">+10k</button>
                        <button type="button" class="btn-mini-del" @click="deletePriceSlot(slot)">
                          <span class="material-symbols-outlined icon-14">delete</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Đêm muộn -->
              <div v-if="nightSlots.length" class="session-block">
                <div class="session-label">
                  <span class="session-icon">🌙</span>
                  <span>Đêm muộn (22:00 – 23:00)</span>
                </div>
                <div class="hourly-slots-grid">
                  <div
                    v-for="slot in nightSlots"
                    :key="slot.id"
                    class="hour-slot-card"
                    :class="{ 'editing-card': inlineEditingSlotId === slot.id }"
                  >
                    <div class="slot-card-top">
                      <span v-if="selectedWeekdayTab === 0" class="slot-weekday-tag">T{{ slot.weekday }}</span>
                      <span class="slot-time-badge">{{ slot.starts_at.slice(0, 5) }} – {{ slot.ends_at.slice(0, 5) }}</span>
                    </div>

                    <div v-if="inlineEditingSlotId === slot.id" class="inline-edit-box">
                      <input
                        v-model="formattedInlinePrice"
                        type="text"
                        inputmode="numeric"
                        class="inline-price-input"
                        placeholder="VD: 100.000"
                        @keyup.enter="saveInlineEdit(slot)"
                        @keyup.esc="cancelInlineEdit"
                      />
                      <div class="inline-actions">
                        <button type="button" class="btn-inline-save" @click="saveInlineEdit(slot)" title="Lưu">
                          <span class="material-symbols-outlined icon-16">check</span>
                        </button>
                        <button type="button" class="btn-inline-cancel" @click="cancelInlineEdit" title="Hủy">
                          <span class="material-symbols-outlined icon-16">close</span>
                        </button>
                      </div>
                    </div>

                    <div v-else class="slot-card-body" @click="startInlineEdit(slot)">
                      <div class="price-display-box">
                        <span class="price-num">{{ formatCurrency(slot.price) }}</span>
                        <span class="price-unit">/h</span>
                      </div>
                      <div class="slot-card-actions" @click.stop>
                        <button type="button" class="btn-mini-adjust" @click="quickAdjustPrice(slot, -10000)">-10k</button>
                        <button type="button" class="btn-mini-adjust" @click="quickAdjustPrice(slot, 10000)">+10k</button>
                        <button type="button" class="btn-mini-del" @click="deletePriceSlot(slot)">
                          <span class="material-symbols-outlined icon-14">delete</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>

            <!-- Empty slots state -->
            <div v-else class="empty-slots-state">
              <span class="material-symbols-outlined empty-icon">event_busy</span>
              <p class="font-semibold text-sm">Chưa có khung giá nào cho ngày này</p>
              <p class="muted text-xs">Hãy sử dụng công cụ áp dụng nhanh ở trên để tạo 17 khung giờ chuẩn chỉ với 1 thao tác.</p>
            </div>
          </div>

        </div>

        <!-- Footer -->
        <div class="pricing-modal-footer">
          <div class="flex items-center gap-2 text-xs text-muted">
            <span class="material-symbols-outlined text-sm text-pink-accent">verified</span>
            <span>Mọi thay đổi bảng giá sẽ có hiệu lực ngay lập tức cho các lượt đặt sân mới.</span>
          </div>
          <button type="button" class="aura-btn-primary" @click="showPriceModal = false">
            <span class="material-symbols-outlined text-sm">done_all</span>
            <span>Hoàn tất & Đóng</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 3: Court Maintenance Block -->
    <div v-if="showBlockModal" class="modal-backdrop" @click.self="showBlockModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-xs">
            <span class="material-symbols-outlined text-coral">block</span>
            <h3 class="font-semibold text-lg">Khóa lịch: {{ selectedCourtForBlock?.name }}</h3>
          </div>
          <button type="button" class="btn-close" @click="showBlockModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-body space-y-4" @submit.prevent="saveBlock">
          <div class="form-group">
            <label class="form-label">Thời gian bắt đầu khóa:</label>
            <DateTimePicker v-model="blockForm.starts_at" />
          </div>

          <div class="form-group">
            <label class="form-label">Thời gian kết thúc khóa:</label>
            <DateTimePicker v-model="blockForm.ends_at" />
          </div>

          <div class="form-group">
            <label class="form-label">Lý do khóa sân:</label>
            <input
              v-model="blockForm.reason"
              type="text"
              class="form-input"
              placeholder="VD: Thay thảm, bảo dưỡng đèn LED, sự kiện riêng..."
              required
            />
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showBlockModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="saving"
            >
              {{ saving ? t('common.submitting') : 'Khóa lịch sân' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 4: Create Coupon -->
    <div v-if="showCouponModal" class="modal-backdrop" @click.self="showCouponModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-xs">
            <span class="material-symbols-outlined text-pink">confirmation_number</span>
            <h3 class="font-semibold text-lg">Tạo mã giảm giá mới</h3>
          </div>
          <button type="button" class="btn-close" @click="showCouponModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-body space-y-4" @submit.prevent="createCoupon">
          <div class="form-group">
            <label class="form-label">Mã khuyến mãi (Code):</label>
            <input
              v-model="couponForm.code"
              type="text"
              class="form-input uppercase"
              placeholder="VD: AURA2026, WELCOME10"
              required
              maxlength="40"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="form-group">
              <label class="form-label">Loại giảm:</label>
              <SelectDropdown
                v-model="couponForm.discount_type"
                :options="discountTypeOptions"
                variant="bordered"
              />
            </div>
            <div class="form-group">
              <label class="form-label">Giá trị giảm:</label>
              <input
                v-model.number="couponForm.discount_value"
                type="number"
                min="1"
                class="form-input"
                required
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Giới hạn số lượt dùng:</label>
            <input
              v-model.number="couponForm.usage_limit"
              type="number"
              min="1"
              class="form-input"
              required
            />
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showCouponModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="saving"
            >
              {{ saving ? t('common.submitting') : 'Tạo mã giảm giá' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-manage-content-wrap {
  max-width: 1280px;
  margin: 0 auto;
  padding: 30px 24px 90px;
}

/* Header */
.admin-manage-header {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-bottom: 24px;
  margin-bottom: 24px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

@media (min-width: 860px) {
  .admin-manage-header {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
  }
}

.eyebrow-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}

:global(html.dark) .eyebrow-tag {
  background: rgba(248, 180, 196, 0.18);
  color: #f8b4c4;
  border: 1px solid rgba(248, 180, 196, 0.35);
  box-shadow: 0 0 12px rgba(248, 180, 196, 0.15);
}

/* Stats Overview Grid */
.stats-overview-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

@media (min-width: 1024px) {
  .stats-overview-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.stat-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 18px;
  border: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

:global(html.dark) .stat-card {
  background: var(--surface, #22242a);
  border-color: var(--border, #353942);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.stat-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-primary {
  background: rgba(255, 209, 220, 0.2);
  color: var(--pink-dark, #78555e);
}

:global(html.dark) .icon-primary {
  background: rgba(248, 180, 196, 0.18);
  color: #f8b4c4;
}

.icon-success {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}

.icon-warning {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
}

.icon-error {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.stat-label {
  font-size: 12px;
  color: var(--muted, #6c757d);
  font-weight: 500;
}

:global(html.dark) .stat-label {
  color: #c0c4cc;
}

.stat-number {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 2px 0 0;
  line-height: 1;
}

:global(html.dark) .stat-number {
  color: #ffffff;
}

/* Notifications */
.notice-banner,
.error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 14px;
  font-weight: 500;
}

.notice-banner {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.error-banner {
  background: rgba(239, 68, 68, 0.12);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

/* Toolbar Filter */
.toolbar-filter-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 14px 18px;
  border: 1px solid var(--border, #e0e4ea);
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

:global(html.dark) .toolbar-filter-card {
  background: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

@media (min-width: 768px) {
  .toolbar-filter-card {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.search-input-box {
  position: relative;
  flex: 1;
  max-width: 420px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted, #94a3b8);
  font-size: 18px;
}

:global(html.dark) .search-icon {
  color: #9ca3af;
}

.search-input {
  width: 100%;
  padding: 8px 14px 8px 38px;
  border-radius: 9999px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface-subtle, #f8f9fa);
  color: var(--ink, #191c1d);
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
}

:global(html.dark) .search-input {
  background: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: #ffffff;
}

:global(html.dark) .search-input::placeholder {
  color: #9ca3af;
}

.filter-controls-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.filter-select {
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 13px;
  outline: none;
}

:global(html.dark) .filter-select {
  background: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: #ffffff;
}

/* Skeleton Loading Helpers */
.skeleton-stat-card {
  display: flex;
  align-items: center;
  gap: 14px;
}

.skeleton-stat-lines {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.skeleton-admin-card {
  overflow: hidden;
  border-radius: 20px;
}

.skeleton-admin-media {
  height: 160px;
  width: 100%;
}

.skeleton-badges-row {
  display: flex;
  gap: 8px;
  margin: 12px 0 16px;
}

.skeleton-actions-row {
  margin-top: auto;
}

/* Courts Cards Grid */
.courts-cards-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
  margin-bottom: 36px;
}

@media (min-width: 768px) {
  .courts-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1100px) {
  .courts-cards-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.court-manage-card {
  background: var(--surface, #ffffff);
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.court-manage-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.court-manage-card.is-inactive {
  opacity: 0.75;
}

.court-card-media {
  position: relative;
  height: 160px;
  overflow: hidden;
}

.court-card-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.media-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.15) 0%, rgba(0, 0, 0, 0.75) 100%);
}

.status-toggle-corner {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 2;
}

.status-badge-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  backdrop-filter: blur(6px);
}

.badge-active {
  background: rgba(16, 185, 129, 0.9);
  color: #ffffff;
}

.badge-inactive {
  background: rgba(239, 68, 68, 0.9);
  color: #ffffff;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ffffff;
}

.media-bottom-info {
  position: absolute;
  bottom: 10px;
  left: 12px;
  z-index: 2;
}

.venue-name-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 6px;
  background: rgba(20, 22, 28, 0.82);
  backdrop-filter: blur(8px);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.15);
  font-size: 11px;
  font-weight: 600;
}

.court-card-body {
  padding: 16px;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.court-title-text {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0 0 4px;
}

.court-description-text {
  font-size: 12px;
  color: var(--muted, #6c757d);
  margin: 0 0 12px;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.court-meta-chips {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}

.meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 8px;
  background: var(--surface-subtle, #f3f4f5);
  font-size: 11px;
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
}

.chip-warning {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.25);
}

.price-slots-preview {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 12px;
}

.mini-price-pill {
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
  font-size: 10px;
  color: var(--muted, #6c757d);
}

.more-pill {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  font-weight: 600;
  border: none;
}

.blocks-preview-box {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 8px;
  padding: 6px 10px;
  margin-bottom: 12px;
  color: var(--ink, #191c1d);
}

.mini-block-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  color: var(--ink, #191c1d);
}

.btn-quick-unlock {
  border: none;
  background: transparent;
  color: #ef4444;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  padding: 0 4px;
}

.court-actions-toolbar {
  margin-top: auto;
  display: flex;
  align-items: center;
  gap: 6px;
  padding-top: 12px;
  border-top: 1px solid var(--border, #e0e4ea);
}

.card-action-btn {
  padding: 7px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: all 0.15s ease;
}

.primary-action {
  flex: 1;
  background: var(--primary, #78555e) !important;
  color: var(--on-primary, #ffffff) !important;
  font-weight: 700;
  justify-content: center;
}

.primary-action:hover {
  filter: brightness(1.1);
}

:global(html.dark) .primary-action {
  background: #f8b4c4 !important;
  color: #201317 !important;
  font-weight: 800 !important;
}

:global(html.dark) .primary-action:hover {
  background: #ffd5df !important;
}

.secondary-action {
  background: var(--surface-subtle, #f3f4f5);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
}

.secondary-action:hover {
  background: var(--soft, #e7e8e9);
}

.icon-only-action {
  padding: 6px;
  background: transparent;
  color: var(--muted, #6c757d);
  border: 1px solid var(--border, #e0e4ea);
}

.icon-only-action:hover {
  color: var(--ink, #191c1d);
  background: var(--surface-subtle, #f3f4f5);
}

/* Empty State */
.empty-state-card {
  text-align: center;
  padding: 60px 20px;
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px dashed var(--border, #cbd5e1);
  margin-bottom: 30px;
}

.empty-icon {
  font-size: 48px;
  color: var(--muted, #94a3b8);
  margin-bottom: 8px;
}

/* Bottom Management Grid */
.bottom-management-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 1024px) {
  .bottom-management-grid {
    grid-template-columns: 7fr 5fr;
  }
}

.panel-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 22px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.panel-head-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.panel-title-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.panel-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.badge-error {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.25);
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
}

.empty-panel-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 30px 0;
}

/* Table */
.aura-admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.aura-admin-table th {
  text-align: left;
  padding: 10px 12px;
  color: var(--muted, #6c757d);
  font-weight: 600;
  border-bottom: 1px solid var(--border, #e0e4ea);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.aura-admin-table td {
  padding: 12px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  color: var(--ink, #191c1d);
}

.code-badge {
  font-family: monospace;
  font-weight: 700;
  padding: 3px 6px;
  border-radius: 4px;
  background: var(--surface-subtle, #f3f4f5);
  border: 1px solid var(--border, #e0e4ea);
  font-size: 12px;
  color: var(--ink, #191c1d);
}

.user-cell {
  display: flex;
  flex-direction: column;
}

.user-name-text {
  font-weight: 600;
}

.user-email-text {
  font-size: 11px;
  color: var(--muted, #6c757d);
}

.refund-amount-text {
  color: #ef4444;
  font-weight: 700;
}

.table-actions-td {
  white-space: nowrap;
  width: 1%;
  vertical-align: middle;
}

.table-actions-wrap {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
  flex-wrap: nowrap;
}

.btn-table-action {
  padding: 5px 12px;
  border-radius: 8px;
  font-size: 11.5px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
  line-height: 1.2;
}

.btn-approve {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}

.btn-approve:hover {
  background: rgba(16, 185, 129, 0.28);
  transform: translateY(-1px);
}

.btn-reject {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.btn-reject:hover {
  background: rgba(239, 68, 68, 0.28);
  transform: translateY(-1px);
}

/* Coupon Tickets */
.circle-add-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.circle-add-btn:hover {
  filter: brightness(1.1);
}

.coupons-chips-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.coupon-ticket-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px dashed var(--border, #cbd5e1);
}

.ticket-left {
  display: flex;
  flex-direction: column;
}

.ticket-code {
  font-weight: 800;
  font-size: 14px;
  letter-spacing: 0.05em;
  color: var(--ink, #191c1d);
}

.ticket-discount {
  font-size: 12px;
  font-weight: 600;
  color: var(--pink-dark, #78555e);
}

.ticket-usage {
  font-size: 11px;
  font-weight: 500;
  color: var(--muted, #6c757d);
  background: var(--surface, #ffffff);
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid var(--border, #e0e4ea);
}

.btn-delete-slot {
  border: none;
  background: transparent;
  color: #ef4444;
  cursor: pointer;
  padding: 4px;
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-card {
  background: var(--surface, #ffffff);
  border-radius: 20px;
  padding: 24px;
  width: 100%;
  max-width: 520px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
  animation: modal-enter 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  color: var(--ink, #191c1d);
}

@keyframes modal-enter {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 16px;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.btn-close {
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  padding: 4px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  font-size: 14px;
  background: var(--surface-subtle, #f8f9fa);
  color: var(--ink, #191c1d);
  font-family: inherit;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: var(--pink-dark, #78555e);
  box-shadow: 0 0 0 3px rgba(120, 85, 94, 0.15);
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--border, #e0e4ea);
}

.btn {
  padding: 10px 20px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn-primary {
  background: var(--primary, #78555e) !important;
  color: var(--on-primary, #ffffff) !important;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
  font-weight: 700;
}

.btn-primary:hover {
  filter: brightness(1.1);
  transform: translateY(-1px);
}

:global(html.dark) .btn-primary {
  background: #f8b4c4 !important;
  color: #201317 !important;
  font-weight: 800 !important;
  box-shadow: 0 4px 18px rgba(248, 180, 196, 0.38) !important;
}

:global(html.dark) .btn-primary:hover {
  background: #ffd5df !important;
  color: #201317 !important;
  box-shadow: 0 6px 24px rgba(248, 180, 196, 0.5) !important;
}

.btn-secondary {
  background: var(--surface-subtle, #e7e8e9);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
}

.btn-secondary:hover {
  background: var(--soft, #dfe0e0);
}

/* Court Modal Spacious Redesign */
/* Aura Court Modal Premium Design */
.aura-court-modal {
  width: 100%;
  max-width: 580px;
  background: var(--surface, #1e2025);
  border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
  border-radius: 20px;
  padding: 24px 28px;
  box-shadow: 0 24px 50px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(255, 255, 255, 0.05);
  color: var(--ink, #ffffff);
  animation: modal-enter 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-sizing: border-box;
}

.aura-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 16px;
  margin-bottom: 18px;
  border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.08));
}

.aura-header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.aura-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--pink-light, rgba(248, 180, 196, 0.12));
  border: 1px solid var(--outline, rgba(248, 180, 196, 0.25));
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--pink-dark, #f8b4c4);
  flex-shrink: 0;
}

.aura-icon-box .material-symbols-outlined {
  font-size: 22px;
}

.aura-modal-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--ink, #ffffff);
  margin: 0;
  line-height: 1.3;
}

.aura-modal-sub {
  font-size: 12px;
  color: var(--muted, #9ca3af);
  margin: 2px 0 0;
}

.aura-btn-close {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--muted, #9ca3af);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.aura-btn-close:hover {
  background: var(--soft, rgba(255, 255, 255, 0.08));
  color: var(--ink, #ffffff);
}

.aura-modal-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.aura-form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

@media (max-width: 540px) {
  .aura-form-grid-2 {
    grid-template-columns: 1fr;
  }
}

.aura-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.aura-label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.aura-label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--ink, #e5e7eb);
}

.aura-text-btn {
  font-size: 11.5px;
  color: var(--pink-dark, #f8b4c4);
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
}

.aura-text-btn:hover {
  opacity: 0.8;
}

.aura-input,
.aura-select,
.aura-textarea {
  width: 100%;
  padding: 10px 14px;
  border-radius: 12px;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.04));
  border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
  color: var(--ink, #ffffff);
  font-size: 13.5px;
  font-family: inherit;
  box-sizing: border-box;
  transition: all 0.2s ease;
}

.aura-input:focus,
.aura-select:focus,
.aura-textarea:focus {
  outline: none;
  border-color: var(--pink-dark, #f8b4c4);
  box-shadow: 0 0 0 3px rgba(248, 180, 196, 0.15);
  background: var(--surface, #1e2025);
}

.aura-select {
  cursor: pointer;
}

.aura-textarea {
  resize: vertical;
  min-height: 56px;
}

/* Upload Dropzone */
.aura-upload-dropzone {
  border: 1.5px dashed var(--border, rgba(255, 255, 255, 0.15));
  border-radius: 14px;
  padding: 14px 18px;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.02));
  display: flex;
  align-items: center;
  gap: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.aura-upload-dropzone:hover,
.aura-upload-dropzone.is-dragging {
  border-color: var(--pink-dark, #f8b4c4);
  background: var(--pink-light, rgba(248, 180, 196, 0.06));
}

.aura-upload-icon-circle {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--pink-light, rgba(248, 180, 196, 0.12));
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.aura-upload-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #ffffff);
  margin: 0;
}

.aura-upload-hint {
  font-size: 11.5px;
  color: var(--muted, #9ca3af);
  margin: 2px 0 0;
}

/* Preview Card */
.aura-img-preview-card {
  position: relative;
  height: 110px;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid var(--border, rgba(255, 255, 255, 0.12));
  cursor: pointer;
  background: #000;
}

.aura-preview-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.aura-preview-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0,0,0,0.1) 30%, rgba(0,0,0,0.7) 100%);
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  padding: 10px 14px;
}

.aura-r2-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  color: #34d399;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  padding: 3px 8px;
  border-radius: 6px;
}

.aura-change-hint {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  color: #ffffff;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(4px);
  padding: 3px 8px;
  border-radius: 6px;
}

/* Presets Bar */
.aura-presets-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
  flex-wrap: wrap;
}

.aura-presets-label {
  font-size: 11.5px;
  color: var(--muted, #9ca3af);
}

.aura-presets-list {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.aura-preset-chip {
  font-size: 11px;
  font-weight: 500;
  padding: 3px 9px;
  border-radius: 6px;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.04));
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  color: var(--ink, #d1d5db);
  cursor: pointer;
  transition: all 0.15s ease;
}

.aura-preset-chip:hover {
  border-color: var(--pink-dark, #f8b4c4);
  color: var(--ink, #ffffff);
}

.aura-preset-chip.active {
  background: var(--pink-light, rgba(248, 180, 196, 0.15));
  border-color: var(--pink-dark, #f8b4c4);
  color: var(--pink-dark, #f8b4c4);
  font-weight: 600;
}

/* Status Box */
.aura-status-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-radius: 14px;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.03));
  border: 1px solid var(--border, rgba(255, 255, 255, 0.06));
}

.aura-status-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #ffffff);
  display: block;
}

.aura-status-desc {
  font-size: 11.5px;
  color: var(--muted, #9ca3af);
  display: block;
  margin-top: 1px;
}

/* Switch Toggle */
.aura-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  flex-shrink: 0;
  cursor: pointer;
}

.aura-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.aura-slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background-color: var(--outline, #4b5563);
  transition: 0.25s ease;
  border-radius: 24px;
}

.aura-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: #ffffff;
  transition: 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  border-radius: 50%;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.aura-switch input:checked + .aura-slider {
  background-color: var(--pink-dark, #f8b4c4);
}

.aura-switch input:checked + .aura-slider:before {
  transform: translateX(20px);
  background-color: #3d2b31;
}

/* Footer */
.aura-modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 16px;
  margin-top: 4px;
  border-top: 1px solid var(--border, rgba(255, 255, 255, 0.08));
}

.aura-btn-secondary {
  padding: 9px 18px;
  border-radius: 12px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.06));
  border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
  color: var(--ink, #d1d5db);
  transition: all 0.15s ease;
}

.aura-btn-secondary:hover {
  background: var(--soft, rgba(255, 255, 255, 0.12));
  color: var(--ink, #ffffff);
}

.aura-btn-primary {
  padding: 9px 24px;
  border-radius: 12px;
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  background: var(--primary, #78555e) !important;
  border: none;
  color: var(--on-primary, #ffffff) !important;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
  transition: all 0.15s ease;
}

.aura-btn-primary:hover:not(:disabled) {
  filter: brightness(1.1);
  transform: translateY(-1px);
}

:global(html.dark) .aura-btn-primary {
  background: #f8b4c4 !important;
  color: #201317 !important;
  font-weight: 800 !important;
  box-shadow: 0 4px 18px rgba(248, 180, 196, 0.38) !important;
}

:global(html.dark) .aura-btn-primary:hover:not(:disabled) {
  background: #ffd5df !important;
  color: #201317 !important;
  box-shadow: 0 6px 24px rgba(248, 180, 196, 0.5) !important;
}

/* ==========================================================================
   ENHANCED PRICING MANAGEMENT MODAL STYLES
   ========================================================================== */
.aura-pricing-modal-card {
  background: var(--surface, #1e2025);
  border-radius: 24px;
  width: 100%;
  max-width: 1020px;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
  animation: modal-enter 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  color: var(--ink, #191c1d);
  overflow: hidden;
}

.pricing-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  background: var(--surface, #1e2025);
}

.pricing-title-group {
  display: flex;
  align-items: center;
  gap: 14px;
}

.pricing-icon-badge {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(248, 180, 196, 0.12);
  color: var(--pink-dark, #f8b4c4);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.pricing-modal-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--ink, #ffffff);
  margin: 0;
}

.court-venue-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 6px;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.06));
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  color: var(--muted, #9ca3af);
}

.pricing-modal-subtitle {
  font-size: 12px;
  color: var(--muted, #9ca3af);
  margin: 3px 0 0;
}

.pricing-btn-close {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  background: var(--surface-subtle, rgba(255, 255, 255, 0.04));
  color: var(--muted, #9ca3af);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.pricing-btn-close:hover {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.3);
}

/* Metrics Bar */
.pricing-metrics-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  padding: 12px 24px;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.03));
  border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.08));
}

.metric-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.metric-label {
  font-size: 10.5px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
  color: var(--muted, #9ca3af);
}

.metric-value {
  font-size: 14.5px;
  font-weight: 700;
  color: var(--ink, #ffffff);
}

.text-emerald { color: #34d399 !important; }
.text-pink-accent { color: var(--pink-dark, #f8b4c4) !important; }

/* Weekday Tabs Navigation */
.weekday-tabs-nav {
  display: flex;
  gap: 6px;
  padding: 10px 24px;
  overflow-x: auto;
  border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  background: var(--surface, #1e2025);
}

.weekday-tab-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 10px;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.04));
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #9ca3af);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.weekday-tab-pill:hover {
  color: var(--ink, #ffffff);
  border-color: var(--pink-dark, #f8b4c4);
}

.weekday-tab-pill.active {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  border-color: var(--pink, #ffd1dc);
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(248, 180, 196, 0.25);
}

.weekday-tab-pill.active .tab-tag {
  background: rgba(120, 85, 94, 0.15);
  color: var(--pink-dark, #78555e);
}

.weekday-tab-pill.active .tab-count-badge {
  background: rgba(120, 85, 94, 0.15);
  color: var(--pink-dark, #78555e);
}

.tab-tag {
  font-size: 9.5px;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 4px;
  background: rgba(52, 211, 153, 0.12);
  color: #34d399;
}

.tab-count-badge {
  font-size: 10.5px;
  padding: 1px 6px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.08);
  color: var(--muted, #9ca3af);
}

/* Modal Body */
.pricing-modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 18px 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* Tool Panel (Presets & Bulk) */
.pricing-tool-panel {
  background: var(--surface-subtle, rgba(255, 255, 255, 0.03));
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  border-radius: 16px;
  padding: 14px 18px;
}

.tool-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  flex-wrap: wrap;
  gap: 8px;
}

.presets-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.btn-preset {
  font-size: 11px;
  font-weight: 600;
  padding: 4px 9px;
  border-radius: 8px;
  background: var(--surface, #1e2025);
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  color: var(--ink, #d1d5db);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-preset:hover {
  border-color: var(--pink-dark, #f8b4c4);
  color: var(--ink, #ffffff);
  transform: translateY(-1px);
}

.btn-preset-peak {
  border-color: rgba(248, 180, 196, 0.3);
  color: var(--pink-dark, #f8b4c4);
}

.btn-preset-weekend {
  border-color: rgba(52, 211, 153, 0.3);
  color: #34d399;
}

/* Bulk Form Grid */
.bulk-form-grid {
  display: grid;
  grid-template-columns: 1.3fr 1.2fr 1.7fr auto;
  gap: 12px;
  align-items: flex-end;
}

@media (max-width: 860px) {
  .bulk-form-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.bulk-form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.bulk-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted, #9ca3af);
}

.bulk-select,
.bulk-input-price {
  height: 36px;
  padding: 0 10px;
  border-radius: 10px;
  background: var(--surface, #1e2025);
  border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
  color: var(--ink, #ffffff);
  font-size: 12.5px;
}

.hour-range-inputs {
  display: flex;
  align-items: center;
  gap: 6px;
}

.bulk-select-sm {
  flex: 1;
  height: 36px;
  padding: 0 8px;
  border-radius: 10px;
  background: var(--surface, #1e2025);
  border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
  color: var(--ink, #ffffff);
  font-size: 12px;
}

.range-sep {
  font-weight: 700;
  color: var(--muted, #9ca3af);
}

.price-input-wrapper {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.quick-price-chips {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.price-chip {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 6px;
  background: var(--surface, #1e2025);
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  color: var(--muted, #9ca3af);
  cursor: pointer;
  transition: all 0.15s ease;
}

.price-chip:hover {
  color: var(--pink-dark, #f8b4c4);
  border-color: var(--pink-dark, #f8b4c4);
}

.btn-apply-bulk {
  height: 36px;
  padding: 0 16px;
  border-radius: 10px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  border: none;
  font-weight: 700;
  font-size: 12.5px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-apply-bulk:hover:not(:disabled) {
  filter: brightness(1.08);
  transform: translateY(-1px);
}

/* Hourly Matrix Section */
.hourly-matrix-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.matrix-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.matrix-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #ffffff);
  margin: 0;
}

.active-count-tag {
  font-size: 11px;
  font-weight: 600;
  color: #34d399;
  background: rgba(52, 211, 153, 0.12);
  padding: 2px 7px;
  border-radius: 6px;
}

.sessions-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.session-block {
  background: var(--surface-subtle, rgba(255, 255, 255, 0.02));
  border: 1px solid var(--border, rgba(255, 255, 255, 0.06));
  border-radius: 14px;
  padding: 12px 14px;
}

.peak-session-block {
  border-color: rgba(248, 180, 196, 0.25);
  background: linear-gradient(180deg, rgba(248, 180, 196, 0.04) 0%, var(--surface-subtle, rgba(255, 255, 255, 0.02)) 100%);
}

.session-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink, #ffffff);
  margin-bottom: 10px;
}

.session-icon {
  font-size: 14px;
}

.peak-tag {
  font-size: 9px;
  font-weight: 800;
  padding: 1px 5px;
  border-radius: 4px;
  background: var(--pink-dark, #f8b4c4);
  color: #3d2b31;
  letter-spacing: 0.5px;
  margin-left: 4px;
}

.hourly-slots-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 8px;
}

.hour-slot-card {
  background: var(--surface, #1e2025);
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  border-radius: 10px;
  padding: 8px 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  transition: all 0.15s ease;
}

.hour-slot-card:hover {
  border-color: var(--pink-dark, #f8b4c4);
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.peak-card {
  border-color: rgba(248, 180, 196, 0.2);
}

.hour-slot-card.editing-card {
  border-color: var(--pink-dark, #f8b4c4);
  background: rgba(248, 180, 196, 0.06);
}

.slot-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
}

.slot-weekday-tag {
  font-size: 9.5px;
  font-weight: 700;
  color: var(--pink-dark, #f8b4c4);
  background: rgba(248, 180, 196, 0.12);
  padding: 1px 4px;
  border-radius: 4px;
}

.slot-time-badge {
  font-size: 11px;
  font-weight: 700;
  color: var(--ink, #ffffff);
}

.peak-time-badge {
  color: var(--pink-dark, #f8b4c4);
}

.slot-card-body {
  cursor: pointer;
}

.price-display-box {
  display: flex;
  align-items: baseline;
  gap: 2px;
}

.price-num {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ink, #ffffff);
}

.price-unit {
  font-size: 10.5px;
  color: var(--muted, #9ca3af);
}

.slot-card-actions {
  display: flex;
  align-items: center;
  gap: 3px;
  margin-top: 4px;
}

.btn-mini-adjust {
  font-size: 9.5px;
  font-weight: 600;
  padding: 1px 4px;
  border-radius: 4px;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.05));
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  color: var(--muted, #9ca3af);
  cursor: pointer;
  transition: all 0.12s ease;
}

.btn-mini-adjust:hover {
  color: var(--ink, #ffffff);
  border-color: var(--pink-dark, #f8b4c4);
}

.btn-mini-del {
  border: none;
  background: transparent;
  color: var(--muted, #9ca3af);
  cursor: pointer;
  padding: 1px;
  display: flex;
  align-items: center;
  margin-left: auto;
}

.btn-mini-del:hover {
  color: #ef4444;
}

.inline-edit-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 2px;
}

.inline-price-input {
  height: 26px;
  padding: 0 6px;
  border-radius: 6px;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.08));
  border: 1px solid var(--pink-dark, #f8b4c4);
  font-size: 11.5px;
  font-weight: 700;
  color: var(--ink, #ffffff);
}

.inline-actions {
  display: flex;
  gap: 3px;
}

.btn-inline-save {
  flex: 1;
  height: 22px;
  background: #34d399;
  color: #064e3b;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-inline-cancel {
  height: 22px;
  width: 22px;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.05));
  color: var(--muted, #9ca3af);
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-slots-state {
  text-align: center;
  padding: 36px 20px;
  color: var(--muted, #9ca3af);
}

.empty-slots-state .empty-icon {
  font-size: 40px;
  opacity: 0.5;
  margin-bottom: 8px;
}

.pricing-modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 24px;
  border-top: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  background: var(--surface, #1e2025);
  gap: 12px;
}
</style>
