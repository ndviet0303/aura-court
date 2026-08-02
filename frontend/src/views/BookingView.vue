<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import type { Booking } from '@/types'

const bookings = ref<Booking[]>([])
const selectedSlots = ref<Record<number, number[]>>({})
const error = ref('')
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
const formatSlot = (value: string) => new Date(value).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
const statusLabel = (value: string) => ({ pending_payment: 'Chờ thanh toán', confirmed: 'Đã xác nhận', cancelled: 'Đã hủy', expired: 'Đã hết hạn', refunded: 'Đã hoàn tiền', no_show: 'Vắng mặt' }[value] || value)

async function load() {
  try {
    const result = await api.get<{ data: Booking[] }>('/bookings')
    bookings.value = result.data
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được lịch đặt sân.') }
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

onMounted(load)
</script>

<template>
  <section class="row"><div><div class="eyebrow">Hoạt động của bạn</div><h1 class="page-title">Lịch đặt sân của tôi</h1><p class="muted">Theo dõi thanh toán và lịch sân đã đặt.</p></div><RouterLink to="/courts" class="btn">Đặt sân mới</RouterLink></section>
  <p v-if="error" class="error">{{ error }}</p>
  <div v-if="!bookings.length" class="card" style="margin-top:28px"><p>Chưa có lịch đặt sân nào.</p></div>
  <div v-for="booking in bookings" :key="booking.id" class="card" style="margin-top:14px">
    <div class="row"><div><h3>{{ booking.court.name }} · {{ booking.code }}</h3><p class="muted">{{ booking.slots.map((slot) => formatSlot(slot.starts_at)).join(' · ') }}</p></div><span class="badge">{{ statusLabel(booking.status) }}</span></div>
    <div class="slot-list" v-if="booking.status === 'confirmed' && booking.slots.length > 1"><label v-for="slot in booking.slots" :key="slot.id"><input type="checkbox" :checked="selectedSlots[booking.id]?.includes(slot.id || 0)" @change="toggleSlot(booking.id, slot.id)" /> {{ formatSlot(slot.starts_at) }} · {{ money(slot.unit_price) }}</label></div>
    <div class="row"><strong>{{ money(booking.total_amount) }}</strong><div class="actions"><RouterLink v-if="booking.status === 'pending_payment'" :to="'/payment/' + booking.code" class="link-button">Thanh toán</RouterLink><RouterLink v-if="booking.status === 'confirmed'" :to="'/courts/' + booking.court.id + '/schedule?reschedule=' + booking.code" class="link-button">Đổi lịch</RouterLink><button v-if="booking.status === 'confirmed' && (selectedSlots[booking.id]?.length || 0)" class="link-button danger" @click="cancelSelected(booking)">Hủy khung giờ đã chọn</button><button v-if="['pending_payment','confirmed'].includes(booking.status)" class="btn" @click="cancel(booking)">Hủy lịch đặt</button></div></div>
  </div>
</template>
