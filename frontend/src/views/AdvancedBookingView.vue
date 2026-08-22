<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiV2, errorMessage } from '@/lib/api'

type Invoice = { id: number; amount: number; status: string; due_at: string }
type Series = { id: number; frequency: string; status: string; total_amount: number; invoices: Invoice[] }
type Listing = { id: number; asking_price: number; original_price: number; cutoff_at: string; seller: { name: string }; entitlement: { starts_at: string; court?: { name: string } } }

const series = ref<Series[]>([])
const listings = ref<Listing[]>([])
const form = ref({ court_id: '', frequency: 'weekly', starts_at: '', until: '', occurrences: 4 })
const splitForm = ref({ reservation_id: '', shares: '100000,100000' })
const error = ref('')
const notice = ref('')
const loading = ref(false)
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
const date = (value: string) => new Date(value).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' })

async function load(): Promise<void> {
  try {
    const [seriesResponse, listingsResponse] = await Promise.all([
      apiV2.get<{ data: Series[] }>('/recurring-series'),
      apiV2.get<{ data: Listing[] }>('/marketplace/listings'),
    ])
    series.value = seriesResponse.data || []
    listings.value = listingsResponse.data || []
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được các luồng đặt sân nâng cao.') }
}

async function createSeries(): Promise<void> {
  loading.value = true
  try {
    await apiV2.post('/recurring-series', { court_id: Number(form.value.court_id), frequency: form.value.frequency, starts_at: new Date(form.value.starts_at).toISOString(), until: form.value.until ? new Date(form.value.until).toISOString() : undefined, occurrences: form.value.occurrences })
    notice.value = 'Đã xác nhận toàn bộ lịch định kỳ và tạo hóa đơn tháng.'
    await load()
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể tạo lịch định kỳ.') }
  finally { loading.value = false }
}

async function payInvoice(invoice: Invoice): Promise<void> {
  try {
    await apiV2.post(`/monthly-invoices/${invoice.id}/pay`, { payment_reference: `web-${invoice.id}-${Date.now()}` })
    notice.value = 'Đã ghi nhận thanh toán hóa đơn.'
    await load()
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể thanh toán hóa đơn.') }
}

async function createSplit(): Promise<void> {
  try {
    const shares = splitForm.value.shares.split(',').map((amount) => ({ amount: Number(amount.trim()) }))
    await apiV2.post(`/reservations/${splitForm.value.reservation_id}/split`, { shares })
    notice.value = 'Đã tạo nhóm chia tiền; mỗi người có 15 phút để thanh toán.'
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể tạo nhóm chia tiền.') }
}

async function purchase(listing: Listing): Promise<void> {
  try {
    const result = await apiV2.post<{ transfer: { status: string } }>(`/marketplace/listings/${listing.id}/purchase`, { buyer_payment_reference: `market-${listing.id}-${Date.now()}` })
    notice.value = result.transfer.status === 'completed' ? 'Đã chuyển quyền đặt sân thành công.' : 'Thanh toán cần được xử lý bù trừ.'
    await load()
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể mua suất đặt sân.') }
}

onMounted(load)
</script>

<template>
  <main class="page-shell">
    <section class="row"><div><div class="eyebrow">API v2 · Advanced booking</div><h1 class="page-title">Lịch định kỳ & marketplace</h1><p class="muted">Lịch dài hạn, chia tiền và chuyển suất đặt sân có kiểm soát.</p></div></section>
    <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>
    <section class="card" style="margin-top: 18px"><h2>Tạo lịch định kỳ</h2><form class="row" style="margin-top: 14px; align-items: end" @submit.prevent="createSeries"><label class="field">Mã sân<input v-model="form.court_id" type="number" min="1" required /></label><label class="field">Lặp<select v-model="form.frequency"><option value="weekly">Hàng tuần</option><option value="monthly">Hàng tháng</option></select></label><label class="field">Bắt đầu<input v-model="form.starts_at" type="datetime-local" required /></label><label class="field">Đến ngày<input v-model="form.until" type="date" /></label><label class="field">Số lần<input v-model.number="form.occurrences" type="number" min="1" max="104" /></label><button class="btn" :disabled="loading">Xác nhận toàn bộ lịch</button></form></section>
    <section class="card" style="margin-top: 18px"><h2>Hóa đơn định kỳ</h2><div v-for="item in series" :key="item.id" class="panel" style="margin-top: 12px"><div class="row"><strong>{{ item.frequency }} · {{ item.status }}</strong><span>{{ money(item.total_amount) }}</span></div><div v-for="invoice in item.invoices" :key="invoice.id" class="row" style="padding-top: 10px"><span>Hóa đơn #{{ invoice.id }} · hạn {{ date(invoice.due_at) }}</span><div class="actions"><strong>{{ money(invoice.amount) }}</strong><button v-if="invoice.status === 'pending'" class="link-button" @click="payInvoice(invoice)">Thanh toán</button><span v-else class="badge">{{ invoice.status }}</span></div></div></div><p v-if="!series.length" class="muted">Chưa có lịch định kỳ.</p></section>
    <section class="card" style="margin-top: 18px"><h2>Chia tiền booking</h2><form class="row" style="margin-top: 14px; align-items: end" @submit.prevent="createSplit"><label class="field">Mã reservation<input v-model="splitForm.reservation_id" type="number" required /></label><label class="field" style="min-width: 260px">Các phần tiền, cách nhau bằng dấu phẩy<input v-model="splitForm.shares" required /></label><button class="btn">Tạo nhóm chia</button></form><p class="muted" style="margin-top: 10px">Tổng các phần phải đúng bằng giá booking, từ 2 đến 8 phần.</p></section>
    <section class="card" style="margin-top: 18px"><h2>Suất đặt sân đang bán</h2><div v-for="listing in listings" :key="listing.id" class="row" style="padding: 14px 0; border-bottom: 1px solid var(--line)"><div><strong>{{ listing.entitlement.court?.name || 'Sân' }} · {{ date(listing.entitlement.starts_at) }}</strong><p class="muted">Người bán: {{ listing.seller.name }} · hạn {{ date(listing.cutoff_at) }}</p></div><div class="actions"><strong>{{ money(listing.asking_price) }}</strong><button class="btn" @click="purchase(listing)">Mua suất</button></div></div><p v-if="!listings.length" class="muted">Chưa có suất nào đang bán.</p></section>
  </main>
</template>
