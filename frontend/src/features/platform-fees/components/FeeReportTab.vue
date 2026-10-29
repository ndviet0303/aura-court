<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { ExportButton } from '@/shared/components'
import { usePlatformFeeReport } from '../composables/usePlatformFeeReport'
import FeeReportFilterBar from './FeeReportFilterBar.vue'

const { t } = useI18n()
const { filters, report, loading, error, exportFile } = usePlatformFeeReport()

const TOTALS = [
  { key: 'amount', labelKey: 'admin.platformFees.report.collected' },
  { key: 'purchases', labelKey: 'admin.platformFees.report.purchases' },
  { key: 'renewals', labelKey: 'admin.platformFees.report.renewals' },
] as const
const termLabel = (days: number) => t('admin.packages.termLabel', { days })
</script>

<template>
  <section class="report-tab" :aria-busy="loading">
    <FeeReportFilterBar v-model="filters" />
    <div class="toolbar">
      <p class="note">{{ t('admin.platformFees.report.note') }}</p>
      <ExportButton :action="exportFile" />
    </div>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-else-if="loading && !report" class="muted">{{ t('common.loading') }}</p>
    <template v-else-if="report">
      <div class="metric-grid">
        <div
          v-for="metric in TOTALS"
          :key="metric.key"
          class="metric-card"
          :data-testid="`platform-fee-report-${metric.key}`"
        >
          <span class="metric-label">{{ t(metric.labelKey) }}</span>
          <strong class="metric-value">
            {{
              metric.key === 'amount'
                ? formatCurrency(report.totals.amount)
                : report.totals[metric.key]
            }}
          </strong>
        </div>
      </div>

      <h2 class="section-title">{{ t('admin.platformFees.report.byTerm') }}</h2>
      <div class="table-wrap">
        <table class="fee-table" data-testid="platform-fee-report-by-term">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.platformFees.report.columns.term') }}</th>
              <th scope="col" class="num">
                {{ t('admin.platformFees.report.columns.purchases') }}
              </th>
              <th scope="col" class="num">{{ t('admin.platformFees.report.columns.renewals') }}</th>
              <th scope="col" class="num">{{ t('admin.platformFees.report.columns.amount') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in report.by_term" :key="row.term_days">
              <th scope="row">{{ termLabel(row.term_days) }}</th>
              <td class="num">{{ row.purchases }}</td>
              <td class="num">{{ row.renewals }}</td>
              <td class="num">{{ formatCurrency(row.amount) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th scope="row">{{ t('admin.platformFees.report.total') }}</th>
              <td class="num">{{ report.totals.purchases }}</td>
              <td class="num">{{ report.totals.renewals }}</td>
              <td class="num">{{ formatCurrency(report.totals.amount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <h2 class="section-title">{{ t('admin.platformFees.report.byVenue') }}</h2>
      <p v-if="!report.by_venue.length" class="muted" data-testid="platform-fee-report-empty">
        {{ t('admin.platformFees.report.empty') }}
      </p>
      <div v-else class="table-wrap">
        <table class="fee-table" data-testid="platform-fee-report-by-venue">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.platformFees.report.columns.venue') }}</th>
              <th scope="col">{{ t('admin.platformFees.report.columns.term') }}</th>
              <th scope="col" class="num">
                {{ t('admin.platformFees.report.columns.purchases') }}
              </th>
              <th scope="col" class="num">{{ t('admin.platformFees.report.columns.renewals') }}</th>
              <th scope="col" class="num">{{ t('admin.platformFees.report.columns.amount') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in report.by_venue" :key="`${row.venue_id}-${row.term_days}`">
              <th scope="row">{{ row.venue_name || `#${row.venue_id}` }}</th>
              <td>{{ termLabel(row.term_days) }}</td>
              <td class="num">{{ row.purchases }}</td>
              <td class="num">{{ row.renewals }}</td>
              <td class="num">{{ formatCurrency(row.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>

<style scoped>
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px 12px;
}

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
  grid-template-columns: repeat(auto-fit, minmax(min(190px, 100%), 1fr));
  gap: 12px;
}

.metric-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--card-bg);
}

.metric-label {
  font-size: 13px;
  font-weight: 700;
}

.metric-value {
  font-size: 20px;
  font-weight: 800;
}

.section-title {
  margin: 4px 0 0;
  font-size: 15px;
  font-weight: 800;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.fee-table {
  width: 100%;
  border-collapse: collapse;
}

.fee-table th,
.fee-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--outline);
  white-space: nowrap;
  font-size: 13px;
}

.fee-table thead th {
  font-size: 12px;
  color: var(--muted);
}

.fee-table .num {
  text-align: right;
  font-weight: 700;
}

.fee-table tfoot th,
.fee-table tfoot td {
  font-weight: 800;
  border-bottom: none;
}
</style>
