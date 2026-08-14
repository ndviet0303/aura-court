<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import type { Venue } from '@/types'

const route = useRoute()
const venue = ref<Venue | null>(null)
const error = ref('')
const copied = ref(false)

onMounted(async () => {
  try { venue.value = await api.get<Venue>(`/venues/${encodeURIComponent(String(route.params.slug))}`) }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không tìm thấy địa điểm.') }
})

async function copyAddress(): Promise<void> {
  if (!venue.value?.address) return
  await navigator.clipboard.writeText(venue.value.address)
  copied.value = true
  window.setTimeout(() => { copied.value = false }, 1800)
}

function openMaps(): void {
  if (venue.value?.google_maps_url) window.open(venue.value.google_maps_url, '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <section v-if="error" class="page-shell not-found"><div class="not-found-art"><strong>404</strong><span>Địa điểm không tồn tại</span></div><div class="eyebrow">Aura Court</div><h1 class="page-title">Không tìm thấy địa điểm.</h1><p class="muted">Địa điểm có thể đã ngừng hoạt động hoặc liên kết không còn hợp lệ.</p><RouterLink to="/courts" class="btn">Xem danh sách sân</RouterLink></section>
  <section v-else-if="venue" class="page-shell venue-directions"><div class="eyebrow">Chỉ đường đến Aura Court</div><h1 class="page-title">{{ venue.name }}</h1><p class="muted">Mở trang này trên điện thoại rồi bấm nút để tiếp tục tới Google Maps.</p><div class="panel venue-directions-card"><span class="feature-icon">location_on</span><h2>Địa chỉ</h2><p class="venue-address">{{ venue.address || 'Địa chỉ đang được cập nhật.' }}</p><div class="actions"><button class="btn" :disabled="!venue.google_maps_url" @click="openMaps">Mở Google Maps <span class="material-symbols-outlined">open_in_new</span></button><button class="btn secondary" :disabled="!venue.address" @click="copyAddress">{{ copied ? 'Đã sao chép' : 'Sao chép địa chỉ' }}</button></div></div></section>
  <section v-else class="page-shell"><p class="muted">Đang tải địa điểm…</p></section>
</template>
