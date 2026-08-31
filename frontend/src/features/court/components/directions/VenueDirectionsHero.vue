<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { Venue, Court } from '../../types/court'

defineProps<{
  venue: Venue
  matchedCourt?: Court | null
}>()
</script>

<template>
  <header class="venue-hero-section">
    <!-- Breadcrumbs -->
    <nav class="directions-breadcrumbs" aria-label="Breadcrumb">
      <RouterLink to="/" class="bc-link">Trang chủ</RouterLink>
      <span class="material-symbols-outlined bc-sep">chevron_right</span>
      <RouterLink to="/courts" class="bc-link">Danh sách sân</RouterLink>
      <template v-if="matchedCourt">
        <span class="material-symbols-outlined bc-sep">chevron_right</span>
        <RouterLink :to="`/courts/${matchedCourt.slug || matchedCourt.id}`" class="bc-link">
          {{ matchedCourt.name }}
        </RouterLink>
      </template>
      <span class="material-symbols-outlined bc-sep">chevron_right</span>
      <span class="bc-current">Chỉ đường & Vị trí</span>
    </nav>

    <!-- Main Title Card -->
    <div class="hero-main-card">
      <div class="hero-info-col">
        <div class="hero-badge-row">
          <span class="brand-eyebrow">Aura Court Club</span>
          <span class="status-open-pill">
            <span class="pulse-dot"></span>
            <span>Mở cửa 06:00 - 23:00</span>
          </span>
        </div>

        <h1 class="venue-title">{{ venue.name }}</h1>
        <p class="venue-subtitle">
          <span class="material-symbols-outlined icon-18">pin_drop</span>
          <span>{{ venue.address || 'Hà Nội' }}</span>
        </p>

        <!-- Amenity Badges -->
        <div class="venue-tags-row">
          <span class="amenity-pill">
            <span class="material-symbols-outlined icon-14">sports_tennis</span>
            <span>Sân Thảm BWF Chuẩn Thi Đấu</span>
          </span>
          <span class="amenity-pill">
            <span class="material-symbols-outlined icon-14">local_parking</span>
            <span>Bãi Đỗ Ô Tô & Xe Máy Miễn Phí</span>
          </span>
          <span class="amenity-pill">
            <span class="material-symbols-outlined icon-14">ac_unit</span>
            <span>Điều Hòa & Phòng Nghỉ VIP</span>
          </span>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.venue-hero-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.directions-breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--muted);
  flex-wrap: wrap;
}

.bc-link {
  color: var(--muted);
  text-decoration: none;
  transition: color 0.15s;
}

.bc-link:hover {
  color: var(--primary);
}

.bc-sep {
  font-size: 14px;
  color: var(--muted);
  opacity: 0.6;
}

.bc-current {
  color: var(--ink);
  font-weight: 700;
}

.hero-main-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 24px;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .hero-main-card {
  background: #1a1518;
  border-color: #382d33;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.hero-info-col {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.hero-badge-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.brand-eyebrow {
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--accent);
}

:global(html.dark) .brand-eyebrow {
  color: #d8b46e;
}

.status-open-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--success-bg, #e8f5e9);
  color: var(--success-text, #2e7d32);
  border: 1px solid rgba(46, 125, 50, 0.25);
  font-size: 11px;
  font-weight: 700;
}

.pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 8px #10b981;
  animation: pulseDot 2s infinite ease-in-out;
}

@keyframes pulseDot {
  0% { transform: scale(0.9); opacity: 0.7; }
  50% { transform: scale(1.3); opacity: 1; }
  100% { transform: scale(0.9); opacity: 0.7; }
}

.venue-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink);
}

.venue-subtitle {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 14px;
  color: var(--muted);
}

.icon-18 {
  font-size: 18px;
  color: var(--primary);
}

:global(html.dark) .icon-18 {
  color: #e58e9f;
}

.venue-tags-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  padding-top: 4px;
}

.amenity-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 8px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--outline);
  font-size: 11.5px;
  font-weight: 600;
  color: var(--muted);
}

:global(html.dark) .amenity-pill {
  background: #231c20;
  border-color: #382d33;
  color: #c4b9be;
}

.icon-14 {
  font-size: 14px;
  color: var(--primary);
}

:global(html.dark) .icon-14 {
  color: #e58e9f;
}
</style>
