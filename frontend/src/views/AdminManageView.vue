<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api, errorMessage } from '@/lib/api'
import type { Court, CourtBlock, PriceSlot, Refund } from '@/types'
import TimePicker from '@/components/TimePicker.vue'
import DateTimePicker from '@/components/DateTimePicker.vue'
import { useAuthStore } from '@/stores/auth'

type CourtForm = { name: string; description: string; image_url: string; is_active: boolean }
type PriceForm = { weekday: number; starts_at: string; ends_at: string; price: number }
type BlockForm = { starts_at: string; ends_at: string; reason: string }

const courts = ref<Court[]>([])
const auth = useAuthStore()
const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email.endsWith('@demo.auracourt.vn'))
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
    courts.value = await api.get<Court[]>('/admin/courts')
    courts.value.forEach((court) => {
      priceForms.value[court.id] ||= emptyPrice()
      blockForms.value[court.id] ||= emptyBlock()
    })
    refunds.value = (await api.get<{ data: Refund[] }>('/admin/refunds')).data
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được dữ liệu quản trị.') }
}

function startEdit(court: Court) {
  editingCourt.value = court.id
  courtForm.value = { name: court.name, description: court.description || '', image_url: court.image_url || '', is_active: court.is_active !== false }
}

function startCreate() {
  editingCourt.value = null
  courtForm.value = { name: '', description: '', image_url: '', is_active: true }
}

async function saveCourt() {
  saving.value = true; error.value = ''; notice.value = ''
  try {
    const payload = { ...courtForm.value, image_url: courtForm.value.image_url || null }
    if (isDemoAdmin.value) {
      if (editingCourt.value) {
        courts.value = courts.value.map((court) => court.id === editingCourt.value ? { ...court, ...payload, image_url: payload.image_url || undefined } : court)
        notice.value = 'Đã mô phỏng cập nhật sân (không ghi vào dữ liệu thật).'
      } else {
        const syntheticId = -Date.now()
        courts.value = [...courts.value, { ...payload, id: syntheticId, image_url: payload.image_url || undefined, price_slots: [], blocks: [] }]
        priceForms.value[syntheticId] = emptyPrice()
        blockForms.value[syntheticId] = emptyBlock()
        notice.value = 'Đã mô phỏng tạo sân (không ghi vào dữ liệu thật).'
      }
      startCreate()
      return
    }
    if (editingCourt.value) await api.patch(`/admin/courts/${editingCourt.value}`, payload)
    else await api.post('/admin/courts', payload)
    notice.value = editingCourt.value ? 'Đã cập nhật sân.' : 'Đã tạo sân mới.'
    await load(); startCreate()
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể lưu sân.') }
  finally { saving.value = false }
}

async function deactivateCourt(court: Court) {
  if (!confirm(`Ngừng mở ${court.name}?`)) return
  if (isDemoAdmin.value) { court.is_active = false; notice.value = 'Đã mô phỏng ngừng mở sân (không ghi vào dữ liệu thật).'; return }
  try { await api.delete(`/admin/courts/${court.id}`); notice.value = 'Đã ngừng mở sân.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể ngừng mở sân.') }
}

async function activateCourt(court: Court) {
  if (isDemoAdmin.value) { court.is_active = true; notice.value = 'Đã mô phỏng mở lại sân (không ghi vào dữ liệu thật).'; return }
  try { await api.patch('/admin/courts/' + court.id, { is_active: true }); notice.value = 'Đã mở lại sân.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể mở lại sân.') }
}

async function savePrice(court: Court) {
  if (isDemoAdmin.value) {
    const form = priceForms.value[court.id]!
    const prices = [...(court.price_slots || [])]
    const index = prices.findIndex((price) => price.weekday === form.weekday && price.starts_at.slice(0, 5) === form.starts_at)
    const next = { ...form, id: index >= 0 ? prices[index]?.id : -Date.now(), court_id: court.id, is_active: true }
    if (index >= 0) prices[index] = next
    else prices.push(next)
    court.price_slots = prices
    notice.value = `Đã mô phỏng lưu giá cho ${court.name} (không ghi vào dữ liệu thật).`
    return
  }
  try { await api.post(`/admin/courts/${court.id}/prices`, priceForms.value[court.id]); notice.value = `Đã lưu giá cho ${court.name}.`; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể lưu giá.') }
}

async function deletePrice(court: Court, price: PriceSlot) {
  if (!price.id || !confirm('Xóa khung giá này?')) return
  if (isDemoAdmin.value) { court.price_slots = (court.price_slots || []).filter((item) => item.id !== price.id); notice.value = 'Đã mô phỏng xóa khung giá (không ghi vào dữ liệu thật).'; return }
  try { await api.delete('/admin/courts/' + court.id + '/prices/' + price.id); notice.value = 'Đã xóa khung giá.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể xóa khung giá.') }
}

async function saveBlock(court: Court) {
  const block = blockForms.value[court.id]!
  if (!block.starts_at || !block.ends_at) { error.value = 'Nhập đầy đủ thời gian khóa sân.'; return }
  if (isDemoAdmin.value) { court.blocks = [...(court.blocks || []), { ...block, id: -Date.now(), court_id: court.id }]; notice.value = `Đã mô phỏng khóa lịch ${court.name} (không ghi vào dữ liệu thật).`; blockForms.value[court.id] = emptyBlock(); return }
  try { await api.post(`/admin/courts/${court.id}/blocks`, block); notice.value = `Đã khóa lịch ${court.name}.`; blockForms.value[court.id] = emptyBlock() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể khóa lịch.') }
}

async function deleteBlock(court: Court, block: CourtBlock) {
  if (!confirm('Mở khóa lịch này?')) return
  if (isDemoAdmin.value) { court.blocks = (court.blocks || []).filter((item) => item.id !== block.id); notice.value = 'Đã mô phỏng mở khóa lịch (không ghi vào dữ liệu thật).'; return }
  try { await api.delete('/admin/courts/' + court.id + '/blocks/' + block.id); notice.value = 'Đã mở khóa lịch.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể mở khóa lịch.') }
}

