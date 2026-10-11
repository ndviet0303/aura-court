<script setup lang="ts">
import { AuraButton, SkeletonLoader } from '@/shared/components'
import type { MatchmakingStats } from '../types/social'

defineProps<{
  stats: MatchmakingStats
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'switchTab', tab: 'all' | 'open' | 'my_matches' | 'partners'): void
}>()
</script>

<template>
  <header class="matchmaking-hero">
    <div class="hero-top-row">
      <div class="hero-main-content">
        <div class="hero-tag-pill">
          <span class="material-symbols-outlined tag-icon">groups</span>
          <span>{{ $t('matchmaking.eyebrow') }}</span>
        </div>
        <h1 class="hero-title">{{ $t('matchmaking.title') }}</h1>
        <p class="hero-subtitle">
          {{ $t('matchmaking.subtitle') }}
        </p>
      </div>

      <div class="hero-actions">
        <AuraButton to="/matchmaking/create" variant="primary" size="md" icon="add_circle">
          {{ $t('matchmaking.createMatchBtn') }}
        </AuraButton>

        <AuraButton
          variant="secondary"
          size="md"
          icon="person_search"
          @click="emit('switchTab', 'partners')"
        >
          {{ $t('matchmaking.tabPartners') }}
        </AuraButton>
      </div>
    </div>

    <!-- Live Stats Grid -->
    <div class="hero-stats-grid">
      <div class="stat-card">
        <div class="stat-icon-wrap primary">
          <span class="material-symbols-outlined">sports</span>
        </div>
        <div class="stat-info">
          <span class="stat-label">{{ $t('matchmaking.tabAllMatches') }}</span>
          <SkeletonLoader v-if="loading" type="rect" width="50px" height="24px" rounded="6px" />
          <span v-else class="stat-number font-mono">{{ stats.totalMatches }}</span>
        </div>
      </div>

      <div class="stat-card highlight">
        <div class="stat-icon-wrap highlight">
          <span class="material-symbols-outlined">group_add</span>
        </div>
        <div class="stat-info">
          <span class="stat-label">{{ $t('matchmaking.openMatches') }}</span>
          <SkeletonLoader v-if="loading" type="rect" width="50px" height="24px" rounded="6px" />
          <span v-else class="stat-number font-mono">{{ stats.openMatches }}</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap green">
          <span class="material-symbols-outlined">person</span>
        </div>
        <div class="stat-info">
          <span class="stat-label">{{ $t('matchmaking.totalMembers') }}</span>
          <SkeletonLoader v-if="loading" type="rect" width="50px" height="24px" rounded="6px" />
          <span v-else class="stat-number font-mono">{{ stats.activePlayers }}+</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap amber">
          <span class="material-symbols-outlined">stadium</span>
        </div>
        <div class="stat-info">
          <span class="stat-label">{{ $t('matchmaking.totalVenues') }}</span>
          <SkeletonLoader v-if="loading" type="rect" width="50px" height="24px" rounded="6px" />
          <span v-else class="stat-number font-mono">{{ stats.totalVenues }}</span>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.matchmaking-hero {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px 28px;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.hero-top-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.hero-main-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 720px;
}

.hero-tag-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.5px;
  width: fit-content;
}

.tag-icon {
  font-size: 14px;
}

.hero-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.3px;
}

.hero-subtitle {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.5;
  color: var(--muted);
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.hero-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
  padding-top: 16px;
  border-top: 1px solid var(--outline);
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  transition:
    transform 0.2s ease,
    border-color 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  border-color: rgba(219, 39, 119, 0.3);
}

.stat-card.highlight {
  background: rgba(219, 39, 119, 0.05);
  border-color: rgba(219, 39, 119, 0.25);
}

.stat-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.stat-icon-wrap.primary {
  background: rgba(219, 39, 119, 0.12);
  color: var(--pink-dark, #db2777);
}

.stat-icon-wrap.highlight {
  background: rgba(219, 39, 119, 0.18);
  color: var(--pink-dark, #db2777);
}

.stat-icon-wrap.green {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.stat-icon-wrap.amber {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
}

.stat-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat-label {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--muted);
}

.stat-number {
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

@media (max-width: 768px) {
  .matchmaking-hero {
    padding: 18px;
  }
  .hero-title {
    font-size: 20px;
  }
  .hero-stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
