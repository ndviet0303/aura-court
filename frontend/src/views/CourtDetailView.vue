<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'

const route = useRoute()
const store = useBookingStore()
const court = computed(() => store.courts.find((item) => item.id === Number(route.params.id)) || store.courts[0])

onMounted(async () => {
  try { await store.loadCourts() } catch { /* store exposes the error */ }
})

function openMaps(): void {
  if (court.value?.venue.google_maps_url) window.open(court.value.venue.google_maps_url, '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <section class="page-shell">
    <p v-if="store.error" class="error">{{ store.error }}</p>
    <template v-if="court">
      <div class="eyebrow">{{ court.venue.name }} / {{ court.name }}</div>
      <div class="detail-hero">
        <div>
          <h1 class="page-title">{{ court.name }}</h1>
          <p class="muted">{{ court.description || 'Sân cầu lông trong nhà với ánh sáng chuyên nghiệp và không gian thoải mái.' }}</p>
          <div class="detail-stats"><span><strong>4.9</strong> đánh giá</span><span><strong>60 phút</strong> khung giờ</span><span><strong>06–23</strong> mở cửa</span></div>
          <div class="actions">
            <RouterLink :to="`/courts/${court.id}/schedule`" class="btn">Chọn giờ đặt <span class="material-symbols-outlined">arrow_forward</span></RouterLink>
            <button v-if="court.venue.google_maps_url" class="btn secondary" @click="openMaps">Mở Google Maps</button>
          </div>
        </div>
        <img class="detail-image" :src="court.image_url || '/aura/court-4.jpg'" :alt="court.name">
      </div>
      <div v-if="court.venue.address" class="panel venue-summary">
        <div><div class="eyebrow">Địa điểm</div><h2>{{ court.venue.name }}</h2><p class="muted">{{ court.venue.address }}</p></div>
        <RouterLink :to="`/venues/${court.venue.slug}/directions`" class="link-button">Xem trang chỉ đường</RouterLink>
      </div>
      <div class="feature-row" style="margin-top:28px"><article class="feature"><span class="feature-icon">light_mode</span><h3>Ánh sáng chuyên nghiệp</h3><p class="muted">Không chói mắt, phù hợp cho mọi trận đấu.</p></article><article class="feature"><span class="feature-icon">air</span><h3>Điều hòa ổn định</h3><p class="muted">Không gian mát và ổn định suốt buổi chơi.</p></article><article class="feature"><span class="feature-icon">local_parking</span><h3>Di chuyển thuận tiện</h3><p class="muted">Bãi xe và khu nghỉ ngay tại câu lạc bộ.</p></article></div>
    </template>
  </section>
</template>
