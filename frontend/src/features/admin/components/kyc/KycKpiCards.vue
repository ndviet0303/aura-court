<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { KycStats } from '../../types/kyc'
import { SkeletonLoader } from '@/shared/components'

withDefaults(
  defineProps<{
    stats: KycStats
    loading?: boolean
  }>(),
  {
    loading: false,
  }
)

const { t } = useI18n()
</script>

<template>
  <div class="kyc-kpi-grid">
    <!-- Skeleton State -->
    <template v-if="loading">
      <div v-for="i in 4" :key="i" class="kpi-card skeleton-card">
        <SkeletonLoader type="text" width="60%" height="14px" />
        <div class="kpi-value-row">
          <SkeletonLoader type="title" width="40%" height="28px" />
          <SkeletonLoader type="rect" width="50px" height="20px" rounded="999px" />
        </div>
        <SkeletonLoader type="text" width="90%" height="12px" />
      </div>
    </template>

    <!-- Loaded State -->
    <template v-else>
      <!-- Card 1: Pending -->
      <div class="kpi-card">
        <div class="kpi-header">
          <div class="kpi-label">{{ t('admin.kyc.kpiPending') }}</div>
          <span class="material-symbols-outlined kpi-icon">hourglass_top</span>
        </div>
        <div class="kpi-value-row">
          <span class="kpi-number">{{ stats.pending_count }}</span>
          <span class="kpi-badge warning">{{ t('admin.kyc.kpiPendingBadge') }}</span>
        </div>
        <div class="kpi-desc">{{ t('admin.kyc.kpiPendingDesc') }}</div>
      </div>

      <!-- Card 2: Confidence Rate -->
      <div class="kpi-card">
        <div class="kpi-header">
          <div class="kpi-label">{{ t('admin.kyc.kpiConfidence') }}</div>
          <span class="material-symbols-outlined kpi-icon success">verified_user</span>
        </div>
        <div class="kpi-value-row">
          <span class="kpi-number">{{ stats.ai_confidence_rate }}%</span>
          <span class="kpi-badge success">{{ t('admin.kyc.kpiConfidenceBadge') }}</span>
        </div>
        <div class="kpi-desc">{{ t('admin.kyc.kpiConfidenceDesc') }}</div>
      </div>

      <!-- Card 3: Avg Processing Time -->
      <div class="kpi-card">
        <div class="kpi-header">
          <div class="kpi-label">{{ t('admin.kyc.kpiAvgTime') }}</div>
          <span class="material-symbols-outlined kpi-icon">speed</span>
        </div>
        <div class="kpi-value-row">
          <span class="kpi-number">{{ stats.avg_processing_time_minutes }}</span>
          <span class="kpi-unit">{{ t('admin.kyc.kpiAvgTimeUnit') }}</span>
        </div>
        <div class="kpi-desc">{{ t('admin.kyc.kpiAvgTimeDesc') }}</div>
      </div>

      <!-- Card 4: High Risk / Flagged -->
      <div class="kpi-card alert-card">
        <div class="kpi-header">
          <div class="kpi-label alert-text">{{ t('admin.kyc.kpiFlagged') }}</div>
          <span class="material-symbols-outlined kpi-icon alert-text">warning</span>
        </div>
        <div class="kpi-value-row">
          <span class="kpi-number alert-text">{{ stats.flagged_count }}</span>
          <span class="kpi-badge danger">{{ t('admin.kyc.kpiFlaggedBadge') }}</span>
        </div>
        <div class="kpi-desc alert-desc">{{ t('admin.kyc.kpiFlaggedDesc') }}</div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.kyc-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

@media (max-width: 1024px) {
  .kyc-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .kyc-kpi-grid {
    grid-template-columns: 1fr;
  }
}

.kpi-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 16px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}

.kpi-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border-color: var(--primary, #78555e);
}

.kpi-card.skeleton-card {
  gap: 10px;
}

.kpi-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kpi-icon {
  font-size: 20px;
  color: var(--muted, #817476);
}

.kpi-icon.success {
  color: #10b981;
}

.alert-card {
  background: var(--surface, #ffffff);
  border-color: rgba(239, 68, 68, 0.3);
}

.kpi-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
}

.alert-text {
  color: #ef4444 !important;
}

.kpi-value-row {
  display: flex;
  align-items: baseline;
  gap: 10px;
}

.kpi-number {
  font-size: 28px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  line-height: 1.1;
  letter-spacing: -0.02em;
}

.kpi-unit {
  font-size: 13px;
  color: var(--muted, #5d5f5f);
  font-weight: 500;
}

.kpi-desc {
  font-size: 12px;
  color: var(--muted, #817476);
  margin-top: 2px;
}

.alert-desc {
  color: #ef4444;
  opacity: 0.9;
}

.kpi-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
}

.kpi-badge.success {
  background: #d1fae5;
  color: #059669;
}

.kpi-badge.warning {
  background: #fef3c7;
  color: #d97706;
}

.kpi-badge.danger {
  background: #fee2e2;
  color: #dc2626;
}
</style>

