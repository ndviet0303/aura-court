<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { api, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Venue } from '@/types'

const { t } = useI18n()
const auth = useAuthStore()

type VenueForm = { name: string; address: string; google_maps_url: string; is_active: boolean }
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

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    venues.value = await api.get<Venue[]>('/admin/venues')
    await Promise.all(venues.value.map(makeQr))
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không tải được danh sách địa điểm.')
  } finally {
    loading.value = false
  }
}

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
    notice.value = active ? 'Đã mở lại địa điểm.' : 'Đã ngừng hoạt động địa điểm.'
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể đổi trạng thái địa điểm.')
  }
}

async function copyLink(venue: Venue): Promise<void> {
  const url = venue.share_url || `${window.location.origin}/venues/${venue.slug}/directions`
  await navigator.clipboard.writeText(url)
  notice.value = 'Đã sao chép link chỉ đường Google Maps & Aura.'
}

function downloadQr(venue: Venue): void {
  const url = qrUrls.value[venue.id]
  if (!url) return
  const link = document.createElement('a')
  link.href = url
  link.download = `${venue.slug}-directions-qr.png`
  link.click()
}

onMounted(load)
</script>

<template>
  <div class="admin-venues-content-wrap">
    <!-- Header Section -->
    <header class="admin-venues-header">
      <div class="header-content">
        <div class="eyebrow-tag">
          <span class="material-symbols-outlined icon-16">location_on</span>
          <span>ĐỊA ĐIỂM · VENUES & HUBS</span>
        </div>
        <h1 class="page-title">Quản lý Cụm sân & Chi nhánh</h1>
        <p class="muted max-w-2xl">
          Quản lý danh sách các cơ sở, địa chỉ, vị trí Google Maps và mã QR chỉ đường dành cho người chơi.
        </p>
      </div>

      <div class="header-actions">
        <button
          type="button"
          class="btn btn-primary flex items-center gap-xs"
          @click="startCreate"
        >
          <span class="material-symbols-outlined icon-20">add</span>
          <span>Thêm địa điểm</span>
        </button>
      </div>
    </header>

    <!-- Notifications -->
    <div v-if="notice" class="notice-banner">
      <span class="material-symbols-outlined text-green">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="error-banner">
      <span class="material-symbols-outlined text-error">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- Venues Cards Grid -->
    <section class="venues-cards-grid">
      <article
        v-for="venue in venues"
        :key="venue.id"
        class="venue-card"
        :class="{ 'is-inactive': !venue.is_active }"
      >
        <!-- Card Cover / Thumbnail -->
        <div class="venue-card-cover">
          <img
            src="/aura/court-1.jpg"
            :alt="venue.name"
            class="venue-cover-img"
          />
          <div class="venue-overlay"></div>

          <!-- Status badge top right -->
          <div class="venue-status-badge-wrap">
            <button
              type="button"
              class="status-toggle-pill"
              :class="venue.is_active ? 'pill-active' : 'pill-inactive'"
              @click="toggle(venue)"
            >
              <span class="pulse-dot"></span>
              <span>{{ venue.is_active ? 'Đang hoạt động' : 'Tạm ngừng' }}</span>
            </button>
          </div>

          <div class="venue-cover-bottom">
            <span class="court-count-pill">
              <span class="material-symbols-outlined icon-14">sports_tennis</span>
              {{ venue.courts_count || 4 }} Sân thi đấu
            </span>
          </div>
        </div>

        <!-- Venue Details Body -->
        <div class="venue-card-body">
          <h2 class="venue-title">{{ venue.name }}</h2>
          <p class="venue-address">
            <span class="material-symbols-outlined icon-16">pin_drop</span>
            <span>{{ venue.address || 'Chưa cập nhật địa chỉ' }}</span>
          </p>

          <!-- QR & Directions Box -->
          <div class="qr-share-box">
            <div class="qr-preview-box">
              <img
                v-if="qrUrls[venue.id]"
                :src="qrUrls[venue.id]"
                :alt="`QR ${venue.name}`"
                class="qr-img"
              />
              <span v-else class="material-symbols-outlined icon-24 text-muted">qr_code_2</span>
            </div>

            <div class="qr-info-col">
              <span class="font-semibold text-xs text-ink">Mã QR Chỉ đường</span>
              <p class="text-xs text-muted">Quét nhanh để mở chỉ đường Google Maps</p>
              <div class="flex items-center gap-2 mt-1">
                <button
                  type="button"
                  class="btn-qr-action"
                  :disabled="!qrUrls[venue.id]"
                  @click="downloadQr(venue)"
                >
                  <span class="material-symbols-outlined icon-14">download</span>
                  Tải PNG
                </button>
                <button
                  type="button"
                  class="btn-qr-action"
                  @click="copyLink(venue)"
                >
                  <span class="material-symbols-outlined icon-14">content_copy</span>
                  Copy link
                </button>
              </div>
            </div>
          </div>

          <!-- Actions Toolbar -->
          <div class="venue-actions-toolbar">
            <a
              v-if="venue.google_maps_url"
              :href="venue.google_maps_url"
              target="_blank"
              rel="noopener noreferrer"
              class="card-action-btn secondary-action"
            >
              <span class="material-symbols-outlined icon-16">map</span>
              <span>Google Maps</span>
            </a>

            <button
              type="button"
              class="card-action-btn primary-action"
              @click="startEdit(venue)"
            >
              <span class="material-symbols-outlined icon-16">edit</span>
              <span>Chỉnh sửa</span>
            </button>
          </div>
        </div>
      </article>
    </section>

    <!-- MODAL: Create / Edit Venue -->
    <div v-if="showVenueModal" class="modal-backdrop" @click.self="showVenueModal = false">
      <div class="aura-venue-modal">
        <!-- Header -->
        <div class="aura-modal-header">
          <div class="aura-header-left">
            <div class="aura-icon-box">
              <span class="material-symbols-outlined">location_on</span>
            </div>
            <div>
              <h3 class="aura-modal-title">{{ editingId ? 'Cập nhật địa điểm' : 'Thêm địa điểm mới' }}</h3>
              <p class="aura-modal-sub">Quản lý tên cụm sân, địa chỉ và liên kết Google Maps</p>
            </div>
          </div>
          <button type="button" class="aura-btn-close" aria-label="Đóng" @click="showVenueModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="aura-modal-body" @submit.prevent="save">
          <div class="aura-field">
            <label class="aura-label">Tên địa điểm / Chi nhánh <span class="text-pink">*</span></label>
            <input
              v-model="form.name"
              type="text"
              class="aura-input"
              placeholder="VD: Aura Center Thảo Điền"
              required
              maxlength="120"
            />
          </div>

          <div class="aura-field">
            <label class="aura-label">Địa chỉ chi tiết <span class="text-pink">*</span></label>
            <input
              v-model="form.address"
              type="text"
              class="aura-input"
              placeholder="VD: 12 Quốc Hương, Thảo Điền, TP. Thủ Đức"
              required
              maxlength="255"
            />
          </div>

          <div class="aura-field">
            <label class="aura-label">Link Google Maps (HTTPS) <span class="text-pink">*</span></label>
            <input
              v-model="form.google_maps_url"
              type="url"
              class="aura-input"
              placeholder="https://maps.google.com/?q=..."
              required
            />
          </div>

          <!-- Status Box -->
          <div class="aura-status-box">
            <div class="aura-status-text">
              <span class="aura-status-name">Cho phép địa điểm hoạt động công khai</span>
              <span class="aura-status-desc">Hiển thị cụm sân trên ứng dụng để khách hàng tìm kiếm</span>
            </div>
            <label class="aura-switch">
              <input v-model="form.is_active" type="checkbox" />
              <span class="aura-slider"></span>
            </label>
          </div>

          <!-- Footer -->
          <div class="aura-modal-footer">
            <button
              type="button"
              class="aura-btn-secondary"
              @click="showVenueModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="submit"
              class="aura-btn-primary"
              :disabled="saving"
            >
              <span v-if="saving" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
              <span>{{ saving ? t('common.submitting') : (editingId ? 'Cập nhật địa điểm' : 'Lưu địa điểm') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-venues-content-wrap {
  max-width: 1280px;
  margin: 0 auto;
  padding: 30px 24px 90px;
}

/* Header */
.admin-venues-header {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-bottom: 24px;
  margin-bottom: 24px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

@media (min-width: 860px) {
  .admin-venues-header {
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

/* Venues Grid */
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

.venue-card {
  background: var(--surface, #ffffff);
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.venue-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.venue-card.is-inactive {
  opacity: 0.75;
}

.venue-card-cover {
  position: relative;
  height: 160px;
  overflow: hidden;
}

.venue-cover-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.venue-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.15) 0%, rgba(0, 0, 0, 0.75) 100%);
}

.venue-status-badge-wrap {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 2;
}

.status-toggle-pill {
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

.pill-active {
  background: rgba(16, 185, 129, 0.9);
  color: #ffffff;
}

.pill-inactive {
  background: rgba(239, 68, 68, 0.9);
  color: #ffffff;
}

.pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ffffff;
}

.venue-cover-bottom {
  position: absolute;
  bottom: 10px;
  left: 12px;
  z-index: 2;
}

.court-count-pill {
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

.venue-card-body {
  padding: 16px;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.venue-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0 0 4px;
}

.venue-address {
  display: flex;
  align-items: flex-start;
  gap: 4px;
  font-size: 12px;
  color: var(--muted, #6c757d);
  margin: 0 0 14px;
  line-height: 1.4;
}

/* QR Box */
.qr-share-box {
  background: var(--surface-subtle, #f8f9fa);
  border-radius: 12px;
  padding: 10px;
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid var(--border, #e0e4ea);
  margin-bottom: 16px;
}

.qr-preview-box {
  width: 54px;
  height: 54px;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
}

.qr-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.qr-info-col {
  flex: 1;
  min-width: 0;
}

.btn-qr-action {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.btn-qr-action:hover {
  background: var(--soft, #e7e8e9);
}

.venue-actions-toolbar {
  margin-top: auto;
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--border, #e0e4ea);
}

.card-action-btn {
  flex: 1;
  padding: 8px 12px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  text-decoration: none;
  transition: all 0.15s ease;
}

.primary-action {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
}

.primary-action:hover {
  filter: brightness(1.08);
}

.secondary-action {
  background: var(--surface-subtle, #f3f4f5);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
}

.secondary-action:hover {
  background: var(--soft, #e7e8e9);
}

/* Aura Venue Modal Premium Design */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.aura-venue-modal {
  width: 100%;
  max-width: 540px;
  background: var(--surface, #1e2025);
  border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
  border-radius: 20px;
  padding: 24px 28px;
  box-shadow: 0 24px 50px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(255, 255, 255, 0.05);
  color: var(--ink, #ffffff);
  animation: modal-enter 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-sizing: border-box;
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

.aura-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.aura-label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--ink, #e5e7eb);
}

.aura-input {
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

.aura-input:focus {
  outline: none;
  border-color: var(--pink-dark, #f8b4c4);
  box-shadow: 0 0 0 3px rgba(248, 180, 196, 0.15);
  background: var(--surface, #1e2025);
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
  background: var(--pink, #ffd1dc);
  border: none;
  color: var(--pink-dark, #78555e);
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
  transition: all 0.15s ease;
}

.aura-btn-primary:hover:not(:disabled) {
  filter: brightness(1.08);
  transform: translateY(-1px);
}
</style>
