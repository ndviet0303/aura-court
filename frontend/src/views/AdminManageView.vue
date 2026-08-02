<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, errorMessage } from '@/lib/api'
import type { Court, Refund } from '@/types'

type CourtForm = { name: string; description: string; image_url: string; is_active: boolean }
type PriceForm = { weekday: number; starts_at: string; ends_at: string; price: number }
type BlockForm = { starts_at: string; ends_at: string; reason: string }

const courts = ref<Court[]>([])
const refunds = ref<Refund[]>([])
const error = ref('')
const notice = ref('')
const saving = ref(false)
const courtForm = ref<CourtForm>({ name: '', description: '', image_url: '', is_active: true })
const editingCourt = ref<number | null>(null)
const priceForms = ref<Record<number, PriceForm>>({})
const blockForms = ref<Record<number, BlockForm>>({})

const emptyPrice = (): PriceForm => ({ weekday: 1, starts_at: '06:00', ends_at: '07:00', price: 100000 })
const emptyBlock = (): BlockForm => ({ starts_at: '', ends_at: '', reason: '' })
const priceForm = (courtId: number) => priceForms.value[courtId]!
const blockForm = (courtId: number) => blockForms.value[courtId]!

async function load() {
  error.value = ''
  try {
    courts.value = await api.get<Court[]>('/courts')
    refunds.value = (await api.get<{ data: Refund[] }>('/admin/refunds')).data
    courts.value.forEach((court) => {
      priceForms.value[court.id] ||= emptyPrice()
      blockForms.value[court.id] ||= emptyBlock()
    })
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được dữ liệu quản trị.') }
}

function startEdit(court: Court) {
  editingCourt.value = court.id
  courtForm.value = { name: court.name, description: court.description || '', image_url: court.image_url || '', is_active: true }
}

function startCreate() {
  editingCourt.value = null
  courtForm.value = { name: '', description: '', image_url: '', is_active: true }
}

async function saveCourt() {
  saving.value = true; error.value = ''; notice.value = ''
  try {
    const payload = { ...courtForm.value, image_url: courtForm.value.image_url || null }
    if (editingCourt.value) await api.patch(`/admin/courts/${editingCourt.value}`, payload)
    else await api.post('/admin/courts', payload)
    notice.value = editingCourt.value ? 'Đã cập nhật sân.' : 'Đã tạo sân mới.'
    await load(); startCreate()
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể lưu sân.') }
  finally { saving.value = false }
}

async function deactivateCourt(court: Court) {
  if (!confirm(`Ngừng mở ${court.name}?`)) return
  try { await api.delete(`/admin/courts/${court.id}`); notice.value = 'Đã ngừng mở sân.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể ngừng mở sân.') }
}

async function savePrice(court: Court) {
  try { await api.post(`/admin/courts/${court.id}/prices`, priceForms.value[court.id]); notice.value = `Đã lưu giá cho ${court.name}.`; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể lưu giá.') }
}

async function saveBlock(court: Court) {
  const block = blockForms.value[court.id]!
  if (!block.starts_at || !block.ends_at) { error.value = 'Nhập đầy đủ thời gian khóa sân.'; return }
  try { await api.post(`/admin/courts/${court.id}/blocks`, block); notice.value = `Đã khóa lịch ${court.name}.`; blockForms.value[court.id] = emptyBlock() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể khóa lịch.') }
}

async function processRefund(refund: Refund, status: 'processed' | 'rejected') {
  try { await api.patch(`/admin/refunds/${refund.id}`, { status }); notice.value = status === 'processed' ? 'Đã duyệt hoàn tiền.' : 'Đã từ chối hoàn tiền.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể xử lý hoàn tiền.') }
}

onMounted(load)
</script>

<template>
  <section class="page-shell admin-manage">
    <div class="row"><div><div class="eyebrow">Operations</div><h1 class="page-title">Quản lý sân</h1><p class="muted">CRUD sân, giá theo khung giờ, lịch khóa và hoàn tiền.</p></div><button class="btn" @click="startCreate">+ Thêm sân</button></div>
    <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>

    <form class="panel admin-form" @submit.prevent="saveCourt"><h2>{{ editingCourt ? 'Cập nhật sân' : 'Tạo sân mới' }}</h2><div class="admin-form-grid"><label>Tên sân<input v-model="courtForm.name" required maxlength="120" /></label><label>Ảnh URL<input v-model="courtForm.image_url" type="url" placeholder="https://..." /></label><label class="wide">Mô tả<textarea v-model="courtForm.description" rows="2" /></label></div><div class="actions"><button class="btn" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Lưu sân' }}</button><button v-if="editingCourt" type="button" class="btn secondary" @click="startCreate">Hủy</button></div></form>

    <div class="admin-court-list"><article v-for="court in courts" :key="court.id" class="panel admin-court"><div class="row"><div><h2>{{ court.name }}</h2><p class="muted">{{ court.description || 'Chưa có mô tả' }}</p></div><div class="actions"><button class="link-button" @click="startEdit(court)">Sửa</button><button class="link-button danger" @click="deactivateCourt(court)">Ngừng mở</button></div></div><div class="admin-form-grid compact"><label>Thứ (1–7)<input v-model.number="priceForm(court.id).weekday" type="number" min="1" max="7" /></label><label>Bắt đầu<input v-model="priceForm(court.id).starts_at" type="time" /></label><label>Kết thúc<input v-model="priceForm(court.id).ends_at" type="time" /></label><label>Giá/giờ<input v-model.number="priceForm(court.id).price" type="number" min="0" step="1000" /></label><button class="btn" @click="savePrice(court)">Lưu giá</button></div><div class="price-list"><span v-for="price in (court.price_slots || [])" :key="price.id || `${price.weekday}-${price.starts_at}`" class="badge">T{{ price.weekday }} {{ price.starts_at.slice(0,5) }} · {{ new Intl.NumberFormat('vi-VN').format(price.price) }}₫</span></div><div class="admin-form-grid compact block-row"><label>Bắt đầu khóa<input v-model="blockForm(court.id).starts_at" type="datetime-local" /></label><label>Kết thúc khóa<input v-model="blockForm(court.id).ends_at" type="datetime-local" /></label><label>Lý do<input v-model="blockForm(court.id).reason" placeholder="Bảo trì" /></label><button class="btn secondary" @click="saveBlock(court)">Khóa lịch</button></div></article></div>

    <section class="panel" style="margin-top:24px"><h2>Yêu cầu hoàn tiền</h2><p v-if="!refunds.length" class="muted">Không có yêu cầu đang chờ.</p><div v-else class="table-wrap"><table class="table"><thead><tr><th>Mã booking</th><th>Khách</th><th>Số tiền</th><th>Thao tác</th></tr></thead><tbody><tr v-for="refund in refunds" :key="refund.id"><td>{{ refund.booking.code }}</td><td>{{ refund.booking.user?.name }}</td><td>{{ new Intl.NumberFormat('vi-VN').format(refund.amount) }}₫</td><td class="actions"><button class="link-button" @click="processRefund(refund, 'processed')">Duyệt</button><button class="link-button danger" @click="processRefund(refund, 'rejected')">Từ chối</button></td></tr></tbody></table></div></section>
  </section>
</template>
