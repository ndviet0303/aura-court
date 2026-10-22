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
      <RouterLink to="/" class="bc-link">{{ $t('directions.breadcrumbHome') }}</RouterLink>
      <span class="material-symbols-outlined bc-sep">chevron_right</span>
      <RouterLink to="/courts" class="bc-link">{{ $t('directions.breadcrumbCourts') }}</RouterLink>
      <template v-if="matchedCourt">
        <span class="material-symbols-outlined bc-sep">chevron_right</span>
        <RouterLink :to="`/courts/${matchedCourt.id}`" class="bc-link">
          {{ matchedCourt.code }}
        </RouterLink>
      </template>
      <span class="material-symbols-outlined bc-sep">chevron_right</span>
      <span class="bc-current">{{ $t('directions.breadcrumbCurrent') }}</span>
    </nav>

    <!-- Main Title Card -->
    <div class="hero-main-card">
      <div class="hero-info-col">
        <div class="hero-badge-row">
          <span class="brand-eyebrow">Aura Court Club</span>
          <span class="status-open-pill">
            <span class="pulse-dot"></span>
            <span>{{ $t('directions.openStatus') }}</span>
          </span>
        </div>

        <h1 class="venue-title">{{ venue.name }}</h1>
        <p class="venue-subtitle">
          <span class="material-symbols-outlined icon-18">pin_drop</span>
          <span>{{ venue.address || 'Hà Nội' }}</span>
        </p>

        <!-- The venue's saved photos; its declared amenities show below the map -->
        <div v-if="venue.images?.length" class="venue-photos-row">
          <img
            v-for="src in venue.images"
            :key="src"
            :src="src"
            :alt="venue.name"
            class="venue-photo"
            loading="lazy"
          />
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
  0% {
    transform: scale(0.9);
    opacity: 0.7;
  }
  50% {
    transform: scale(1.3);
    opacity: 1;
  }
  100% {
    transform: scale(0.9);
    opacity: 0.7;
  }
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

.venue-photos-row {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-top: 4px;
}

.venue-photo {
  width: 120px;
  height: 80px;
  flex-shrink: 0;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid var(--outline);
}
</style>
