<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
const store = useBookingStore(); const auth = useAuthStore(); const router = useRouter(); const date = ref(new Date().toISOString().slice(0, 10)); const selected = ref<number | null>(null); const loading = ref(false); const error = ref('')
const total = computed(() => store.selectedSlots.reduce((sum, slot) => sum + slot.price, 0))
onMounted(async () => { await store.loadCourts(); if (store.courts[0]) { selected.value = store.courts[0].id; await store.loadAvailability(store.courts[0].id, date.value) } })
async function chooseCourt(id: number) { selected.value = id; await store.loadAvailability(id, date.value) }
async function changeDate() { if (selected.value) await store.loadAvailability(selected.value, date.value) }
async function submit() { if (!auth.user) return router.push({ name: 'login', query: { redirect: '/' } }); if (!store.selectedSlots.length) return; loading.value = true; error.value = ''; try { await store.createBooking(); router.push('/booking') } catch (e: any) { error.value = e.message ?? 'Không thể tạo booking.' } finally { loading.value = false } }
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
</script>
<template>
  <section class="hero"><h1>Đặt sân dễ dàng.</h1><p>Chọn sân, chọn giờ và thanh toán bằng QR ngân hàng. Lịch được giữ trong 15 phút để bạn hoàn tất chuyển khoản.</p></section>
  <div class="row"><div><h2>Chọn sân</h2><p class="muted">Khung giờ mở bán theo ngày và bảng giá hiện hành.</p></div><input type="date" v-model="date" @change="changeDate" /></div>
  <div class="grid" style="margin:16px 0 26px"><button v-for="court in store.courts" :key="court.id" class="card" :style="selected === court.id ? 'border:2px solid #0e6b50' : ''" @click="chooseCourt(court.id)"><h3>{{ court.name }}</h3><p class="muted">{{ court.description || 'Sân cầu lông tiêu chuẩn' }}</p></button></div>
  <section v-if="selected" class="card"><div class="row"><h2>Khung giờ</h2><span class="muted">{{ store.selectedSlots.length }} slot đã chọn</span></div><div class="slot-grid"><button v-for="slot in store.slots" :key="slot.startsAt" class="slot" :class="{ selected: store.selectedSlots.some(s => s.startsAt === slot.startsAt), unavailable: !slot.available }" :disabled="!slot.available" @click="store.toggle(slot)">{{ new Date(slot.startsAt).toLocaleTimeString('vi-VN',{hour:'2-digit',minute:'2-digit'}) }}<br><small>{{ money(slot.price) }}</small></button></div><p v-if="error" class="error">{{ error }}</p><div class="row"><strong>Tổng: {{ money(total) }}</strong><button class="btn" :disabled="loading || !store.selectedSlots.length" @click="submit">{{ auth.user ? 'Giữ sân & thanh toán' : 'Đăng nhập để đặt sân' }}</button></div></section>
</template>
