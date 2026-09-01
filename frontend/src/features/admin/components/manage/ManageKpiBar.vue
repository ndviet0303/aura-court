<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  totalCourtsCount: number
  activeCourtsCount: number
  totalBlocksCount: number
  pendingRefundsCount: number
}>()

const { t } = useI18n()
</script>

<template>
  <div class="manage-kpi-bar">
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

@media (max-width: 900px) {
  .manage-kpi-bar {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
