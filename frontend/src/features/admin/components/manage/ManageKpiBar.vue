<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  totalCourtsCount: number
  activeCourtsCount: number
  totalBlocksCount: number
  pendingRefundsCount: number
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="manage-kpi-bar">
    <template v-if="loading">
      <div v-for="i in 4" :key="i" class="kpi-card skeleton-card">
        <div class="kpi-top-row">
          <div class="skeleton-item skeleton-icon"></div>
          <div class="skeleton-item skeleton-lbl"></div>
        </div>
        <div class="skeleton-item skeleton-val"></div>
      </div>
    </template>

    <template v-else>
      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon">sports_tennis</span>
          <span class="kpi-lbl">{{ t('admin.manage.totalCourts') }}</span>
        </div>
        <strong class="kpi-num">{{ totalCourtsCount }}</strong>
      </div>

      <div class="kpi-card text-green">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-green">check_circle</span>
          <span class="kpi-lbl">{{ t('admin.manage.activeCourts') }}</span>
        </div>
        <strong class="kpi-num">{{ activeCourtsCount }}</strong>
      </div>

      <div class="kpi-card text-yellow">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-yellow">build</span>
          <span class="kpi-lbl">{{ t('admin.manage.maintenanceBlocks') }}</span>
        </div>
        <strong class="kpi-num">{{ totalBlocksCount }}</strong>
      </div>

      <div class="kpi-card text-pink">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-pink">currency_exchange</span>
          <span class="kpi-lbl">{{ t('admin.manage.pendingRefunds') }}</span>
        </div>
        <strong class="kpi-num">{{ pendingRefundsCount }}</strong>
      </div>
    </template>
  </div>
</template>

<style scoped>
.manage-kpi-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px 20px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.kpi-top-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.kpi-icon {
  font-size: 18px;
  color: var(--muted);
}

.kpi-lbl {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.kpi-num {
  font-size: 22px;
  font-weight: 800;
}

.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }
.text-pink { color: var(--pink-dark, #db2777); }

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
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

@media (max-width: 900px) {
  .manage-kpi-bar {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