async function processRefund(refund: Refund, status: 'processed' | 'rejected') {
  if (isDemoAdmin.value) { refunds.value = refunds.value.filter((item) => item.id !== refund.id); notice.value = status === 'processed' ? 'Đã mô phỏng duyệt hoàn tiền.' : 'Đã mô phỏng từ chối hoàn tiền.'; return }
  try { await api.patch(`/admin/refunds/${refund.id}`, { status }); notice.value = status === 'processed' ? 'Đã duyệt hoàn tiền.' : 'Đã từ chối hoàn tiền.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể xử lý hoàn tiền.') }
}

onMounted(load)
</script>

<template>
  <section class="page-shell admin-manage">
    <div class="row"><div><div class="eyebrow">Vận hành</div><h1 class="page-title">Quản lý sân</h1><p class="muted">Quản lý sân, giá theo khung giờ, lịch khóa và hoàn tiền.</p></div><button class="btn" @click="startCreate">+ Thêm sân</button></div>
    <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>

    <form class="panel admin-form" @submit.prevent="saveCourt"><h2>{{ editingCourt ? 'Cập nhật sân' : 'Tạo sân mới' }}</h2><div class="admin-form-grid"><label>Tên sân<input v-model="courtForm.name" required maxlength="120" /></label><label>Ảnh URL<input v-model="courtForm.image_url" type="url" placeholder="https://..." /></label><label class="wide">Mô tả<textarea v-model="courtForm.description" rows="2" /></label></div><div class="actions"><button class="btn" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Lưu sân' }}</button><button v-if="editingCourt" type="button" class="btn secondary" @click="startCreate">Hủy</button></div></form>

    <div class="admin-court-list"><article v-for="court in courts" :key="court.id" class="panel admin-court"><div class="row"><div><h2>{{ court.name }} <span class="badge" :class="{ 'badge-danger': court.is_active === false }">{{ court.is_active === false ? 'Đã ngừng' : 'Đang mở' }}</span></h2><p class="muted">{{ court.description || 'Chưa có mô tả' }}</p></div><div class="actions"><button class="link-button" @click="startEdit(court)">Sửa</button><button v-if="court.is_active !== false" class="link-button danger" @click="deactivateCourt(court)">Ngừng mở</button><button v-else class="link-button" @click="activateCourt(court)">Mở lại</button></div></div><div class="admin-form-grid compact"><label>Thứ (1–7)<input v-model.number="priceForm(court.id).weekday" type="number" min="1" max="7" /></label><label>Bắt đầu<TimePicker v-model="priceForm(court.id).starts_at" /></label><label>Kết thúc<TimePicker v-model="priceForm(court.id).ends_at" /></label><label>Giá/giờ<input v-model.number="priceForm(court.id).price" type="number" min="0" step="1000" /></label><button class="btn" @click="savePrice(court)">Lưu giá</button></div><div class="price-list"><span v-for="price in (court.price_slots || [])" :key="price.id || `${price.weekday}-${price.starts_at}`" class="badge">T{{ price.weekday }} {{ price.starts_at.slice(0,5) }} · {{ new Intl.NumberFormat('vi-VN').format(price.price) }}₫<button class="link-button danger" @click="deletePrice(court, price)">×</button></span></div><div class="admin-form-grid compact block-row"><label>Bắt đầu khóa<DateTimePicker v-model="blockForm(court.id).starts_at" /></label><label>Kết thúc khóa<DateTimePicker v-model="blockForm(court.id).ends_at" /></label><label>Lý do<input v-model="blockForm(court.id).reason" placeholder="Bảo trì" /></label><button class="btn secondary" @click="saveBlock(court)">Khóa lịch</button></div><div v-if="court.blocks?.length" class="price-list"><span v-for="block in court.blocks" :key="block.id" class="badge">Khóa {{ new Date(block.starts_at).toLocaleString() }}–{{ new Date(block.ends_at).toLocaleTimeString() }} <button class="link-button" @click="deleteBlock(court, block)">Mở</button></span></div></article></div>

    <section class="panel" style="margin-top:24px"><h2>Yêu cầu hoàn tiền</h2><p v-if="!refunds.length" class="muted">Không có yêu cầu đang chờ.</p><div v-else class="table-wrap"><table class="table"><thead><tr><th>Mã đặt sân</th><th>Khách</th><th>Số tiền</th><th>Thao tác</th></tr></thead><tbody><tr v-for="refund in refunds" :key="refund.id"><td>{{ refund.booking.code }}</td><td>{{ refund.booking.user?.name }}</td><td>{{ new Intl.NumberFormat('vi-VN').format(refund.amount) }}₫</td><td class="actions"><button class="link-button" @click="processRefund(refund, 'processed')">Duyệt</button><button class="link-button danger" @click="processRefund(refund, 'rejected')">Từ chối</button></td></tr></tbody></table></div></section>
  </section>
</template>
