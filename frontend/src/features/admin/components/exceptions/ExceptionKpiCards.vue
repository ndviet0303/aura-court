<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { SkeletonLoader } from '@/shared/components'
import type { ExceptionStats } from '../../types/exceptions'

defineProps<{
  stats: ExceptionStats
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <section class="kpi-grid">
    <!-- Card 1: Critical -->
    <div class="kpi-card card-critical">
      <div class="kpi-meta">
        <span class="kpi-label text-critical">{{ t('admin.exceptions.kpiCriticalAlerts') }}</span>
        <div class="kpi-icon-box bg-critical-tint">
          <span class="material-symbols-outlined text-critical">warning</span>
        </div>
      </div>
      <template v-if="loading">
        <SkeletonLoader height="32px" width="60px" borderRadius="6px" />
        <SkeletonLoader height="14px" width="140px" borderRadius="4px" />
      </template>
      <template v-else>
        <div class="kpi-value text-critical">{{ stats.critical_count }}</div>
        <div class="kpi-hint">{{ t('admin.exceptions.kpiCriticalDesc') }}</div>
      </template>
    </div>

    <!-- Card 2: Warning / Pending -->
    <div class="kpi-card card-warning">
      <div class="kpi-meta">
        <span class="kpi-label text-warning">{{ t('admin.exceptions.kpiPendingAction') }}</span>
        <div class="kpi-icon-box bg-warning-tint">
          <span class="material-symbols-outlined text-warning">pending_actions</span>
        </div>
      </div>
      <template v-if="loading">
        <SkeletonLoader height="32px" width="60px" borderRadius="6px" />
        <SkeletonLoader height="14px" width="140px" borderRadius="4px" />
      </template>
      <template v-else>
        <div class="kpi-value text-warning">{{ stats.pending_count }}</div>
        <div class="kpi-hint">{{ t('admin.exceptions.kpiPendingDesc') }}</div>
      </template>
    </div>

    <!-- Card 3: Success / Resolved -->
    <div class="kpi-card card-success">
      <div class="kpi-meta">
        <span class="kpi-label text-success">{{ t('admin.exceptions.kpiResolvedToday') }}</span>
        <div class="kpi-icon-box bg-success-tint">
          <span class="material-symbols-outlined text-success">task_alt</span>
        </div>
      </div>
      <template v-if="loading">
        <SkeletonLoader height="32px" width="60px" borderRadius="6px" />
        <SkeletonLoader height="14px" width="140px" borderRadius="4px" />
      </template>
      <template v-else>
        <div class="kpi-value text-success">{{ stats.resolved_count }}</div>
        <div class="kpi-hint">{{ t('admin.exceptions.kpiResolvedDesc') }}</div>
      </template>
    </div>

    <!-- Card 4: Total Risk Value -->
    <div class="kpi-card card-risk">
      <div class="kpi-meta">
        <span class="kpi-label text-muted">{{ t('admin.exceptions.kpiTotalRiskValue') }}</span>
        <div class="kpi-icon-box bg-primary-tint">
          <span class="material-symbols-outlined text-primary">security</span>
        </div>
      </div>
      <template v-if="loading">
        <SkeletonLoader height="32px" width="120px" borderRadius="6px" />
        <SkeletonLoader height="14px" width="140px" borderRadius="4px" />
      </template>
      <template v-else>
        <div class="kpi-value text-ink font-mono">{{ formatCurrency(stats.total_risk_value) }}</div>
        <div class="kpi-hint">{{ t('admin.exceptions.kpiTotalRiskDesc') }}</div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));
  gap: 16px;
}

.kpi-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 18px;
  border: 1px solid var(--outline, #e1e3e4);
  display: flex;
  flex-direction: column;
  gap: 6px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.card-critical {
  border-top: 3px solid #ba1a1a;
}
.card-warning {
  border-top: 3px solid #f59e0b;
}
.card-success {
  border-top: 3px solid #10b981;
}
.card-risk {
  border-top: 3px solid var(--primary, #78555e);
}

.kpi-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2px;
}

.kpi-label {
  font-size: 13px;
  font-weight: 700;
}

.kpi-icon-box {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bg-critical-tint {
  background: rgba(186, 26, 26, 0.1);
}
.bg-warning-tint {
  background: rgba(245, 158, 11, 0.1);
}
.bg-success-tint {
  background: rgba(16, 185, 129, 0.1);
}
.bg-primary-tint {
  background: rgba(120, 85, 94, 0.1);
}

.text-critical {
  color: #ba1a1a;
}
.text-warning {
  color: #d97706;
}
.text-success {
  color: #10b981;
}
.text-primary {
  color: var(--primary, #78555e);
}
.text-ink {
  color: var(--ink, #191c1d);
}
.text-muted {
  color: var(--muted, #5d5f5f);
}

.kpi-value {
  font-size: 24px;
  font-weight: 800;
  line-height: 1.2;
}

.kpi-hint {
  font-size: 12px;
  color: var(--muted, #817476);
}
</style>
