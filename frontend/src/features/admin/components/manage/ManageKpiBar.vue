<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  totalCourtsCount: number
  activeCourtsCount: number
  pausedCourtsCount: number
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="manage-kpi-bar">
    <template v-if="loading">
      <div v-for="i in 3" :key="i" class="kpi-card skeleton-card">
        <div class="skeleton-icon-box skeleton-item"></div>
        <div class="kpi-content">
          <div class="skeleton-item skeleton-lbl"></div>
          <div class="skeleton-item skeleton-val"></div>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="kpi-card kpi-total">
        <div class="kpi-icon-box total-icon-box">
          <span class="material-symbols-outlined icon-22">sports_tennis</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-lbl">{{ t('admin.manage.totalCourts') }}</span>
          <strong class="kpi-num">{{ totalCourtsCount }}</strong>
        </div>
      </div>

      <div class="kpi-card kpi-active">
        <div class="kpi-icon-box active-icon-box">
          <span class="material-symbols-outlined icon-22">check_circle</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-lbl">{{ t('admin.manage.activeCourts') }}</span>
          <strong class="kpi-num text-green">{{ activeCourtsCount }}</strong>
        </div>
      </div>

      <div class="kpi-card kpi-maintenance">
        <div class="kpi-icon-box maintenance-icon-box">
          <span class="material-symbols-outlined icon-22">pause_circle</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-lbl">{{ t('admin.manage.pausedCourts') }}</span>
          <strong class="kpi-num text-yellow">{{ pausedCourtsCount }}</strong>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.manage-kpi-bar {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.kpi-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px 22px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
  border-color: var(--border);
}

:global(html.dark) .kpi-card {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
  border-color: rgba(255, 255, 255, 0.08);
}

:global(html.dark) .kpi-card:hover {
  border-color: var(--accent, #c9a15a);
}

.kpi-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.total-icon-box {
  background: rgba(107, 45, 63, 0.08);
  color: var(--primary, #6b2d3f);
}

:global(html.dark) .total-icon-box {
  background: rgba(201, 161, 90, 0.12);
  color: var(--accent, #c9a15a);
}

.active-icon-box {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

:global(html.dark) .active-icon-box {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
}

.maintenance-icon-box {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

:global(html.dark) .maintenance-icon-box {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
}

.kpi-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.kpi-lbl {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.6px;
}

.kpi-num {
  font-size: 26px;
  font-weight: 800;
  color: var(--ink);
  line-height: 1.1;
}

.text-green {
  color: #10b981;
}
:global(html.dark) .text-green {
  color: #34d399;
}

.text-yellow {
  color: #f59e0b;
}
:global(html.dark) .text-yellow {
  color: #fbbf24;
}

.icon-22 {
  font-size: 22px;
}

.skeleton-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  flex-shrink: 0;
}
/* Shimmer animation */
.skeleton-card {
  pointer-events: none;
}

.skeleton-item {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
  border-radius: 6px;
}

.skeleton-item::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 209, 220, 0.45) 45%,
    rgba(255, 255, 255, 0.8) 55%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer-wave 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

:global(html.dark) .skeleton-item {
  background-color: var(--soft, #2b2e36);
}

:global(html.dark) .skeleton-item::after {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(120, 85, 94, 0.35) 45%,
    rgba(255, 255, 255, 0.1) 55%,
    rgba(255, 255, 255, 0) 100%
  );
}

.skeleton-icon {
  width: 18px;
  height: 18px;
  border-radius: 4px;
}

.skeleton-lbl {
  width: 90px;
  height: 12px;
}

.skeleton-val {
  width: 50px;
  height: 24px;
  margin-top: 4px;
}

@keyframes shimmer-wave {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

@media (max-width: 900px) {
  .manage-kpi-bar {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
