<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { SkeletonLoader } from '@/shared/components'
import type { ContractStats } from '../../types/contract'

defineProps<{
  stats: ContractStats
  loading: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="contracts-kpi-grid">
    <!-- Active Contracts KPI -->
    <div class="kpi-card">
      <div class="kpi-top-row">
        <span class="material-symbols-outlined kpi-icon">contract</span>
        <span class="kpi-label">{{ t('admin.contracts.activeContracts') }}</span>
      </div>
      <div v-if="loading" class="kpi-skeleton-wrap">
        <SkeletonLoader height="26px" width="60%" />
        <SkeletonLoader height="14px" width="40%" class="mt-1" />
      </div>
      <template v-else>
        <strong class="kpi-value">{{
          t('admin.contracts.activeContractsCount', { count: stats.active_contracts })
        }}</strong>
        <span class="kpi-trend text-green">{{ t('admin.contracts.activeTrend') }}</span>
      </template>
    </div>

    <!-- Monthly Recurring Revenue (MRR) -->
    <div class="kpi-card">
      <div class="kpi-top-row">
        <span class="material-symbols-outlined kpi-icon text-pink">payments</span>
        <span class="kpi-label">{{ t('admin.contracts.mrrLabel') }}</span>
      </div>
      <div v-if="loading" class="kpi-skeleton-wrap">
        <SkeletonLoader height="26px" width="70%" />
        <SkeletonLoader height="14px" width="50%" class="mt-1" />
      </div>
      <template v-else>
        <strong class="kpi-value text-pink">{{ formatCurrency(stats.mrr_amount) }}</strong>
        <span class="kpi-trend">{{ t('admin.contracts.mrrContribution') }}</span>
      </template>
    </div>

    <!-- Overdue Billing Cycles -->
    <div class="kpi-card">
      <div class="kpi-top-row">
        <span class="material-symbols-outlined kpi-icon text-yellow">warning</span>
        <span class="kpi-label">{{ t('admin.contracts.overdueCycles') }}</span>
      </div>
      <div v-if="loading" class="kpi-skeleton-wrap">
        <SkeletonLoader height="26px" width="50%" />
        <SkeletonLoader height="14px" width="45%" class="mt-1" />
      </div>
      <template v-else>
        <strong class="kpi-value text-yellow">{{
          t('admin.contracts.overdueCount', { count: stats.overdue_cycles })
        }}</strong>
        <span class="kpi-trend text-red">{{ t('admin.contracts.overdueWarning') }}</span>
      </template>
    </div>

    <!-- Prime Time Utilization -->
    <div class="kpi-card">
      <div class="kpi-top-row">
        <span class="material-symbols-outlined kpi-icon text-green">bolt</span>
        <span class="kpi-label">{{ t('admin.contracts.primeTimeUtilization') }}</span>
      </div>
      <div v-if="loading" class="kpi-skeleton-wrap">
        <SkeletonLoader height="26px" width="40%" />
        <SkeletonLoader height="14px" width="60%" class="mt-1" />
      </div>
      <template v-else>
        <strong class="kpi-value text-green">{{ stats.prime_time_utilization }}%</strong>
        <span class="kpi-trend">{{ t('admin.contracts.primeTimeHours') }}</span>
      </template>
    </div>
  </div>
</template>

<style scoped>
.contracts-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 18px 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition:
    transform 0.15s,
    box-shadow 0.15s;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.kpi-top-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.kpi-icon {
  font-size: 20px;
  color: var(--muted);
}

.kpi-label {
  font-size: 12px;
  color: var(--muted);
  font-weight: 600;
}

.kpi-value {
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.kpi-trend {
  font-size: 11px;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}
.text-green {
  color: #10b981;
}
.text-yellow {
  color: #f59e0b;
}
.text-red {
  color: #ef4444;
}

.kpi-skeleton-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 4px;
}

.mt-1 {
  margin-top: 4px;
}

@media (max-width: 900px) {
  .contracts-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .contracts-kpi-grid {
    grid-template-columns: 1fr;
  }
}
</style>
