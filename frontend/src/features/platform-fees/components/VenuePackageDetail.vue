<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { PlatformFeeVenue } from '../types/platformFee'

defineProps<{ venue: PlatformFeeVenue | null; loading: boolean; error: string | null }>()
const emit = defineEmits<{ close: [] }>()

const { t, d } = useI18n()
const time = (iso: string | null) => (iso ? d(new Date(iso), 'datetime') : '—')
</script>

<template>
  <section class="venue-detail" :aria-busy="loading" data-testid="platform-fee-venue-detail">
    <header class="detail-head">
      <h2 class="detail-title">
        {{
          venue
            ? t('admin.platformFees.venueDetail.title', {
                venue: venue.venue_name || `#${venue.venue_id}`,
              })
            : t('admin.platformFees.venueDetail.loadingTitle')
        }}
      </h2>
      <button
        type="button"
        class="close-btn"
        :aria-label="t('admin.platformFees.venueDetail.close')"
        @click="emit('close')"
      >
        <span class="material-symbols-outlined" aria-hidden="true">close</span>
      </button>
    </header>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-else-if="loading || !venue" class="muted">{{ t('common.loading') }}</p>
    <template v-else>
      <h3 class="section-title">
        {{ t('admin.platformFees.venueDetail.current') }}:
        <span :class="`status-${venue.package_status}`">
          {{ t(`admin.packages.status.${venue.package_status}`) }}
        </span>
      </h3>
      <dl v-if="venue.package" class="fields">
        <div class="field">
          <dt>{{ t('admin.packages.fields.term') }}</dt>
          <dd>{{ t('admin.packages.termLabel', { days: venue.package.term_days }) }}</dd>
        </div>
        <div class="field">
          <dt>{{ t('admin.packages.fields.startsAt') }}</dt>
          <dd>{{ time(venue.package.starts_at) }}</dd>
        </div>
        <div class="field">
          <dt>{{ t('admin.packages.fields.endsAt') }}</dt>
          <dd>{{ time(venue.package.ends_at) }}</dd>
        </div>
        <div v-if="venue.package.terminated_at" class="field">
          <dt>{{ t('admin.packages.fields.terminatedAt') }}</dt>
          <dd>{{ time(venue.package.terminated_at) }}</dd>
        </div>
      </dl>
      <p v-else class="muted">{{ t('admin.platformFees.venueDetail.none') }}</p>

      <template v-if="venue.packages?.length">
        <h3 class="section-title">{{ t('admin.platformFees.venueDetail.packages') }}</h3>
        <div class="table-wrap">
          <table class="fee-table" data-testid="platform-fee-venue-packages">
            <thead>
              <tr>
                <th scope="col">{{ t('admin.packages.fields.term') }}</th>
                <th scope="col">{{ t('admin.packages.fields.startsAt') }}</th>
                <th scope="col">{{ t('admin.packages.fields.endsAt') }}</th>
                <th scope="col">{{ t('admin.platformFees.venues.columns.status') }}</th>
                <th scope="col">{{ t('admin.packages.fields.terminatedAt') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in venue.packages" :key="item.id">
                <td>{{ t('admin.packages.termLabel', { days: item.term_days }) }}</td>
                <td>{{ time(item.starts_at) }}</td>
                <td>{{ time(item.ends_at) }}</td>
                <td>{{ t(`admin.packages.status.${item.status}`) }}</td>
                <td>{{ time(item.terminated_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <h3 class="section-title">{{ t('admin.platformFees.venueDetail.requests') }}</h3>
      <p v-if="!venue.requests?.length" class="muted">
        {{ t('admin.platformFees.venueDetail.requestsEmpty') }}
      </p>
      <div v-else class="table-wrap">
        <table class="fee-table" data-testid="platform-fee-venue-requests">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.platformFees.requests.columns.code') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.kind') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.term') }}</th>
              <th scope="col" class="num">{{ t('admin.platformFees.requests.columns.amount') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.createdAt') }}</th>
              <th scope="col">{{ t('admin.platformFees.requests.columns.status') }}</th>
              <th scope="col">{{ t('admin.packages.history.columns.paidAt') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="request in venue.requests" :key="request.id">
              <th scope="row" class="code">{{ request.request_code }}</th>
              <td>{{ t(`admin.packages.kind.${request.kind}`) }}</td>
              <td>{{ t('admin.packages.termLabel', { days: request.term_days }) }}</td>
              <td class="num">{{ formatCurrency(request.amount) }}</td>
              <td>{{ time(request.created_at) }}</td>
              <td>{{ t(`admin.packages.requestStatus.${request.status}`) }}</td>
              <td>{{ time(request.paid_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>

<style scoped>
.venue-detail {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--primary);
  border-radius: 16px;
}

.detail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.detail-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.close-btn {
  display: inline-flex;
  padding: 4px;
  border: none;
  border-radius: 8px;
  background: none;
  color: var(--ink);
  cursor: pointer;
}

.error,
.muted {
  margin: 0;
  font-size: 13px;
}

.muted {
  color: var(--muted);
}

.error {
  color: var(--error-text);
}

.section-title {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
}

.fields {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
  gap: 10px 16px;
  margin: 0;
}

.field dt {
  font-size: 12px;
  color: var(--muted);
}

.field dd {
  margin: 2px 0 0;
  font-size: 13px;
  font-weight: 600;
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
  padding: 8px 12px;
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

.code {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-weight: 400;
}

.status-active {
  color: var(--success-text);
}

.status-expired,
.status-none {
  color: var(--warning-text);
}

.status-terminated {
  color: var(--error-text);
}
</style>
