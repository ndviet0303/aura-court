<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { SkeletonLoader } from '@/shared/components'
import type { DisputeStats } from '../../types/dispute'

defineProps<{
  stats: DisputeStats
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="dispute-kpi-grid">
    <!-- Skeleton Loading State -->
    <template v-if="loading">
      <div v-for="i in 4" :key="i" class="kpi-card skeleton-card">
        <SkeletonLoader type="avatar" width="44px" height="44px" />
        <div class="kpi-content skeleton-text-wrap">
          <SkeletonLoader type="text" width="120px" height="12px" />
          <SkeletonLoader type="title" width="60px" height="24px" />
        </div>
      </div>
    </template>

    <!-- Real KPI Cards -->
    <template v-else>
      <!-- Card 1: Pending -->
      <div class="kpi-card">
        <div class="kpi-icon-wrap icon-pending">
          <span class="material-symbols-outlined">pending_actions</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">{{ t('admin.disputes.kpiPending') }}</span>
          <div class="kpi-value-row">
            <span class="kpi-value text-pending">{{ stats.pending_count }}</span>
            <span class="kpi-sub">{{ t('admin.disputes.kpiPendingSub') }}</span>
          </div>
        </div>
      </div>

      <!-- Card 2: Investigating -->
      <div class="kpi-card">
        <div class="kpi-icon-wrap icon-investigating">
          <span class="material-symbols-outlined">search_insights</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">{{ t('admin.disputes.kpiInvestigating') }}</span>
          <div class="kpi-value-row">
            <span class="kpi-value text-investigating">{{ stats.investigatingCount }}</span>
            <span class="kpi-sub">{{ t('admin.disputes.kpiInvestigatingSub') }}</span>
          </div>
        </div>
      </div>

      <!-- Card 3: Critical SLA -->
      <div class="kpi-card">
        <div class="kpi-icon-wrap icon-sla">
          <span class="material-symbols-outlined">alarm</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">{{ t('admin.disputes.kpiCriticalSla') }}</span>
          <div class="kpi-value-row">
            <span class="kpi-value text-sla">{{ stats.criticalSlaCount }}</span>
            <span class="kpi-sub">{{ t('admin.disputes.kpiCriticalSlaSub') }}</span>
          </div>
        </div>
      </div>

      <!-- Card 4: Resolved -->
      <div class="kpi-card">
        <div class="kpi-icon-wrap icon-resolved">
          <span class="material-symbols-outlined">task_alt</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">{{ t('admin.disputes.kpiResolved') }}</span>
          <div class="kpi-value-row">
            <span class="kpi-value text-resolved">{{ stats.resolvedCount }}</span>
            <span class="kpi-sub">{{ t('admin.disputes.kpiResolvedSub') }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.dispute-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.kpi-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 16px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.04));
  transition:
    transform 0.2s,
    box-shadow 0.2s;
  color: var(--ink);
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md, 0 4px 12px rgba(0, 0, 0, 0.08));
}

.kpi-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-icon-wrap .material-symbols-outlined {
  font-size: 24px;
}

.icon-pending {
  background: var(--primary-container);
  color: var(--primary);
}

.icon-investigating {
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8;
}

.icon-sla {
  background: var(--error-bg, rgba(198, 40, 40, 0.2));
  color: var(--error-text, #f87171);
}

.icon-resolved {
  background: var(--success-bg, rgba(46, 125, 50, 0.25));
  color: var(--success-text, #4ade80);
}

.kpi-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.skeleton-text-wrap {
  flex: 1;
  gap: 6px;
}

.kpi-label {
  font-size: 12px;
  color: var(--muted);
  font-weight: 500;
}

.kpi-value-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.kpi-value {
  font-size: 22px;
  font-weight: 800;
  line-height: 1.1;
}

.text-pending {
  color: var(--primary);
}

.text-investigating {
  color: #38bdf8;
}

.text-sla {
  color: var(--error-text, #f87171);
}

.text-resolved {
  color: var(--success-text, #4ade80);
}

.kpi-sub {
  font-size: 11px;
  color: var(--muted);
}
</style>
