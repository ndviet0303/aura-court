<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, apiOrigin, errorMessage } from '@/lib/api'
import type { Booking } from '@/types'

const bookings = ref<Booking[]>([])
const selectedSlots = ref<Record<number, number[]>>({})
const reviewDrafts = ref<Record<number, { rating: number; body: string }>>({})
const error = ref('')
const notice = ref('')
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
const formatSlot = (value: string) => new Date(value).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
const statusLabel = (value: string) => ({ pending_payment: 'Chờ thanh toán', confirmed: 'Đã xác nhận', cancelled: 'Đã hủy', expired: 'Đã hết hạn', refunded: 'Đã hoàn tiền', no_show: 'Vắng mặt' }[value] || value)

async function load() {
  try { const result = await api.get<{ data: Booking[] }>('/bookings'); bookings.value = result.data }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được lịch đặt sân.') }
}

function toggleSlot(bookingId: number, slotId?: number) {
  if (!slotId) return
  const current = selectedSlots.value[bookingId] || []
  selectedSlots.value[bookingId] = current.includes(slotId) ? current.filter((id) => id !== slotId) : [...current, slotId]
}

async function cancel(booking: Booking) {
  if (!confirm('Bạn chắc chắn muốn hủy lịch đặt này?')) return
  try { await api.post('/bookings/' + booking.code + '/cancel', { reason: 'Khách tự hủy' }); await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể hủy lịch đặt.') }
}

async function cancelSelected(booking: Booking) {
  const slotIds = selectedSlots.value[booking.id] || []
  if (!slotIds.length || !confirm('Hủy các khung giờ đã chọn?')) return
  try { await api.post('/bookings/' + booking.code + '/cancel-slots', { slot_ids: slotIds, reason: 'Khách hủy từng khung giờ' }); selectedSlots.value[booking.id] = []; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể hủy khung giờ.') }
}

function isCompleted(booking: Booking) {
  return booking.status === 'confirmed' && booking.slots.length > 0 && booking.slots.every((slot) => new Date(slot.ends_at).getTime() < Date.now())
}

function draft(booking: Booking) { return reviewDrafts.value[booking.id] ||= { rating: 5, body: '' } }

async function submitReview(booking: Booking) {
  try { await api.post('/reviews', { booking_code: booking.code, ...draft(booking) }); notice.value = 'Cảm ơn bạn đã đánh giá sân.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể gửi đánh giá.') }
}

onMounted(load)
</script>

<template>
  <section class="row"><div><div class="eyebrow">Hoạt động của bạn</div><h1 class="page-title">Lịch đặt sân của tôi</h1><p class="muted">Theo dõi thanh toán, lịch sân và đánh giá sau khi chơi.</p></div><RouterLink to="/courts" class="btn">Đặt sân mới</RouterLink></section>
  <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>
  <div v-if="!bookings.length" class="card" style="margin-top:28px"><p>Chưa có lịch đặt sân nào.</p></div>
  <div v-for="booking in bookings" :key="booking.id" class="card" style="margin-top:14px">
    <div class="row"><div><h3>{{ booking.court.name }} · {{ booking.code }}</h3><p class="muted">{{ booking.slots.map((slot) => formatSlot(slot.starts_at)).join(' · ') }}</p></div><span class="badge">{{ statusLabel(booking.status) }}</span></div>
    <div class="slot-list" v-if="booking.status === 'confirmed' && booking.slots.length > 1"><label v-for="slot in booking.slots" :key="slot.id"><input type="checkbox" :checked="selectedSlots[booking.id]?.includes(slot.id || 0)" @change="toggleSlot(booking.id, slot.id)" /> {{ formatSlot(slot.starts_at) }} · {{ money(slot.unit_price) }}</label></div>
    <div class="row"><strong>{{ money(booking.total_amount) }}</strong><div class="actions"><RouterLink v-if="booking.status === 'pending_payment'" :to="'/payment/' + booking.code" class="link-button">Thanh toán</RouterLink><a v-if="booking.status === 'confirmed'" class="link-button" :href="`${apiOrigin}/api/v1/bookings/${booking.code}/calendar.ics`">Thêm vào lịch</a><RouterLink v-if="booking.status === 'confirmed'" :to="'/courts/' + booking.court.id + '/schedule?reschedule=' + booking.code" class="link-button">Đổi lịch</RouterLink><button v-if="booking.status === 'confirmed' && (selectedSlots[booking.id]?.length || 0)" class="link-button danger" @click="cancelSelected(booking)">Hủy khung giờ đã chọn</button><button v-if="['pending_payment', 'confirmed'].includes(booking.status)" class="btn" @click="cancel(booking)">Hủy lịch đặt</button></div></div>
    <form v-if="isCompleted(booking) && !booking.review" class="panel" style="margin-top:18px" @submit.prevent="submitReview(booking)"><strong>Đánh giá sân</strong><div class="field"><label>Điểm đánh giá</label><select v-model.number="draft(booking).rating"><option v-for="rating in [5, 4, 3, 2, 1]" :key="rating" :value="rating">{{ rating }}/5</option></select></div><div class="field"><label>Nhận xét (không bắt buộc)</label><textarea v-model="draft(booking).body" rows="3" maxlength="2000" /></div><button class="btn">Gửi đánh giá</button></form>
    <p v-else-if="booking.review" class="muted" style="margin-top:18px">Bạn đã đánh giá {{ booking.review.rating }}/5 cho booking này.</p>
  </div>
</template>
