<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps<{
  totalCount: number
  heldCount: number
  confirmedCount: number
  cancelledCount: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'refresh'): void
  (e: 'create-quote'): void
}>()
</script>

<template>
  <header class="hero-header">
    <div class="hero-content">
      <div class="category-eyebrow">
        <span class="material-symbols-outlined eyebrow-icon">sports_tennis</span>
        <span class="font-mono">{{ t('reservations.hero.eyebrow') }}</span>
      </div>
      <h1 class="hero-title">{{ t('reservations.hero.title') }}</h1>
      <p class="hero-description">
        {{ t('reservations.hero.desc') }}
      </p>

      <!-- Stat Badges Strip -->
      <div class="stats-strip">
        <div class="stat-pill">
          <span class="stat-label">{{ t('reservations.hero.statTotal') }}</span>
          <span class="stat-value font-mono">{{ totalCount }}</span>
        </div>
        <div class="stat-pill held">
          <span class="pulse-dot"></span>
          <span class="stat-label">{{ t('reservations.hero.statHeld') }}</span>
          <span class="stat-value font-mono">{{ heldCount }}</span>
        </div>
        <div class="stat-pill confirmed">
          <span class="material-symbols-outlined stat-icon">verified</span>
          <span class="stat-label">{{ t('reservations.hero.statConfirmed') }}</span>
          <span class="stat-value font-mono">{{ confirmedCount }}</span>
        </div>
        <div v-if="cancelledCount > 0" class="stat-pill muted">
          <span class="stat-label">{{ t('reservations.hero.statCancelled') }}</span>
          <span class="stat-value font-mono">{{ cancelledCount }}</span>
        </div>
      </div>
    </div>

    <div class="hero-actions">
      <button
        class="btn btn-outline-refresh"
        :disabled="loading"
        @click="emit('refresh')"
        :title="t('reservations.hero.refreshTitle')"
      >
        <span class="material-symbols-outlined" :class="{ spinning: loading }">refresh</span>
        <span>{{ t('reservations.hero.refresh') }}</span>
      </button>
      <button class="btn btn-primary" @click="emit('create-quote')">
        <span class="material-symbols-outlined">calculate</span>
        <span>{{ t('reservations.hero.createQuote') }}</span>
      </button>
    </div>
  </header>
</template>

<style scoped>
.hero-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 24px;
  padding: 28px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 24px;
  box-shadow: 0 4px 20px rgba(107, 45, 63, 0.04);
  position: relative;
  overflow: hidden;
}

.hero-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--primary, #6b2d3f) 0%, var(--accent, #c9a15a) 100%);
}

.hero-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 680px;
}

.category-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 11.5px;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: var(--primary, #6b2d3f);
  text-transform: uppercase;
}

.eyebrow-icon {
  font-size: 16px;
}

.hero-title {
  font-size: 26px;
  font-weight: 800;
  line-height: 1.25;
  margin: 0;
  color: var(--ink);
  letter-spacing: -0.02em;
}

.hero-description {
  font-size: 14px;
  line-height: 1.6;
  color: var(--muted);
  margin: 0;
}

.stats-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 6px;
}

.stat-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 9999px;
  font-size: 12.5px;
  color: var(--ink);
}

.stat-label {
  color: var(--muted);
}

.stat-value {
  font-weight: 800;
  color: var(--ink);
}

.stat-pill.held {
  background: var(--warning-bg, rgba(239, 108, 0, 0.1));
  border-color: rgba(239, 108, 0, 0.3);
  color: var(--warning-text, #ef6c00);
}
.stat-pill.held .stat-value {
  color: var(--warning-text, #ef6c00);
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #f59e0b;
  animation: pulse-ring 1.8s infinite;
}

@keyframes pulse-ring {
  0% {
    transform: scale(0.95);
    opacity: 1;
  }
  50% {
    transform: scale(1.3);
    opacity: 0.6;
  }
  100% {
    transform: scale(0.95);
    opacity: 1;
  }
}

.stat-pill.confirmed {
  background: var(--success-bg, rgba(46, 125, 50, 0.1));
  border-color: rgba(46, 125, 50, 0.3);
  color: var(--success-text, #2e7d32);
}
.stat-pill.confirmed .stat-value {
  color: var(--success-text, #2e7d32);
}

.stat-icon {
  font-size: 15px;
}

.stat-pill.muted {
  opacity: 0.8;
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.btn-outline-refresh {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: 9999px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-outline-refresh:hover:not(:disabled) {
  background: var(--surface-subtle);
  border-color: var(--ink);
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}

@media (max-width: 800px) {
  .hero-header {
    flex-direction: column;
    padding: 20px;
  }
  .hero-actions {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>
