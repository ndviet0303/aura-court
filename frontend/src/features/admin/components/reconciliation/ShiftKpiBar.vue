<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'

defineProps<{
  activeShifts: number
  todayCash: number
  todayDigital: number
  totalVariance: number
  matchRate: number
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="shift-kpi-grid">
    <!-- Card 1: Active Shifts -->
    <div class="stat-card">
      <div class="stat-top-row">
        <span class="material-symbols-outlined stat-icon text-green">schedule</span>
        <span class="stat-label">{{ t('admin.shifts.activeShiftsKpi') }}</span>
      </div>
      <div v-if="loading" class="stat-skeleton-wrap">
        <SkeletonLoader type="rect" height="24px" width="90px" />
      </div>
      <strong v-else class="stat-value font-mono">
        {{ activeShifts }}
      </strong>
      <span class="stat-trend" :class="activeShifts > 0 ? 'text-green' : 'text-muted-sub'">
        <span class="material-symbols-outlined icon-14">{{
          activeShifts > 0 ? 'sensors' : 'pause_circle'
        }}</span>
        <span>{{
          activeShifts > 0 ? t('admin.shifts.statusLive') : t('admin.shifts.closedStatus')
        }}</span>
      </span>
    </div>

    <!-- Card 2: Today Cash Collected -->
    <div class="stat-card">
      <div class="stat-top-row">
        <span class="material-symbols-outlined stat-icon text-pink">payments</span>
        <span class="stat-label">{{ t('admin.shifts.todayCashKpi') }}</span>
      </div>
      <div v-if="loading" class="stat-skeleton-wrap">
        <SkeletonLoader type="rect" height="24px" width="130px" />
      </div>
      <strong v-else class="stat-value text-pink font-mono">
        {{ formatCurrency(todayCash) }}
      </strong>
      <span class="stat-trend text-pink">
        <span class="material-symbols-outlined icon-14">account_balance_wallet</span>
        <span>{{ t('admin.shifts.hdrCountedCash') }}</span>
      </span>
    </div>

    <!-- Card 3: Today Digital Revenue -->
    <div class="stat-card">
      <div class="stat-top-row">
        <span class="material-symbols-outlined stat-icon text-blue">qr_code_2</span>
        <span class="stat-label">{{ t('admin.shifts.todayDigitalKpi') }}</span>
      </div>
      <div v-if="loading" class="stat-skeleton-wrap">
        <SkeletonLoader type="rect" height="24px" width="130px" />
      </div>
      <strong v-else class="stat-value text-blue font-mono">
        {{ formatCurrency(todayDigital) }}
      </strong>
      <span class="stat-trend text-blue">
        <span class="material-symbols-outlined icon-14">verified</span>
        <span>PayOS & Aura Wallet</span>
      </span>
    </div>

    <!-- Card 4: Variance Discrepancy -->
    <div class="stat-card">
      <div class="stat-top-row">
        <span
          class="material-symbols-outlined stat-icon"
          :class="totalVariance > 0 ? 'text-yellow' : 'text-green'"
          >difference</span
        >
        <span class="stat-label">{{ t('admin.shifts.varianceAlertKpi') }}</span>
      </div>
      <div v-if="loading" class="stat-skeleton-wrap">
        <SkeletonLoader type="rect" height="24px" width="110px" />
      </div>
      <strong
        v-else
        class="stat-value font-mono"
        :class="totalVariance > 0 ? 'text-yellow' : 'text-green'"
      >
        {{ formatCurrency(totalVariance) }}
      </strong>
      <span class="stat-trend" :class="totalVariance > 0 ? 'text-yellow' : 'text-green'">
        <span class="material-symbols-outlined icon-14">{{
          totalVariance > 0 ? 'warning' : 'check_circle'
        }}</span>
        <span>{{
          totalVariance > 0 ? t('admin.shifts.hasVariance') : t('admin.shifts.balancedCash')
        }}</span>
      </span>
    </div>

    <!-- Card 5: Match Rate -->
    <div class="stat-card">
      <div class="stat-top-row">
        <span class="material-symbols-outlined stat-icon text-purple">fact_check</span>
        <span class="stat-label">{{ t('admin.shifts.matchRateKpi') }}</span>
      </div>
      <div v-if="loading" class="stat-skeleton-wrap">
        <SkeletonLoader type="rect" height="24px" width="80px" />
      </div>
      <strong v-else class="stat-value text-purple font-mono"> {{ matchRate }}% </strong>
      <span class="stat-trend text-purple">
        <span class="material-symbols-outlined icon-14">task_alt</span>
        <span>{{ t('admin.shifts.approvedReconciliation') }}</span>
      </span>
    </div>
  </div>
</template>

<style scoped>
.shift-kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
}

.stat-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
  transition:
    transform 0.2s,
    box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
}

.stat-top-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.stat-icon {
  font-size: 20px;
}

.stat-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.stat-value {
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.02em;
}

.stat-skeleton-wrap {
  padding: 2px 0;
}

.stat-trend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
}

.icon-14 {
  font-size: 14px;
}

.text-green {
  color: #10b981;
}
.text-blue {
  color: #3b82f6;
}
.text-pink {
  color: var(--pink-dark, #db2777);
}
.text-purple {
  color: #8b5cf6;
}
.text-yellow {
  color: #f59e0b;
}
.text-red {
  color: #ef4444;
}
.text-muted-sub {
  color: var(--muted);
}

:global(html.dark) .stat-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

@media (max-width: 1200px) {
  .shift-kpi-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .shift-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .shift-kpi-grid {
    grid-template-columns: 1fr;
  }
}
</style>
