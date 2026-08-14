<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import QRCode from 'qrcode'
import { api, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Venue } from '@/types'

type VenueForm = { name: string; address: string; google_maps_url: string; is_active: boolean }
const auth = useAuthStore()
const venues = ref<Venue[]>([])
const qrUrls = ref<Record<number, string>>({})
const form = ref<VenueForm>({ name: '', address: '', google_maps_url: '', is_active: true })
const editingId = ref<number | null>(null)
const error = ref('')
const notice = ref('')
const saving = ref(false)
const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email.endsWith('@demo.auracourt.vn'))

async function makeQr(venue: Venue): Promise<void> {
  qrUrls.value[venue.id] = await QRCode.toDataURL(venue.share_url, { width: 1024, errorCorrectionLevel: 'H', margin: 2 })
}

async function load(): Promise<void> {
  error.value = ''
  try {
    venues.value = await api.get<Venue[]>('/admin/venues')
    await Promise.all(venues.value.map(makeQr))
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được danh sách địa điểm.') }
}

function startCreate(): void {
  editingId.value = null
  form.value = { name: '', address: '', google_maps_url: '', is_active: true }
}

function startEdit(venue: Venue): void {
  editingId.value = venue.id
  form.value = { name: venue.name, address: venue.address || '', google_maps_url: venue.google_maps_url || '', is_active: venue.is_active }
}

async function save(): Promise<void> {
  saving.value = true; error.value = ''; notice.value = ''
  try {
    if (isDemoAdmin.value) {
      if (editingId.value) {
        venues.value = venues.value.map((venue) => venue.id === editingId.value ? { ...venue, ...form.value } : venue)
        notice.value = 'Đã mô phỏng cập nhật địa điểm (không ghi vào dữ liệu thật).'
      } else {
        const id = -Date.now(); const slug = `${form.value.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'venue'}-demo`
        const venue = { ...form.value, id, slug, share_url: `${window.location.origin}/venues/${slug}/directions`, address: form.value.address, google_maps_url: form.value.google_maps_url }
        venues.value = [...venues.value, venue]
        await makeQr(venue)
        notice.value = 'Đã mô phỏng tạo địa điểm (không ghi vào dữ liệu thật).'
      }
      startCreate(); return
    }
    if (editingId.value) await api.patch(`/admin/venues/${editingId.value}`, form.value)
    else await api.post('/admin/venues', form.value)
    notice.value = editingId.value ? 'Đã cập nhật địa điểm.' : 'Đã tạo địa điểm.'
    await load(); startCreate()
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể lưu địa điểm.') }
  finally { saving.value = false }
}

async function toggle(venue: Venue): Promise<void> {
  const active = !venue.is_active
  if (isDemoAdmin.value) { venue.is_active = active; notice.value = 'Đã mô phỏng đổi trạng thái địa điểm (không ghi vào dữ liệu thật).'; return }
  try { await api.patch(`/admin/venues/${venue.id}`, { is_active: active }); notice.value = active ? 'Đã mở lại địa điểm.' : 'Đã ngừng hoạt động địa điểm.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể đổi trạng thái địa điểm.') }
}

async function copyLink(venue: Venue): Promise<void> {
  await navigator.clipboard.writeText(venue.share_url)
  notice.value = 'Đã sao chép link chia sẻ.'
}

function downloadQr(venue: Venue): void {
  const url = qrUrls.value[venue.id]
  if (!url) return
  const link = document.createElement('a'); link.href = url; link.download = `${venue.slug}-directions-qr.png`; link.click()
}

onMounted(load)
</script>

<template>
  <section class="page-shell admin-venues">
    <div class="row"><div><div class="eyebrow">Vận hành</div><h1 class="page-title">Địa điểm</h1><p class="muted">Quản lý địa chỉ, Google Maps và QR chia sẻ cho từng địa điểm.</p></div><button class="btn" @click="startCreate">+ Thêm địa điểm</button></div>
    <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>
    <form class="panel admin-form" @submit.prevent="save"><h2>{{ editingId ? 'Cập nhật địa điểm' : 'Tạo địa điểm mới' }}</h2><div class="admin-form-grid"><label>Tên địa điểm<input v-model="form.name" required maxlength="120"></label><label>Địa chỉ<input v-model="form.address" required maxlength="255"></label><label class="wide">Link Google Maps<input v-model="form.google_maps_url" type="url" required placeholder="https://maps.google.com/..." /><small class="muted">Chỉ chấp nhận link HTTPS của Google Maps.</small></label></div><div class="actions"><button class="btn" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Lưu địa điểm' }}</button><button v-if="editingId" type="button" class="btn secondary" @click="startCreate">Hủy</button></div></form>
    <div class="venue-admin-grid"><article v-for="venue in venues" :key="venue.id" class="panel venue-admin-card"><div class="row"><div><h2>{{ venue.name }} <span class="badge" :class="{ 'badge-danger': !venue.is_active }">{{ venue.is_active ? 'Đang hoạt động' : 'Đã ngừng' }}</span></h2><p class="muted">{{ venue.address || 'Chưa có địa chỉ' }}</p><p class="muted">{{ venue.courts_count || 0 }} sân · <code>/venues/{{ venue.slug }}/directions</code></p></div><div class="actions"><button class="link-button" @click="startEdit(venue)">Sửa</button><button class="link-button" @click="copyLink(venue)">Copy link</button><button class="link-button" :class="{ danger: venue.is_active }" @click="toggle(venue)">{{ venue.is_active ? 'Ngừng' : 'Mở lại' }}</button></div></div><div class="venue-qr"><img v-if="qrUrls[venue.id]" :src="qrUrls[venue.id]" :alt="`QR chỉ đường ${venue.name}`"><div><strong>QR chỉ đường</strong><p class="muted">QR luôn mã hóa link Aura Court ổn định của địa điểm.</p><button class="btn secondary" :disabled="!qrUrls[venue.id]" @click="downloadQr(venue)">Tải QR PNG</button></div></div></article></div>
  </section>
</template>
