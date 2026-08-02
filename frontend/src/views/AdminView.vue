<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, errorMessage } from '@/lib/api'

type AdminReport = { bookings: number; confirmed: number; revenue: number; pendingRefunds: number }
type AdminBooking = { id: number; code: string; status: string; total_amount: number; user?: { name: string }; court?: { name: string } }

const report = ref<AdminReport | null>(null)
const bookings = ref<AdminBooking[]>([])
const error = ref('')
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)

onMounted(async () => {
  try {
    report.value = await api.get<AdminReport>('/admin/reports')
    const result = await api.get<{ data: AdminBooking[] }>('/admin/bookings')
    bookings.value = result.data
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không tải được dữ liệu quản trị.')
  }
})
</script>
<template><h1>Quản trị</h1><p v-if="error" class="error">{{error}}</p><div v-if="report" class="grid"><div class="card"><p class="muted">Tổng booking</p><h2>{{report.bookings}}</h2></div><div class="card"><p class="muted">Đã xác nhận</p><h2>{{report.confirmed}}</h2></div><div class="card"><p class="muted">Doanh thu</p><h2>{{money(report.revenue)}}</h2></div><div class="card"><p class="muted">Chờ hoàn</p><h2>{{money(report.pendingRefunds)}}</h2></div></div><section class="card" style="margin-top:22px"><h2>Booking gần đây</h2><div class="table-wrap"><table class="table"><thead><tr><th>Mã</th><th>Khách</th><th>Sân</th><th>Trạng thái</th><th>Tổng tiền</th></tr></thead><tbody><tr v-for="booking in bookings" :key="booking.id"><td>{{booking.code}}</td><td>{{booking.user?.name}}</td><td>{{booking.court?.name}}</td><td>{{booking.status}}</td><td>{{money(booking.total_amount)}}</td></tr></tbody></table></div></section></template>
