<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import DatePicker from '@/components/DatePicker.vue'
import { useAuthStore } from '@/stores/auth'
import { useBookingStore } from '@/stores/booking'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useBookingStore()
const date = ref(new Date(Date.now() + 86400000).toISOString().slice(0, 10))
const couponCode = ref('')
const court = computed(() => store.courts.find((item) => item.id === Number(route.params.id)) || store.courts[0])
const unavailableSlots = computed(() => store.slots.filter((slot) => !slot.available))
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value)
const error = ref('')
const notice = ref('')
const loading = ref(false)

onMounted(async () => {
  try { await store.loadCourts(); if (court.value) await store.loadAvailability(court.value.id, date.value) }
  catch { error.value = store.error }
})

async function refresh() {
  if (court.value) { try { await store.loadAvailability(court.value.id, date.value) } catch { error.value = store.error } }
}

async function joinWaitlist(slot: { startsAt: string }) {
  if (!auth.user) { router.push({ name: 'login', query: { redirect: route.fullPath } }); return }
  try { await api.post('/waitlist', { court_id: court.value?.id, starts_at: slot.startsAt }); notice.value = 'Đã thêm vào danh sách chờ. Bạn sẽ nhận thông báo khi slot mở.' }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể tham gia danh sách chờ.') }
}

async function next() {
  if (!store.selectedSlots.length) return
  loading.value = true; error.value = ''
  try {
    const payload = { court_id: court.value?.id, slots: store.selectedSlots.map((slot) => slot.startsAt) }
    const result = route.query.reschedule ? await api.post<{ booking: { code: string } }>('/bookings/' + route.query.reschedule + '/reschedule', payload) : await store.createBooking(couponCode.value)
    router.push('/payment/' + result.booking.code)
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể tạo lịch đặt sân.') }
  finally { loading.value = false }
}
</script>

<template>
  <section class="page-shell">
    <div class="eyebrow">Đặt sân</div><h1 class="page-title">Chọn khung giờ.</h1><p class="muted">{{ court?.name || 'Aura Court' }} · Chọn một hoặc nhiều khung giờ liên tiếp.</p>
    <div class="panel" style="margin-top:30px">
      <div class="row"><div><strong>Chọn ngày</strong><p class="muted" style="margin:5px 0 0">Khung giờ 60 phút · Giá hiển thị theo lịch</p></div><DatePicker v-model="date" @change="refresh" /></div>
      <div class="slot-grid"><button v-for="slot in store.slots" :key="slot.startsAt" class="slot" :class="{ selected: store.selectedSlots.some((item) => item.startsAt === slot.startsAt), unavailable: !slot.available }" :disabled="!slot.available" @click="store.toggle(slot)">{{ new Date(slot.startsAt).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) }}<br><small>{{ money(slot.price) }}</small></button></div>
      <div v-if="unavailableSlots.length" class="notice" style="margin-top:18px"><strong>Slot đã kín?</strong> <span>Đăng ký danh sách chờ để được báo khi có người hủy.</span><div class="actions" style="margin-top:10px"><button v-for="slot in unavailableSlots" :key="slot.startsAt" class="link-button" @click="joinWaitlist(slot)">Chờ {{ new Date(slot.startsAt).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) }}</button></div></div>
      <div v-if="!route.query.reschedule" class="field" style="margin-top:22px"><label>Mã giảm giá / coupon</label><input v-model="couponCode" maxlength="40" placeholder="Nhập mã nếu có" /></div>
      <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>
      <div class="row" style="margin-top:28px"><span class="muted">{{ store.selectedSlots.length }} khung giờ đã chọn</span><button class="btn" :disabled="!store.selectedSlots.length || loading" @click="next">{{ loading ? 'Đang giữ chỗ…' : 'Xem lại và thanh toán' }} <span class="material-symbols-outlined">arrow_forward</span></button></div>
    </div>
  </section>
</template>
