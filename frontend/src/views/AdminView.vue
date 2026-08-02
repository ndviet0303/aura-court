<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api, errorMessage } from '@/lib/api'
import DatePicker from '@/components/DatePicker.vue'
import { useAuthStore } from '@/stores/auth'

type AdminReport = { bookings: number; confirmed: number; revenue: number; pendingRefunds: number }
type AdminBooking = { id: number; code: string; status: string; total_amount: number; user?: { name: string; email?: string }; court?: { name: string }; slots?: { starts_at: string }[] }
type ReviewPayment = { id: number; amount: number; transfer_code?: string; booking?: { code: string; user?: { email: string }; court?: { name: string } } }

const report = ref<AdminReport | null>(null)
const bookings = ref<AdminBooking[]>([])
const payments = ref<ReviewPayment[]>([])
const from = ref('')
const to = ref('')
const status = ref('')
const error = ref('')
const loading = ref(false)
const notice = ref('')
const auth = useAuthStore()
const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email.endsWith('@demo.auracourt.vn'))
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
const statusLabel = (value: string) => ({ pending_payment: 'Chờ thanh toán', confirmed: 'Đã xác nhận', cancelled: 'Đã hủy', expired: 'Đã hết hạn', refunded: 'Đã hoàn tiền', no_show: 'Vắng mặt' }[value] || value)
const csvUrl = computed(() => '/api/v1/admin/reports.csv?from=' + encodeURIComponent(from.value) + '&to=' + encodeURIComponent(to.value))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const query = new URLSearchParams()
    if (from.value) query.set('from', from.value)
    if (to.value) query.set('to', to.value)
    if (status.value) query.set('status', status.value)
    report.value = await api.get<AdminReport>('/admin/reports?' + query)
    bookings.value = (await api.get<{ data: AdminBooking[] }>('/admin/bookings?' + query)).data
    payments.value = (await api.get<{ data: ReviewPayment[] }>('/admin/payments')).data
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không tải được dữ liệu quản trị.')
  } finally {
    loading.value = false
  }
}

async function reviewPayment(payment: ReviewPayment, nextStatus: 'paid' | 'rejected') {
  if (isDemoAdmin.value) {
    payments.value = payments.value.filter((item) => item.id !== payment.id)
    bookings.value = bookings.value.map((booking) => booking.code === payment.booking?.code ? { ...booking, status: nextStatus === 'paid' ? 'confirmed' : 'cancelled' } : booking)
    notice.value = 'Đã mô phỏng xử lý giao dịch, không ghi vào dữ liệu thật.'
    return
  }
  try {
    await api.patch('/admin/payments/' + payment.id, { status: nextStatus })
    await load()
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể xử lý giao dịch.') }
}

async function markNoShow(booking: AdminBooking) {
  if (isDemoAdmin.value) { bookings.value = bookings.value.map((item) => item.id === booking.id ? { ...item, status: 'no_show' } : item); notice.value = 'Đã mô phỏng đánh dấu vắng mặt, không ghi vào dữ liệu thật.'; return }
  try {
    await api.patch('/admin/bookings/' + booking.id + '/no-show', {})
    await load()
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể đánh dấu vắng mặt.') }
}

onMounted(load)
</script>

<template>
  <section class="page-shell">
    <div class="row">
      <div><div class="eyebrow">Vận hành</div><h1 class="page-title">Quản trị</h1><p class="muted">Theo dõi lịch đặt sân, doanh thu, giao dịch cần kiểm tra và vắng mặt.</p></div>
      <a class="btn" :href="csvUrl">Tải CSV</a>
    </div>
    <form class="panel admin-form" @submit.prevent="load">
      <div class="admin-form-grid compact"><label>Từ ngày<DatePicker v-model="from" /></label><label>Đến ngày<DatePicker v-model="to" /></label><label>Trạng thái<select v-model="status"><option value="">Tất cả</option><option value="pending_payment">Chờ thanh toán</option><option value="confirmed">Đã xác nhận</option><option value="cancelled">Đã hủy</option><option value="no_show">Vắng mặt</option></select></label><button class="btn" :disabled="loading">{{ loading ? 'Đang tải…' : 'Lọc' }}</button></div>
    </form>
    <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>
    <div v-if="report" class="grid"><div class="card"><p class="muted">Tổng lịch đặt</p><h2>{{ report.bookings }}</h2></div><div class="card"><p class="muted">Đã xác nhận</p><h2>{{ report.confirmed }}</h2></div><div class="card"><p class="muted">Doanh thu</p><h2>{{ money(report.revenue) }}</h2></div><div class="card"><p class="muted">Chờ hoàn</p><h2>{{ money(report.pendingRefunds) }}</h2></div></div>
    <section class="card" style="margin-top:22px"><h2>Giao dịch cần kiểm tra</h2><p v-if="!payments.length" class="muted">Không có giao dịch chờ xử lý.</p><div v-else class="table-wrap"><table class="table"><thead><tr><th>Mã đặt sân</th><th>Khách</th><th>Số tiền</th><th>Thao tác</th></tr></thead><tbody><tr v-for="payment in payments" :key="payment.id"><td>{{ payment.booking?.code || 'Không khớp' }}</td><td>{{ payment.booking?.user?.email || '—' }}</td><td>{{ money(payment.amount) }}</td><td class="actions"><button class="link-button" @click="reviewPayment(payment, 'paid')">Xác nhận</button><button class="link-button danger" @click="reviewPayment(payment, 'rejected')">Từ chối</button></td></tr></tbody></table></div></section>
    <section class="card" style="margin-top:22px"><h2>Lịch đặt sân gần đây</h2><div class="table-wrap"><table class="table"><thead><tr><th>Mã</th><th>Khách</th><th>Sân</th><th>Trạng thái</th><th>Tổng tiền</th><th></th></tr></thead><tbody><tr v-for="booking in bookings" :key="booking.id"><td>{{ booking.code }}</td><td>{{ booking.user?.name }}</td><td>{{ booking.court?.name }}</td><td>{{ statusLabel(booking.status) }}</td><td>{{ money(booking.total_amount) }}</td><td><button v-if="booking.status === 'confirmed'" class="link-button danger" @click="markNoShow(booking)">Vắng mặt</button></td></tr></tbody></table></div></section>
  </section>
</template>
