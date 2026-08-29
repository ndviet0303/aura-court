<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { api, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { SportType, SportZone, Venue } from '@/types'

const { t } = useI18n()
const auth = useAuthStore()

type VenueForm = { name: string; address: string; google_maps_url: string; is_active: boolean }

// Core State
const venues = ref<Venue[]>([])
const qrUrls = ref<Record<number, string>>({})
const form = ref<VenueForm>({ name: '', address: '', google_maps_url: '', is_active: true })
const editingId = ref<number | null>(null)
const showVenueModal = ref(false)
const error = ref('')
const notice = ref('')
const saving = ref(false)
const loading = ref(false)
const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email?.endsWith('@demo.auracourt.vn'))

// Search & Filter State
const searchQuery = ref('')
const statusFilter = ref<'all' | 'active' | 'inactive'>('all')
const sortBy = ref<'name' | 'courts' | 'status'>('name')

// QR Share Modal State
const showQrModal = ref(false)
const selectedVenueForQr = ref<Venue | null>(null)

// Sport Zones State
const showZonesModal = ref(false)
const selectedVenueForZones = ref<Venue | null>(null)
const zones = ref<SportZone[]>([])
const loadingZones = ref(false)
const showZoneEditModal = ref(false)
const editingZoneId = ref<number | null>(null)
const zoneForm = ref<{ name: string; sport_type: SportType; slot_duration: number; description: string; is_active: boolean }>({
  name: '',
  sport_type: 'badminton',
  slot_duration: 60,
  description: '',
  is_active: true,
})

const sportTypeOptions: { value: SportType; label: string; icon: string }[] = [
  { value: 'badminton', label: 'Cầu lông', icon: 'sports_tennis' },
  { value: 'pickleball', label: 'Pickleball', icon: 'sports_tennis' },
  { value: 'tennis', label: 'Tennis', icon: 'sports_tennis' },
  { value: 'football_5v5', label: 'Bóng đá 5 người', icon: 'sports_soccer' },
  { value: 'football_7v7', label: 'Bóng đá 7 người', icon: 'sports_soccer' },
  { value: 'basketball', label: 'Bóng rổ', icon: 'sports_basketball' },
  { value: 'volleyball', label: 'Bóng chuyền', icon: 'sports_volleyball' },
  { value: 'table_tennis', label: 'Bóng bàn', icon: 'table_restaurant' },
]

const slotDurationOptions = [
  { value: 30, label: '30 phút' },
  { value: 45, label: '45 phút' },
  { value: 60, label: '60 phút (1 giờ)' },
  { value: 90, label: '90 phút (1.5 giờ)' },
  { value: 120, label: '120 phút (2 giờ)' },
]

// Computed Metrics & Filtered Data
const totalCourtsCount = computed(() => {
  return venues.value.reduce((sum, v) => sum + (v.courts_count || 4), 0)
})

const activeVenuesCount = computed(() => {
  return venues.value.filter((v) => v.is_active).length
})

const inactiveVenuesCount = computed(() => {
  return venues.value.filter((v) => !v.is_active).length
})

const activePercentage = computed(() => {
  if (!venues.value.length) return 100
  return Math.round((activeVenuesCount.value / venues.value.length) * 100)
})

const filteredVenues = computed(() => {
  let list = [...venues.value]

  // Status filter
  if (statusFilter.value === 'active') {
    list = list.filter((v) => v.is_active)
  } else if (statusFilter.value === 'inactive') {
    list = list.filter((v) => !v.is_active)
  }

  // Search filter
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    list = list.filter(
      (v) =>
        v.name.toLowerCase().includes(q) ||
        (v.address && v.address.toLowerCase().includes(q)) ||
        (v.slug && v.slug.toLowerCase().includes(q))
    )
  }

  // Sorting
  list.sort((a, b) => {
    if (sortBy.value === 'courts') {
      return (b.courts_count || 4) - (a.courts_count || 4)
    }
    if (sortBy.value === 'status') {
      return Number(b.is_active) - Number(a.is_active)
    }
    return a.name.localeCompare(b.name, 'vi')
  })

  return list
})

function getVenueCover(venue: Venue, index: number): string {
  if (venue.image_url) return venue.image_url
  const defaultCovers = [
    '/aura/court-1.jpg',
    '/aura/court-2.jpg',
    '/aura/court-3.jpg',
    '/aura/court-4.jpg',
    '/aura/court-5.jpg',
    '/aura/court-6.jpg',
    '/aura/court-0.jpg',
  ]
  return defaultCovers[index % defaultCovers.length] || '/aura/court-1.jpg'
}

// QR Code helpers
async function makeQr(venue: Venue): Promise<void> {
  try {
    qrUrls.value[venue.id] = await QRCode.toDataURL(venue.share_url || `${window.location.origin}/venues/${venue.slug}/directions`, {
      width: 1024,
      errorCorrectionLevel: 'H',
      margin: 2,
      color: {
        dark: '#78555e',
        light: '#ffffff',
      },
    })
  } catch {
    // ignore qr generation error
  }
}

function openQrModal(venue: Venue) {
  selectedVenueForQr.value = venue
  showQrModal.value = true
}

function downloadQr(venue: Venue): void {
  const url = qrUrls.value[venue.id]
  if (!url) return
  const link = document.createElement('a')
  link.href = url
  link.download = `${venue.slug || 'venue'}-directions-qr.png`
  link.click()
  notice.value = `Đã tải ảnh mã QR của ${venue.name}.`
}

async function copyLink(venue: Venue): Promise<void> {
  const url = venue.share_url || `${window.location.origin}/venues/${venue.slug}/directions`
  await navigator.clipboard.writeText(url)
  notice.value = 'Đã sao chép link chỉ đường Google Maps & Aura vào clipboard.'
}

