<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { DatePicker, ExportButton, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import { usePlatformFeeFilterOptions } from '../composables/usePlatformFeeFilterOptions'
import { usePlatformFeeRequests } from '../composables/usePlatformFeeRequests'
import type { PlatformFeeRequestFilters } from '../types/platformFee'
import PaymentRequestDetail from './PaymentRequestDetail.vue'
import PlatformFeePager from './PlatformFeePager.vue'

const { t, d } = useI18n()
const {
  filters,
  requests,
  page,
  lastPage,
  total,
  loading,
  error,
  load,
  exportFile,
  openCode,
  opened,
  detailLoading,
  detailError,
  open,
  close,
} = usePlatformFeeRequests()
const { venueOptions, termOptions } = usePlatformFeeFilterOptions()

const STATUSES = ['pending', 'paid', 'expired'] as const
const statusOptions = computed<DropdownOption[]>(() => [
  { value: '', label: t('admin.platformFees.filters.allStatuses') },
  ...STATUSES.map((status) => ({
    value: status,
    label: t(`admin.packages.requestStatus.${status}`),
  })),
])

const update = (patch: Partial<PlatformFeeRequestFilters>) => {
  filters.value = { ...filters.value, ...patch }
}
const time = (iso: string) => d(new Date(iso), 'datetime')

const panel = ref<HTMLElement | null>(null)
let trigger: HTMLElement | null = null
async function toggle(code: string, event: MouseEvent) {
  if (openCode.value === code) return close()
  trigger = event.currentTarget as HTMLElement
  void open(code)
  await nextTick()
  panel.value?.focus()
}
// Return focus to the row button, since closing unmounts the focused panel.
function closePanel() {
  close()
  trigger?.focus()
}
</script>

<template>
  <section class="requests-tab" :aria-busy="loading">
    <div
      class="filter-bar"
      role="group"
      :aria-label="t('admin.platformFees.requests.filtersLabel')"
    >
      <div class="form-group">
        <span class="form-label">{{ t('admin.platformFees.filters.status') }}</span>
        <SelectDropdown
          :model-value="filters.status"
          :options="statusOptions"
          variant="bordered"
          :aria-label="t('admin.platformFees.filters.status')"
          data-testid="platform-fee-requests-status"
          @update:model-value="
            (value) => update({ status: value as PlatformFeeRequestFilters['status'] })
          "
        />
      </div>
      <div class="form-group">
        <span class="form-label">{{ t('admin.platformFees.filters.venue') }}</span>
        <SelectDropdown
          :model-value="filters.venueId"
          :options="venueOptions"
          variant="bordered"
          :aria-label="t('admin.platformFees.filters.venue')"
          data-testid="platform-fee-requests-venue"
          @update:model-value="(value) => update({ venueId: Number(value) })"
        />
      </div>
      <div class="form-group">
        <span class="form-label">{{ t('admin.platformFees.filters.term') }}</span>
        <SelectDropdown
          :model-value="filters.term"
          :options="termOptions"
          variant="bordered"
          :aria-label="t('admin.platformFees.filters.term')"
          data-testid="platform-fee-requests-term"
          @update:model-value="(value) => update({ term: Number(value) })"
        />
      </div>
      <div
        class="form-group"
        role="group"
        :aria-label="t('admin.platformFees.filters.createdFrom')"
      >
        <span class="form-label">{{ t('admin.platformFees.filters.createdFrom') }}</span>
        <DatePicker
          :model-value="filters.from"
          :max="filters.to || undefined"
          data-testid="platform-fee-requests-from"
          @update:model-value="(from) => update({ from })"
        />
      </div>
      <div class="form-group" role="group" :aria-label="t('admin.platformFees.filters.createdTo')">
        <span class="form-label">{{ t('admin.platformFees.filters.createdTo') }}</span>
        <DatePicker
          :model-value="filters.to"
          :min="filters.from || undefined"
          data-testid="platform-fee-requests-to"
          @update:model-value="(to) => update({ to })"
        />
      </div>
      <p class="form-hint period-hint">{{ t('admin.platformFees.filters.periodHint') }}</p>
    </div>

    <div class="toolbar">
      <p class="note">{{ t('admin.platformFees.requests.note') }}</p>
      <ExportButton :action="exportFile" />
    </div>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-else-if="loading && !requests.length" class="muted">{{ t('common.loading') }}</p>
    <p v-else-if="!requests.length" class="muted" data-testid="platform-fee-requests-empty">
      {{ t('admin.platformFees.requests.empty') }}
    </p>
    <template v-else>
      <p class="muted">{{ t('admin.platformFees.requests.total', { total }) }}</p>
      <div class="table-wrap">
        <table class="fee-table" data-testid="platform-fee-requests">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.platformFees.requests.columns.code') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.venue') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.owner') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.kind') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.term') }}</th>
              <th scope="col" class="num">{{ t('admin.platformFees.requests.columns.amount') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.createdAt') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.expiresAt') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in requests"
              :key="row.id"
              :data-testid="`platform-fee-request-${row.request_code}`"
            >
              <th scope="row">
                <button
                  type="button"
                  class="link-btn code"
                  :aria-expanded="openCode === row.request_code"
                  aria-controls="platform-fee-request-detail"
                  @click="toggle(row.request_code, $event)"
                >
                  {{ row.request_code }}
                </button>
              </th>
              <td>
                {{ row.venue.name || '—' }}
                <span class="sub">{{ row.organization.name }}</span>
              </td>
              <td>
                <template v-if="row.owner">
                  {{ row.owner.name }}
                  <span class="sub">{{ row.owner.email }}</span>
                </template>
                <template v-else>—</template>
              </td>
              <td>{{ t(`admin.packages.kind.${row.kind}`) }}</td>
              <td>{{ t('admin.packages.termLabel', { days: row.term_days }) }}</td>
              <td class="num">{{ formatCurrency(row.amount) }}</td>
              <td>{{ time(row.created_at) }}</td>
              <td>{{ time(row.expires_at) }}</td>
              <td>
                <span :class="`status-${row.status}`">
                  {{ t(`admin.packages.requestStatus.${row.status}`) }}
                </span>
                <span v-if="row.paid_at" class="sub">{{ time(row.paid_at) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <PlatformFeePager
        :page="page"
        :last-page="lastPage"
        :loading="loading"
        :label="t('admin.platformFees.requests.pager')"
        @go="load"
      />
    </template>

    <div
      v-if="openCode"
      id="platform-fee-request-detail"
      ref="panel"
      class="detail-panel"
      tabindex="-1"
    >
      <PaymentRequestDetail
        :code="openCode"
        :request="opened"
        :loading="detailLoading"
        :error="detailError"
        @close="closePanel"
      />
    </div>
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

.requests-tab {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.filter-bar {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
  align-items: end;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.filter-bar .form-group {
  margin-bottom: 0;
}

.period-hint {
  grid-column: 1 / -1;
  margin: 0;
}

.note,
.error,
.muted {
  margin: 0;
  font-size: 13px;
}

.note,
.muted,
.sub {
  color: var(--muted);
}

.error {
  color: var(--error-text);
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
  vertical-align: top;
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

.sub {
  display: block;
  font-size: 12px;
}

.link-btn {
  padding: 0;
  border: none;
  background: none;
  color: var(--primary);
  font-weight: 700;
  text-decoration: underline;
  cursor: pointer;
}

.code {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.status-paid {
  color: var(--success-text);
}

.status-expired {
  color: var(--warning-text);
}

.detail-panel {
  outline: none;
}
</style>
