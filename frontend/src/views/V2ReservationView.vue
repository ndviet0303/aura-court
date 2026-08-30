<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { bookingApi } from '@/features/booking/api/bookingApi'
import { errorMessage } from '@/core/utils/errorMessage'

type Quote = { id: number; quote_token: string; total_amount: number; expires_at: string; slot_snapshot: Array<{ starts_at: string; unit_price: number }> }
type Reservation = { id: number; code: string; status: string; total_amount: number; hold_expires_at?: string; court: { name: string }; slots: Array<{ starts_at: string; unit_price: number }> }

const courtId = ref('')
const slot = ref('')
const quote = ref<Quote | null>(null)
const reservations = ref<Reservation[]>([])
const error = ref('')
const notice = ref('')
const loading = ref(false)
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
const formatDate = (value: string) => new Date(value).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' })

async function loadReservations(): Promise<void> {
  try {
    const response = await bookingApi.getReservations<{ data: Reservation[] }>()
    reservations.value = response.data || []
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không tải được lịch đặt v2.')
  }
}

async function getQuote(): Promise<void> {
  if (!courtId.value || !slot.value) return
  loading.value = true
  error.value = ''
  try {
    const response = await bookingApi.getPriceQuote<{ quote: Quote }>({ court_id: Number(courtId.value), slots: [new Date(slot.value).toISOString()] })
    quote.value = response.quote
    notice.value = 'Báo giá đã được giữ trong 15 phút.'
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể tạo báo giá.')
  } finally {
    loading.value = false
  }
}

async function holdReservation(): Promise<void> {
  if (!quote.value) return
  loading.value = true
  error.value = ''
  try {
    await bookingApi.holdReservation({ quote_token: quote.value.quote_token })
    quote.value = null
    notice.value = 'Đã giữ lịch. Hãy xác nhận thanh toán để kích hoạt quyền đặt sân.'
    await loadReservations()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể giữ lịch đặt.')
  } finally {
    loading.value = false
  }
}

async function confirmReservation(reservation: Reservation): Promise<void> {
  try {
    await bookingApi.confirmReservation(reservation.code)
    notice.value = 'Lịch đặt đã được xác nhận.'
    await loadReservations()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể xác nhận lịch đặt.')
  }
}

onMounted(loadReservations)
</script>

<template>
  <main class="page-shell">
    <section class="row"><div><div class="eyebrow">API v2</div><h1 class="page-title">Báo giá & lịch đặt mới</h1><p class="muted">Báo giá được chụp bất biến trước khi giữ tồn kho.</p></div></section>
    <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>
    <section class="card" style="margin-top: 18px">
      <h2>Tạo báo giá</h2>
      <div class="row" style="margin-top: 14px; align-items: end">
        <label class="field">Mã sân<input v-model="courtId" type="number" min="1" placeholder="Ví dụ: 1" /></label>
        <label class="field">Thời gian<input v-model="slot" type="datetime-local" /></label>
        <button class="btn" :disabled="loading" @click="getQuote">{{ loading ? 'Đang tính...' : 'Tính giá' }}</button>
      </div>
      <div v-if="quote" class="panel" style="margin-top: 16px">
        <strong>{{ money(quote.total_amount) }}</strong><span class="muted"> · hết hạn {{ formatDate(quote.expires_at) }}</span>
        <button class="btn" style="margin-left: 12px" @click="holdReservation">Giữ lịch</button>
      </div>
    </section>
    <section class="card" style="margin-top: 18px">
      <h2>Lịch đặt v2 của tôi</h2>
      <div v-if="!reservations.length" class="muted" style="margin-top: 12px">Chưa có lịch đặt v2.</div>
      <div v-for="reservation in reservations" :key="reservation.id" class="row" style="padding: 14px 0; border-bottom: 1px solid var(--line)">
        <div><strong>{{ reservation.court.name }} · {{ reservation.code }}</strong><p class="muted">{{ reservation.slots.map((item) => formatDate(item.starts_at)).join(' · ') }}</p></div>
        <div class="actions"><span class="badge">{{ reservation.status }}</span><strong>{{ money(reservation.total_amount) }}</strong><button v-if="reservation.status === 'held'" class="link-button" @click="confirmReservation(reservation)">Xác nhận</button></div>
      </div>
    </section>
  </main>
</template>

<style scoped>
@media (max-width: 640px) {
  .row {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 12px;
  }
  .actions {
    width: 100%;
    justify-content: flex-end;
  }
  .field {
    width: 100%;
  }
  .field input, .field select {
    width: 100%;
    box-sizing: border-box;
  }
}
</style>