// Data fetching
async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    venues.value = await api.get<Venue[]>('/admin/venues')
    await Promise.all(venues.value.map(makeQr))
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không tải được danh sách cụm sân.')
  } finally {
    loading.value = false
  }
}

// Venue CRUD
function startCreate(): void {
  editingId.value = null
  form.value = { name: '', address: '', google_maps_url: '', is_active: true }
  showVenueModal.value = true
}

function startEdit(venue: Venue): void {
  editingId.value = venue.id
  form.value = {
    name: venue.name,
    address: venue.address || '',
    google_maps_url: venue.google_maps_url || '',
    is_active: venue.is_active,
  }
  showVenueModal.value = true
}

async function save(): Promise<void> {
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    if (isDemoAdmin.value) {
      if (editingId.value) {
        venues.value = venues.value.map((venue) => (venue.id === editingId.value ? { ...venue, ...form.value } : venue))
        notice.value = 'Đã mô phỏng cập nhật địa điểm (không ghi vào dữ liệu thật).'
      } else {
        const id = -Date.now()
        const slug = `${form.value.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'venue'}-demo`
        const venue = {
          ...form.value,
          id,
          slug,
          share_url: `${window.location.origin}/venues/${slug}/directions`,
          address: form.value.address,
          google_maps_url: form.value.google_maps_url,
          courts_count: 4,
        }
        venues.value = [...venues.value, venue]
        await makeQr(venue)
        notice.value = 'Đã mô phỏng tạo địa điểm (không ghi vào dữ liệu thật).'
      }
      showVenueModal.value = false
      return
    }

    if (editingId.value) {
      await api.patch(`/admin/venues/${editingId.value}`, form.value)
      notice.value = 'Đã cập nhật địa điểm thành công.'
    } else {
      await api.post('/admin/venues', form.value)
      notice.value = 'Đã tạo địa điểm mới thành công.'
    }
    showVenueModal.value = false
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể lưu địa điểm.')
  } finally {
    saving.value = false
  }
}

async function toggle(venue: Venue): Promise<void> {
  const active = !venue.is_active
  if (isDemoAdmin.value) {
    venue.is_active = active
    notice.value = active ? 'Đã mô phỏng mở lại địa điểm.' : 'Đã mô phỏng ngừng hoạt động địa điểm.'
    return
  }
  try {
    await api.patch(`/admin/venues/${venue.id}`, { is_active: active })
    notice.value = active ? 'Đã mở lại cụm sân.' : 'Đã chuyển cụm sân sang trạng thái bảo trì.'
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể đổi trạng thái địa điểm.')
  }
}

// Sport Zones
async function openZonesModal(venue: Venue) {
  selectedVenueForZones.value = venue
  showZonesModal.value = true
  await loadZones(venue.id)
}

async function loadZones(venueId: number) {
  loadingZones.value = true
  try {
    zones.value = await api.get<SportZone[]>(`/admin/sport-zones?venue_id=${venueId}`)
  } catch (cause) {
    error.value = errorMessage(cause, 'Không thể tải danh sách khu thể thao.')
  } finally {
    loadingZones.value = false
  }
}

function startCreateZone() {
  if (!selectedVenueForZones.value) return
  editingZoneId.value = null
  zoneForm.value = {
    name: 'Khu Sân Cầu Lông',
    sport_type: 'badminton',
    slot_duration: 60,
    description: '',
    is_active: true,
  }
  showZoneEditModal.value = true
}

function startEditZone(zone: SportZone) {
  editingZoneId.value = zone.id
  zoneForm.value = {
    name: zone.name,
    sport_type: zone.sport_type,
    slot_duration: zone.slot_duration,
    description: zone.description || '',
    is_active: zone.is_active,
  }
  showZoneEditModal.value = true
}

async function saveZone() {
  if (!selectedVenueForZones.value) return
  saving.value = true
  try {
    if (editingZoneId.value) {
      await api.patch(`/admin/sport-zones/${editingZoneId.value}`, zoneForm.value)
      notice.value = 'Đã cập nhật khu vực thể thao.'
    } else {
      await api.post('/admin/sport-zones', {
        ...zoneForm.value,
        venue_id: selectedVenueForZones.value.id,
      })
      notice.value = 'Đã thêm khu vực thể thao mới.'
    }
    showZoneEditModal.value = false
    await loadZones(selectedVenueForZones.value.id)
    await load()
  } catch (cause) {
    error.value = errorMessage(cause, 'Không thể lưu khu vực thể thao.')
  } finally {
    saving.value = false
  }
}

async function toggleZone(zone: SportZone) {
  try {
    await api.patch(`/admin/sport-zones/${zone.id}`, { is_active: !zone.is_active })
    zone.is_active = !zone.is_active
    notice.value = zone.is_active ? 'Đã mở lại khu vực thể thao.' : 'Đã tạm ngừng khu vực thể thao.'
  } catch (cause) {
    error.value = errorMessage(cause, 'Không thể đổi trạng thái khu vực.')
  }
}

onMounted(load)
</script>

