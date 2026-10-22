<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { useOwnerFinanceReport } from '../composables/useOwnerFinanceReport'
import type { FinanceMetricKey, OwnerFinanceFilters } from '../types/ownerFinance'
import OwnerFinanceBookingCell from './OwnerFinanceBookingCell.vue'

const props = defineProps<{ organizationId: number; filters: OwnerFinanceFilters }>()

const { t, d } = useI18n()
const { metrics, selected, loading, error } = useOwnerFinanceReport(
  props.organizationId,
  () => props.filters,
)

// D12 order and letters; the metrics stay separate and are never summed.
const METRICS: { key: FinanceMetricKey; letter: string }[] = [
  { key: 'received', letter: 'R' },
  { key: 'completed', letter: 'H' },
  { key: 'unfinished', letter: 'U' },
  { key: 'no_show', letter: 'N' },
  { key: 'incident', letter: 'S' },
]

const detail = computed(() =>
  selected.value && metrics.value ? metrics.value[selected.value].bookings : [],
)
const metricLabel = (key: FinanceMetricKey) => t(`admin.ownerFinance.metrics.${key}.label`)
const toggle = (key: FinanceMetricKey) => {
  selected.value = selected.value === key ? null : key
}
</script>

<template>
  <section class="report-tab" :aria-busy="loading">
    <p class="note">{{ t('admin.ownerFinance.report.note') }}</p>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-else-if="loading && !metrics" class="muted">{{ t('common.loading') }}</p>
    <template v-else-if="metrics">
      <div class="metric-grid">
        <div
          v-for="metric in METRICS"
          :key="metric.key"
          class="metric-card"
          :class="{ active: selected === metric.key }"
          :data-testid="`owner-finance-metric-${metric.key}`"
        >
          <button
            type="button"
            class="metric-select"
            :aria-pressed="selected === metric.key"
            aria-controls="owner-finance-detail"
            @click="toggle(metric.key)"
          >
            <span class="metric-label">
              <span class="metric-letter" aria-hidden="true">{{ metric.letter }}</span>
              {{ metricLabel(metric.key) }}
            </span>
            <strong class="metric-amount">{{ formatCurrency(metrics[metric.key].amount) }}</strong>
            <span class="metric-count">
              {{ t('admin.ownerFinance.report.count', { count: metrics[metric.key].count }) }}
            </span>
          </button>
          <p class="metric-hint">{{ t(`admin.ownerFinance.metrics.${metric.key}.hint`) }}</p>
        </div>
      </div>

      <section
        v-if="selected"
        id="owner-finance-detail"
        class="detail"
        data-testid="owner-finance-detail"
      >
        <h2 class="detail-title">
          {{ t('admin.ownerFinance.report.detailTitle', { metric: metricLabel(selected) }) }}
        </h2>
        <p v-if="!detail.length" class="muted">{{ t('admin.ownerFinance.report.detailEmpty') }}</p>
        <div v-else class="table-wrap">
          <table class="finance-table">
            <thead>
              <tr>
                <th scope="col">{{ t('admin.ownerFinance.report.columns.booking') }}</th>
                <th scope="col">{{ t(`admin.ownerFinance.report.at.${selected}`) }}</th>
                <th scope="col" class="num">
                  {{
                    t(
                      `admin.ownerFinance.report.columns.${selected === 'received' ? 'received' : 'payable'}`,
                    )
                  }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in detail" :key="`${row.id}-${index}`">
                <td><OwnerFinanceBookingCell :booking="row" /></td>
                <td>{{ d(new Date(row.at), 'datetime') }}</td>
                <td class="num">{{ formatCurrency(row.amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <p v-else id="owner-finance-detail" class="muted">
        {{ t('admin.ownerFinance.report.pick') }}
      </p>
    </template>
  </section>
</template>

<style scoped>
.report-tab {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.note,
.error,
.muted {
  margin: 0;
  font-size: 13px;
}

.note,
.muted {
  color: var(--muted);
}

.error {
  color: var(--error-text);
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 12px;
}

.metric-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--card-bg);
}

.metric-card.active {
  border-color: var(--primary);
  box-shadow: 0 0 0 1px var(--primary);
}

.metric-select {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 0;
  border: none;
  background: none;
  color: var(--ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

/* The whole card answers the click, while the button's name stays short. */
.metric-select::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 16px;
}

.metric-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
}

.metric-letter {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  background: var(--primary-container);
  color: var(--primary);
  font-size: 12px;
  font-weight: 800;
}

.metric-amount {
  font-size: 20px;
  font-weight: 800;
}

.metric-count,
.metric-hint {
  font-size: 12px;
  color: var(--muted);
}

.metric-hint {
  margin: 0;
}

.detail {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.detail-title {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.finance-table {
  width: 100%;
  border-collapse: collapse;
}

.finance-table th,
.finance-table td {
  padding: 10px 12px;
  text-align: left;
  vertical-align: top;
  border-bottom: 1px solid var(--outline);
  white-space: nowrap;
  font-size: 13px;
}

.finance-table thead th {
  font-size: 12px;
  color: var(--muted);
}

.finance-table .num {
  text-align: right;
  font-weight: 700;
}
</style>
