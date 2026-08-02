<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { errorMessage } from '@/lib/api'
import { useBookingStore } from '@/stores/booking'

const store = useBookingStore(); const payment = computed(() => store.payment); const status = ref('pending_payment'); const error = ref(''); let timer: number | undefined
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value)
const statusLabel = computed(() => ({ pending_payment: 'Chờ thanh toán', confirmed: 'Đã thanh toán', expired: 'Đã hết hạn', cancelled: 'Đã hủy' }[status.value] || status.value))

async function poll() {
  try { const result = await store.refreshPaymentStatus(); if (result) { status.value = result.status } }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể cập nhật trạng thái thanh toán.') }
}

onMounted(() => { store.restorePayment(); poll(); timer = window.setInterval(poll, 5000) })
onUnmounted(() => { if (timer) window.clearInterval(timer) })
</script>

<template><section class="page-shell"><div class="eyebrow">Secure checkout</div><h1 class="page-title">Confirm & pay.</h1><div v-if="payment" class="panel payment"><span class="badge" :class="{ 'badge-success': status === 'confirmed', 'badge-danger': ['expired','cancelled'].includes(status) }">{{ statusLabel }}</span><h2>{{ money(payment.amount) }}</h2><p class="muted">{{ status === 'confirmed' ? 'Thanh toán đã được xác nhận.' : 'Quét QR bằng ứng dụng ngân hàng và nhập đúng nội dung chuyển khoản.' }}</p><img :src="payment.qrUrl" alt="QR thanh toán Aura Court"/><div class="panel" style="text-align:left;background:#f8f9fa"><div class="row"><span class="muted">Ngân hàng</span><strong>{{ payment.bank }}</strong></div><div class="row"><span class="muted">Số tài khoản</span><strong>{{ payment.accountNumber || 'Chưa cấu hình' }}</strong></div><div class="row"><span class="muted">Nội dung</span><strong>{{ payment.code }}</strong></div></div><p v-if="error" class="error">{{ error }}</p><RouterLink to="/booking" class="btn" style="display:inline-block;margin-top:18px">View my booking</RouterLink></div><div v-else class="panel payment"><h2>Chưa có phiên thanh toán</h2><p class="muted">Hãy chọn sân và khung giờ trước.</p><RouterLink to="/courts" class="btn">Choose a court</RouterLink></div></section></template>