<template>
  <div class="admin-venues-page">
    <div class="page-container">
      <!-- Page Header Row (matching Stitch Venues & Courts header) -->
      <header class="page-header-row">
        <div class="header-text-block">
          <div class="eyebrow-badge">
            <span class="material-symbols-outlined text-[16px]">location_on</span>
            <span>MANAGEMENT · VENUES & HUBS</span>
          </div>
          <h1 class="page-main-title">Quản lý Cụm sân & Cơ sở</h1>
          <p class="page-subtitle">
            Hệ thống quản lý cụm sân thể thao đa cơ sở, phân bổ sân thi đấu, liên kết chỉ đường Google Maps và bảng mã QR thông minh.
          </p>
        </div>

        <div class="header-action-group">
          <button
            type="button"
            class="btn-primary-add"
            @click="startCreate"
          >
            <span class="material-symbols-outlined text-[20px]">add</span>
            <span>Thêm Cụm Sân Mới</span>
          </button>
        </div>
      </header>

      <!-- KPI Summary Cards -->
      <section class="kpi-summary-grid">
        <div class="kpi-card">
          <div class="kpi-icon-box bg-pink-container text-on-pink">
            <span class="material-symbols-outlined text-[22px]">corporate_fare</span>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Tổng số cụm sân</span>
            <div class="kpi-val-row">
              <span class="kpi-number">{{ venues.length }}</span>
              <span class="kpi-tag">Cơ sở vận hành</span>
            </div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box bg-green-50 text-green-700">
            <span class="material-symbols-outlined text-[22px]">sports_tennis</span>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Tổng số sân thi đấu</span>
            <div class="kpi-val-row">
              <span class="kpi-number">{{ totalCourtsCount }}</span>
              <span class="kpi-tag text-green-700">Sẵn sàng phục vụ</span>
            </div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box bg-blue-50 text-blue-700">
            <span class="material-symbols-outlined text-[22px]">check_circle</span>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Tỷ lệ mở cửa đón khách</span>
            <div class="kpi-val-row">
              <span class="kpi-number">{{ activePercentage }}%</span>
              <span class="kpi-tag text-blue-700">{{ activeVenuesCount }}/{{ venues.length }} Hoạt động</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Notification Banners -->
      <transition name="fade">
        <div v-if="notice" class="alert-banner alert-success">
          <span class="material-symbols-outlined text-green-600">check_circle</span>
          <span class="flex-1">{{ notice }}</span>
          <button type="button" class="alert-close-btn" @click="notice = ''">
            <span class="material-symbols-outlined text-sm">close</span>
          </button>
        </div>
      </transition>

      <transition name="fade">
        <div v-if="error" class="alert-banner alert-danger">
          <span class="material-symbols-outlined text-red-600">error</span>
          <span class="flex-1">{{ error }}</span>
          <button type="button" class="alert-close-btn" @click="error = ''">
            <span class="material-symbols-outlined text-sm">close</span>
          </button>
        </div>
      </transition>

      <!-- Search & Filters Toolbar -->
      <section class="toolbar-section">
        <!-- Live Search Input -->
        <div class="search-input-wrap">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            class="search-input"
            placeholder="Tìm kiếm cụm sân theo tên hoặc địa chỉ chi tiết..."
          />
          <button
            v-if="searchQuery"
            type="button"
            class="clear-search-btn"
            title="Xóa tìm kiếm"
            @click="searchQuery = ''"
          >
            <span class="material-symbols-outlined text-sm">close</span>
          </button>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs-pills">
          <button
            type="button"
            class="filter-pill-btn"
            :class="{ active: statusFilter === 'all' }"
            @click="statusFilter = 'all'"
          >
            Tất cả ({{ venues.length }})
          </button>
          <button
            type="button"
            class="filter-pill-btn"
            :class="{ active: statusFilter === 'active' }"
            @click="statusFilter = 'active'"
          >
            <span class="status-dot-sm bg-emerald-500"></span>
            Đang hoạt động ({{ activeVenuesCount }})
          </button>
          <button
            type="button"
            class="filter-pill-btn"
            :class="{ active: statusFilter === 'inactive' }"
            @click="statusFilter = 'inactive'"
          >
            <span class="status-dot-sm bg-amber-500"></span>
            Bảo trì / Tạm dừng ({{ inactiveVenuesCount }})
          </button>
        </div>

        <!-- Sort dropdown -->
        <div class="sort-selector-wrap">
          <span class="material-symbols-outlined text-muted text-sm">sort</span>
          <select v-model="sortBy" class="sort-select">
            <option value="name">Tên cơ sở (A-Z)</option>
            <option value="courts">Số lượng sân (Nhiều nhất)</option>
            <option value="status">Trạng thái hoạt động</option>
          </select>
        </div>
      </section>

      <!-- Loading State -->
      <div v-if="loading" class="venues-grid-skeleton">
        <div v-for="n in 3" :key="n" class="skeleton-card">
          <div class="skeleton-img"></div>
          <div class="skeleton-body">
            <div class="skeleton-line line-title"></div>
            <div class="skeleton-line line-sub"></div>
            <div class="skeleton-line line-btn"></div>
          </div>
        </div>
      </div>

      <!-- Venues Grid (Pixel-Perfect Aura Court Stitch Design) -->
      <div v-else-if="filteredVenues.length" class="venues-cards-grid">
        <article
          v-for="(venue, index) in filteredVenues"
          :key="venue.id"
          class="venue-card group"
          :class="{ 'is-inactive': !venue.is_active }"
        >
          <!-- Card Top: Cover Photo & Switch -->
          <div class="venue-card-cover">
            <div
              class="cover-bg-image"
              :style="{ backgroundImage: `url('${getVenueCover(venue, index)}')` }"
            ></div>
            <div class="cover-gradient-overlay"></div>

            <!-- iOS-style Status Toggle Switch on Top Right -->
            <div class="cover-top-controls">
              <label
                class="switch-control-wrap"
                :title="venue.is_active ? 'Bấm để tạm ngừng cơ sở' : 'Bấm để mở lại cơ sở'"
                @click.stop
              >
                <input
                  type="checkbox"
                  class="sr-only peer"
                  :checked="venue.is_active"
                  @change="toggle(venue)"
                />
                <div class="switch-pill peer-checked:bg-primary"></div>
              </label>
            </div>

            <!-- Court count badge at bottom-left of image -->
            <div class="cover-bottom-pill">
              <span class="court-chip">
                <span class="material-symbols-outlined text-[14px]">sports_tennis</span>
                <span>{{ venue.courts_count || 4 }} Sân thi đấu</span>
              </span>
            </div>
          </div>

          <!-- Card Body: Overlapping Floating Glass Container -->
          <div class="venue-card-body">
            <!-- Status Row & Pulse Dot -->
            <div class="card-status-row">
              <div class="status-indicator" :class="venue.is_active ? 'is-active-dot' : 'is-maintenance-dot'">
                <span class="pulse-core"></span>
                <span class="status-text uppercase tracking-widest font-semibold">
                  {{ venue.is_active ? 'ACTIVE' : 'MAINTENANCE' }}
                </span>
              </div>

              <!-- Quick Edit Button -->
              <button
                type="button"
                class="btn-icon-soft"
                title="Chỉnh sửa thông tin"
                @click="startEdit(venue)"
              >
                <span class="material-symbols-outlined text-[18px]">edit</span>
              </button>
            </div>

            <!-- Venue Title & Address -->
            <div class="venue-info-block">
              <h2 class="venue-name" :title="venue.name">
                {{ venue.name }}
              </h2>
              <p class="venue-address" :title="venue.address || undefined">
                <span class="material-symbols-outlined location-icon">location_on</span>
                <span class="address-text">{{ venue.address || 'Chưa cập nhật địa chỉ' }}</span>
              </p>
            </div>

            <!-- Summary & Forward Navigation Row -->
            <div class="venue-stats-action-row">
              <div class="courts-count-column">
                <span class="count-number">{{ venue.courts_count || 4 }}</span>
                <span class="count-label">Courts · Sân đấu</span>
              </div>

              <!-- Circle Arrow Forward Link to Court Manager -->
              <RouterLink
                :to="`/admin/manage?venue_id=${venue.id}`"
                class="btn-circle-forward"
                title="Xem danh sách & quản lý sân cơ sở này"
              >
                <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
              </RouterLink>
            </div>

            <!-- Operational Sub-Actions Toolbar -->
            <div class="venue-toolbar-actions">
              <button
                type="button"
                class="subaction-btn"
                @click="openZonesModal(venue)"
              >
                <span class="material-symbols-outlined text-[16px]">category</span>
                <span>Khu thể thao</span>
              </button>

              <button
                type="button"
                class="subaction-btn"
                @click="openQrModal(venue)"
              >
                <span class="material-symbols-outlined text-[16px]">qr_code_2</span>
                <span>Mã QR & Bản đồ</span>
              </button>

              <a
                v-if="venue.google_maps_url"
                :href="venue.google_maps_url"
                target="_blank"
                rel="noopener noreferrer"
                class="subaction-btn"
                title="Mở trên ứng dụng Google Maps"
              >
                <span class="material-symbols-outlined text-[16px]">map</span>
                <span>Chỉ đường</span>
              </a>
            </div>
          </div>
        </article>
      </div>

      <!-- Empty State -->
      <div v-else class="empty-results-card">
        <div class="empty-icon-circle">
          <span class="material-symbols-outlined text-[36px]">location_off</span>
        </div>
        <h3 class="empty-title">Không tìm thấy cụm sân phù hợp</h3>
        <p class="empty-desc">
          Không có cơ sở nào khớp với từ khóa "{{ searchQuery }}" hoặc bộ lọc đã chọn. Hãy thử tìm kiếm với từ khóa khác hoặc đặt lại bộ lọc.
        </p>
        <div class="empty-actions">
          <button
            type="button"
            class="btn-secondary-reset"
            @click="searchQuery = ''; statusFilter = 'all'"
          >
            Đặt lại bộ lọc
          </button>
          <button
            type="button"
            class="btn-primary-create"
            @click="startCreate"
          >
            <span class="material-symbols-outlined text-[18px]">add</span>
            <span>Thêm Cụm Sân Mới</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL: CREATE / EDIT VENUE ================= -->
    <div v-if="showVenueModal" class="modal-backdrop" @click.self="showVenueModal = false">
      <div class="aura-modal-box">
        <!-- Header -->
        <div class="modal-header">
          <div class="modal-header-left">
            <div class="modal-icon-badge">
              <span class="material-symbols-outlined">domain</span>
            </div>
            <div>
              <h3 class="modal-title">{{ editingId ? 'Cập nhật Cụm Sân' : 'Thêm Cụm Sân Mới' }}</h3>
              <p class="modal-subtitle">Quản lý thông tin chi nhánh, địa chỉ và liên kết định vị Google Maps</p>
            </div>
          </div>
          <button
            type="button"
            class="btn-modal-close"
            aria-label="Đóng"
            @click="showVenueModal = false"
          >
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <!-- Form -->
        <form @submit.prevent="save">
          <div class="modal-body">
            <!-- Venue Name -->
            <div class="form-group">
              <label class="form-label">
                <span>Tên Cụm Sân / Chi nhánh</span>
                <span class="text-error">*</span>
              </label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">sports_tennis</span>
                <input
                  v-model="form.name"
                  type="text"
                  class="aura-form-input"
                  placeholder="Ví dụ: Aura Center - Chi nhánh Quận 7"
                  required
                />
              </div>
            </div>

            <!-- Address -->
            <div class="form-group">
              <label class="form-label">
                <span>Địa chỉ chi tiết</span>
                <span class="text-error">*</span>
              </label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">pin_drop</span>
                <input
                  v-model="form.address"
                  type="text"
                  class="aura-form-input"
                  placeholder="Ví dụ: 142 Wellness Blvd, Tân Phong, Quận 7, TP.HCM"
                  required
                />
              </div>
            </div>

            <!-- Google Maps URL -->
            <div class="form-group">
              <label class="form-label">
                <span>Liên kết Google Maps</span>
                <span class="text-error">*</span>
              </label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">link</span>
                <input
                  v-model="form.google_maps_url"
                  type="url"
                  class="aura-form-input"
                  placeholder="https://maps.app.goo.gl/... hoặc https://google.com/maps..."
                  required
                />
              </div>
              <p class="form-tip">
                Dán link chia sẻ Google Maps để tự động tạo mã QR và hỗ trợ chỉ đường một chạm cho người chơi.
              </p>
            </div>

            <!-- Status Toggle -->
            <div class="toggle-row-box">
              <div class="toggle-info">
                <span class="toggle-title">Mở cửa hoạt động</span>
                <span class="toggle-sub">Cho phép người chơi tìm thấy cơ sở và đặt sân trực tuyến.</span>
              </div>
              <label class="switch-control-wrap">
                <input v-model="form.is_active" type="checkbox" class="sr-only peer" />
                <div class="switch-pill peer-checked:bg-primary"></div>
              </label>
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <button
              type="button"
              class="btn-cancel"
              @click="showVenueModal = false"
            >
              Hủy bỏ
            </button>
            <button
              type="submit"
              class="btn-submit"
              :disabled="saving"
            >
              <span v-if="saving" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
              <span>{{ saving ? 'Đang lưu...' : (editingId ? 'Cập nhật cơ sở' : 'Khởi tạo cơ sở') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= MODAL: SPORT ZONES MANAGER ================= -->
    <div v-if="showZonesModal" class="modal-backdrop" @click.self="showZonesModal = false">
      <div class="aura-modal-box max-w-2xl">
        <div class="modal-header">
          <div class="modal-header-left">
            <div class="modal-icon-badge">
              <span class="material-symbols-outlined">category</span>
            </div>
            <div>
              <h3 class="modal-title">Phân khu môn thể thao · {{ selectedVenueForZones?.name }}</h3>
              <p class="modal-subtitle">Cấu hình các bộ môn và khung giờ slot chơi tại cơ sở này</p>
            </div>
          </div>
          <button
            type="button"
            class="btn-modal-close"
            aria-label="Đóng"
            @click="showZonesModal = false"
          >
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-semibold text-on-surface">Danh sách phân khu ({{ zones.length }})</span>
            <button
              type="button"
              class="btn-primary-add text-xs py-2 px-3"
              @click="startCreateZone"
            >
              <span class="material-symbols-outlined text-[16px]">add</span>
              <span>Thêm phân khu mới</span>
            </button>
          </div>

          <div v-if="loadingZones" class="py-12 text-center text-muted">
            <span class="material-symbols-outlined animate-spin text-3xl">progress_activity</span>
            <p class="text-sm mt-2">Đang tải danh sách phân khu...</p>
          </div>

          <div v-else-if="!zones.length" class="empty-zones-box">
            <span class="material-symbols-outlined text-4xl text-muted">sports_soccer</span>
            <p class="text-sm mt-2 text-on-surface-variant">Chưa có phân khu thể thao nào được thiết lập.</p>
            <button
              type="button"
              class="btn-secondary-reset mt-3"
              @click="startCreateZone"
            >
              Tạo phân khu đầu tiên
            </button>
          </div>

          <div v-else class="zones-list-stack">
            <div
              v-for="zone in zones"
              :key="zone.id"
              class="zone-row-card"
              :class="{ 'opacity-60': !zone.is_active }"
            >
              <div class="zone-row-left">
                <div class="zone-sport-icon">
                  <span class="material-symbols-outlined">
                    {{ sportTypeOptions.find((o) => o.value === zone.sport_type)?.icon || 'sports' }}
                  </span>
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-sm text-on-surface">{{ zone.name }}</span>
                    <span
                      class="status-tag-pill"
                      :class="zone.is_active ? 'tag-active' : 'tag-inactive'"
                    >
                      {{ zone.is_active ? 'Đang mở' : 'Tạm dừng' }}
                    </span>
                  </div>
                  <div class="zone-meta-line">
                    <span>Môn: {{ sportTypeOptions.find((o) => o.value === zone.sport_type)?.label || zone.sport_type }}</span>
                    <span>•</span>
                    <span>Khung: {{ zone.slot_duration }} phút</span>
                    <span>•</span>
                    <span>{{ zone.courts_count || 0 }} sân thi đấu</span>
                  </div>
                </div>
              </div>

              <div class="zone-row-actions">
                <button
                  type="button"
                  class="btn-icon-action"
                  :title="zone.is_active ? 'Tạm dừng phân khu' : 'Mở lại phân khu'"
                  @click="toggleZone(zone)"
                >
                  <span class="material-symbols-outlined text-[18px]">
                    {{ zone.is_active ? 'pause' : 'play_arrow' }}
                  </span>
                </button>
                <button
                  type="button"
                  class="btn-icon-action"
                  title="Chỉnh sửa phân khu"
                  @click="startEditZone(zone)"
                >
                  <span class="material-symbols-outlined text-[18px]">edit</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button
            type="button"
            class="btn-cancel"
            @click="showZonesModal = false"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL: EDIT SPORT ZONE ================= -->
    <div v-if="showZoneEditModal" class="modal-backdrop" @click.self="showZoneEditModal = false">
      <div class="aura-modal-box max-w-lg">
        <div class="modal-header">
          <div class="modal-header-left">
            <div class="modal-icon-badge">
              <span class="material-symbols-outlined">edit</span>
            </div>
            <div>
              <h3 class="modal-title">{{ editingZoneId ? 'Chỉnh sửa Phân khu' : 'Tạo Phân khu Thể thao mới' }}</h3>
              <p class="modal-subtitle">{{ selectedVenueForZones?.name }}</p>
            </div>
          </div>
          <button
            type="button"
            class="btn-modal-close"
            aria-label="Đóng"
            @click="showZoneEditModal = false"
          >
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form @submit.prevent="saveZone">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">
                <span>Tên phân khu</span>
                <span class="text-error">*</span>
              </label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">badge</span>
                <input
                  v-model="zoneForm.name"
                  type="text"
                  class="aura-form-input"
                  placeholder="Ví dụ: Khu Cầu Lông Tầng 2, Sân Bóng Mini..."
                  required
                />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">
                <span>Bộ môn thể thao</span>
                <span class="text-error">*</span>
              </label>
              <select v-model="zoneForm.sport_type" class="aura-form-input" required>
                <option v-for="opt in sportTypeOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">
                <span>Thời lượng khung giờ (Slot Duration)</span>
                <span class="text-error">*</span>
              </label>
              <select v-model="zoneForm.slot_duration" class="aura-form-input" required>
                <option v-for="opt in slotDurationOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">
                <span>Mô tả chi tiết</span>
              </label>
              <input
                v-model="zoneForm.description"
                type="text"
                class="aura-form-input"
                placeholder="Ví dụ: Cụm 4 sân sàn gỗ chuẩn BWF, đèn LED chống chói"
              />
            </div>

            <div class="toggle-row-box">
              <div class="toggle-info">
                <span class="toggle-title">Mở phân khu này</span>
              </div>
              <label class="switch-control-wrap">
                <input v-model="zoneForm.is_active" type="checkbox" class="sr-only peer" />
                <div class="switch-pill peer-checked:bg-primary"></div>
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn-cancel"
              @click="showZoneEditModal = false"
            >
              Hủy
            </button>
            <button
              type="submit"
              class="btn-submit"
              :disabled="saving"
            >
              <span>{{ saving ? 'Đang lưu...' : 'Lưu phân khu' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= MODAL: QR CODE & DIRECTIONS SHARE ================= -->
    <div v-if="showQrModal && selectedVenueForQr" class="modal-backdrop" @click.self="showQrModal = false">
      <div class="aura-modal-box max-w-md text-center">
        <div class="modal-header justify-between">
          <div class="text-left">
            <h3 class="modal-title">Mã QR Chỉ Đường</h3>
            <p class="modal-subtitle">{{ selectedVenueForQr.name }}</p>
          </div>
          <button
            type="button"
            class="btn-modal-close"
            aria-label="Đóng"
            @click="showQrModal = false"
          >
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body items-center py-4">
          <!-- QR Card Container -->
          <div class="qr-display-frame">
            <img
              v-if="qrUrls[selectedVenueForQr.id]"
              :src="qrUrls[selectedVenueForQr.id]"
              :alt="`QR ${selectedVenueForQr.name}`"
              class="w-56 h-56 object-contain rounded-xl"
            />
            <div v-else class="w-56 h-56 flex items-center justify-center text-muted">
              <span class="material-symbols-outlined text-4xl animate-spin">progress_activity</span>
            </div>
          </div>

          <p class="text-xs text-on-surface-variant max-w-xs mt-3">
            Người chơi quét mã này bằng Camera điện thoại để mở ngay định vị Google Maps và trang chi tiết của {{ selectedVenueForQr.name }}.
          </p>

          <!-- Action buttons -->
          <div class="flex items-center justify-center gap-3 w-full mt-4">
            <button
              type="button"
              class="btn-qr-pill"
              :disabled="!qrUrls[selectedVenueForQr.id]"
              @click="downloadQr(selectedVenueForQr)"
            >
              <span class="material-symbols-outlined text-[16px]">download</span>
              <span>Tải ảnh PNG</span>
            </button>

            <button
              type="button"
              class="btn-qr-pill"
              @click="copyLink(selectedVenueForQr)"
            >
              <span class="material-symbols-outlined text-[16px]">content_copy</span>
              <span>Sao chép Link</span>
            </button>
          </div>

          <a
            v-if="selectedVenueForQr.google_maps_url"
            :href="selectedVenueForQr.google_maps_url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1.5 text-xs text-primary font-semibold hover:underline mt-4"
          >
            <span class="material-symbols-outlined text-[16px]">map</span>
            <span>Mở trực tiếp trên Google Maps</span>
          </a>
        </div>

        <div class="modal-footer justify-center">
          <button
            type="button"
            class="btn-cancel w-full"
            @click="showQrModal = false"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Base Layout & Page */
.admin-venues-page {
  min-height: 100vh;
  background: var(--bg, #f8f9fa);
  color: var(--ink, #191c1d);
  padding-bottom: 80px;
}

.page-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 32px 24px;
}

/* Page Header */
.page-header-row {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-bottom: 28px;
}

@media (min-width: 768px) {
  .page-header-row {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
  }
}

.eyebrow-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.page-main-title {
  font-family: 'Inter', sans-serif;
  font-size: 32px;
  font-weight: 700;
  line-height: 1.2;
  color: var(--ink, #191c1d);
  letter-spacing: -0.02em;
  margin: 0 0 6px;
}

.page-subtitle {
  font-size: 14.5px;
  color: var(--muted, #5d5f5f);
  max-width: 680px;
  margin: 0;
  line-height: 1.5;
}

.header-action-group {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.btn-primary-add {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  border-radius: 9999px;
  background: var(--primary, #78555e);
  color: var(--on-primary, #ffffff);
  font-size: 13.5px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
  transition: all 0.25s ease;
}

.btn-primary-add:hover {
  filter: brightness(1.1);
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(120, 85, 94, 0.35);
}

/* KPI Summary Cards */
.kpi-summary-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
  margin-bottom: 28px;
}

@media (min-width: 768px) {
  .kpi-summary-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.kpi-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 18px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.kpi-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.bg-pink-container {
  background: var(--primary-container, #ffd1dc);
}

.text-on-pink {
  color: var(--on-primary-container, #7a5761);
}

.kpi-info {
  display: flex;
  flex-direction: column;
}

.kpi-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.kpi-val-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-top: 2px;
}

.kpi-number {
  font-size: 22px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  line-height: 1;
}

.kpi-tag {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
}

/* Notification Banners */
.alert-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-radius: 14px;
  margin-bottom: 24px;
  font-size: 13.5px;
  font-weight: 500;
}

.alert-success {
  background: var(--success-bg, rgba(16, 185, 129, 0.12));
  color: var(--success-text, #10b981);
  border: 1px solid var(--success-bg, rgba(16, 185, 129, 0.3));
}

.alert-danger {
  background: var(--error-bg, rgba(239, 68, 68, 0.12));
  color: var(--error-text, #f87171);
  border: 1px solid var(--error-bg, rgba(239, 68, 68, 0.3));
}

.alert-close-btn {
  background: transparent;
  border: none;
  color: inherit;
  cursor: pointer;
  padding: 2px;
  display: flex;
  align-items: center;
}

/* Toolbar & Filters */
.toolbar-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 28px;
}

@media (min-width: 960px) {
  .toolbar-section {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.search-input-wrap {
  position: relative;
  flex: 1;
  max-width: 480px;
}

.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted, #5d5f5f);
  font-size: 20px;
}

.search-input {
  width: 100%;
  padding: 10px 38px 10px 42px;
  border-radius: 9999px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 13.5px;
  outline: none;
  transition: all 0.2s ease;
}

.search-input:focus {
  border-color: var(--primary, #78555e);
  box-shadow: 0 0 0 3px rgba(120, 85, 94, 0.18);
}

.clear-search-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: var(--muted, #5d5f5f);
  cursor: pointer;
}

.filter-tabs-pills {
  display: inline-flex;
  gap: 6px;
  background: var(--surface-container, #edeeef);
  padding: 4px;
  border-radius: 9999px;
}

.filter-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 9999px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.filter-pill-btn.active {
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

.status-dot-sm {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.sort-selector-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 9999px;
  padding: 4px 14px;
}

.sort-select {
  border: none;
  background: transparent;
  color: var(--ink, #191c1d);
  font-size: 12.5px;
  font-weight: 500;
  outline: none;
  cursor: pointer;
}

/* Venues Grid Layout */
.venues-cards-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 768px) {
  .venues-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1100px) {
  .venues-cards-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

/* Venue Card */
.venue-card {
  position: relative;
  border-radius: 24px;
  overflow: hidden;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
  display: flex;
  flex-direction: column;
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.venue-card:hover {
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.12);
  transform: translateY(-4px);
}

.venue-card.is-inactive {
  opacity: 0.85;
}

/* Cover Image Section */
.venue-card-cover {
  position: relative;
  height: 200px;
  overflow: hidden;
}

.cover-bg-image {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  transition: transform 1s cubic-bezier(0.16, 1, 0.3, 1);
}

.venue-card:hover .cover-bg-image {
  transform: scale(1.06);
}

.venue-card.is-inactive .cover-bg-image {
  filter: grayscale(80%);
}

.cover-gradient-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, var(--surface, #ffffff) 0%, rgba(0, 0, 0, 0.05) 50%, rgba(0, 0, 0, 0.4) 100%);
}

.cover-top-controls {
  position: absolute;
  top: 14px;
  right: 14px;
  z-index: 10;
}

/* Custom iOS-like switch pill */
.switch-control-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
}

.switch-pill {
  width: 44px;
  height: 24px;
  background: rgba(100, 116, 139, 0.85);
  backdrop-filter: blur(8px);
  border-radius: 9999px;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
}

.switch-pill::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  background: #ffffff;
  border-radius: 9999px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.switch-control-wrap input:checked + .switch-pill {
  background: var(--primary, #78555e);
}

.switch-control-wrap input:checked + .switch-pill::after {
  transform: translateX(20px);
}

.cover-bottom-pill {
  position: absolute;
  bottom: 44px;
  left: 16px;
  z-index: 10;
}

.court-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 9999px;
  background: rgba(25, 28, 29, 0.85);
  backdrop-filter: blur(8px);
  color: #ffffff;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.02em;
}

/* Overlapping Card Body */
.venue-card-body {
  position: relative;
  z-index: 10;
  margin-top: -32px;
  background: var(--surface, #ffffff);
  border-top-left-radius: 24px;
  border-top-right-radius: 24px;
  border-top: 1px solid var(--border, #e0e4ea);
  padding: 20px;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.card-status-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.status-indicator {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
}

.is-active-dot .pulse-core {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6);
  animation: pulse-ring 2s infinite ease-out;
}

.is-active-dot .status-text {
  color: #10b981;
}

.is-maintenance-dot .pulse-core {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #64748b;
}

.is-maintenance-dot .status-text {
  color: #64748b;
}

@keyframes pulse-ring {
  0% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6);
  }
  70% {
    box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
  }
}

.btn-icon-soft {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--muted, #5d5f5f);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-icon-soft:hover {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  border-color: var(--primary, #78555e);
}

.venue-info-block {
  margin-bottom: 18px;
}

.venue-name {
  font-family: 'Inter', sans-serif;
  font-size: 20px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  line-height: 1.3;
  margin: 0 0 6px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.venue-address {
  font-size: 13px;
  color: var(--muted, #5d5f5f);
  margin: 0;
  display: flex;
  align-items: flex-start;
  gap: 5px;
  line-height: 1.4;
}

.location-icon {
  font-size: 16px;
  color: var(--primary, #78555e);
  flex-shrink: 0;
  margin-top: 1px;
}

.address-text {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Stats & Action Row */
.venue-stats-action-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 0 12px;
  border-top: 1px solid var(--border, #e0e4ea);
}

.courts-count-column {
  display: flex;
  flex-direction: column;
}

.count-number {
  font-family: 'Inter', sans-serif;
  font-size: 24px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  line-height: 1;
}

.count-label {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-top: 2px;
}

.btn-circle-forward {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.btn-circle-forward:hover {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  transform: scale(1.08) translateX(2px);
  border-color: var(--primary, #78555e);
}

/* Subaction Toolbar */
.venue-toolbar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--border, #e0e4ea);
}

.subaction-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 6px 10px;
  border-radius: 10px;
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.subaction-btn:hover {
  background: var(--surface-container, #edeeef);
  color: var(--primary, #78555e);
  border-color: var(--primary, #78555e);
}

/* Empty State */
.empty-results-card {
  padding: 60px 24px;
  text-align: center;
  background: var(--surface, #ffffff);
  border: 1px dashed var(--border, #e0e4ea);
  border-radius: 24px;
  max-width: 600px;
  margin: 40px auto;
}

.empty-icon-circle {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: var(--surface-container, #edeeef);
  color: var(--muted, #5d5f5f);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
}

.empty-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0 0 6px;
}

.empty-desc {
  font-size: 13.5px;
  color: var(--muted, #5d5f5f);
  line-height: 1.5;
  margin: 0 0 20px;
}

.empty-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.btn-secondary-reset {
  padding: 8px 18px;
  border-radius: 9999px;
  border: 1px solid var(--border, #e0e4ea);
  background: transparent;
  color: var(--ink, #191c1d);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-primary-create {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 20px;
  border-radius: 9999px;
  background: var(--primary, #78555e);
  color: var(--on-primary, #ffffff);
  font-size: 13px;
  font-weight: 600;
  border: none;
  cursor: pointer;
}

/* Skeletons */
.venues-grid-skeleton {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 768px) {
  .venues-grid-skeleton {
    grid-template-columns: repeat(3, 1fr);
  }
}

.skeleton-card {
  height: 380px;
  border-radius: 24px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  overflow: hidden;
  animation: pulse 1.5s infinite;
}

.skeleton-img {
  height: 180px;
  background: var(--surface-container, #edeeef);
}

.skeleton-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-line {
  height: 16px;
  background: var(--surface-container, #edeeef);
  border-radius: 8px;
}

.line-title {
  width: 70%;
  height: 22px;
}

.line-sub {
  width: 90%;
}

.line-btn {
  width: 40%;
  margin-top: auto;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.aura-modal-box {
  width: 100%;
  max-width: 560px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 24px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modal-pop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modal-pop {
  from {
    opacity: 0;
    transform: scale(0.96) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.modal-icon-badge {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.modal-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.modal-subtitle {
  font-size: 12.5px;
  color: var(--muted, #5d5f5f);
  margin: 2px 0 0;
}

.btn-modal-close {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: none;
  background: var(--surface-subtle, #f5f6f8);
  color: var(--muted, #5d5f5f);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.btn-modal-close:hover {
  background: var(--surface-container, #edeeef);
  color: var(--ink, #191c1d);
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  max-height: 75vh;
  overflow-y: auto;
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
  display: flex;
  align-items: center;
  gap: 4px;
}

.text-error {
  color: var(--error-text, #c62828);
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 14px;
  color: var(--muted, #5d5f5f);
  font-size: 20px;
}

.aura-form-input {
  width: 100%;
  padding: 10px 14px 10px 42px;
  border-radius: 12px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  font-size: 13.5px;
  outline: none;
  transition: all 0.2s ease;
}

select.aura-form-input {
  padding-left: 14px;
}

.aura-form-input:focus {
  border-color: var(--primary, #78555e);
  box-shadow: 0 0 0 3px rgba(120, 85, 94, 0.15);
}

.form-tip {
  font-size: 11.5px;
  color: var(--muted, #5d5f5f);
  margin: 2px 0 0;
}

.toggle-row-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-radius: 14px;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
}

.toggle-title {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  display: block;
}

.toggle-sub {
  font-size: 11.5px;
  color: var(--muted, #5d5f5f);
  display: block;
  margin-top: 1px;
}

.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
}

.btn-cancel {
  padding: 9px 20px;
  border-radius: 9999px;
  border: 1px solid var(--border, #e0e4ea);
  background: transparent;
  color: var(--ink, #191c1d);
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
}

.btn-submit {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 24px;
  border-radius: 9999px;
  border: none;
  background: var(--primary, #78555e);
  color: var(--on-primary, #ffffff);
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.2);
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* QR Frame & Actions */
.qr-display-frame {
  padding: 16px;
  background: #ffffff;
  border-radius: 20px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  display: inline-block;
  margin: 0 auto;
}

.btn-qr-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  border-radius: 9999px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-qr-pill:hover {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  border-color: var(--primary, #78555e);
}

/* Sport Zones List in Modal */
.zones-list-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.zone-row-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-radius: 16px;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
}

.zone-row-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.zone-sport-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}

.status-tag-pill {
  font-size: 10.5px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 9999px;
}

.tag-active {
  background: var(--success-bg, rgba(16, 185, 129, 0.15));
  color: var(--success-text, #10b981);
}

.tag-inactive {
  background: rgba(100, 116, 139, 0.15);
  color: var(--muted, #9ca3af);
}

.zone-meta-line {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--muted, #5d5f5f);
  margin-top: 2px;
}

.zone-row-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-icon-action {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  color: var(--muted, #5d5f5f);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.btn-icon-action:hover {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  border-color: var(--primary, #78555e);
}

.empty-zones-box {
  padding: 32px 16px;
  text-align: center;
  border: 1px dashed var(--border, #e0e4ea);
  border-radius: 16px;
}
</style>
