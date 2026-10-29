<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, ExportButton, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { PackageStatus } from '@/features/platform-subscriptions/types'
import { usePlatformFeeVenues } from '../composables/usePlatformFeeVenues'
import type { ExpiringWithin, PlatformFeeVenueFilters } from '../types/platformFee'
import PlatformFeePager from './PlatformFeePager.vue'
import VenuePackageDetail from './VenuePackageDetail.vue'

const { t, te, d } = useI18n()
const {
  filters,
  venues,
  page,
  lastPage,
  total,
  expiringCounts,
  loading,
  error,
  load,
  exportFile,
  openId,
  opened,
  detailLoading,
  detailError,
  open,
  close,
} = usePlatformFeeVenues()

const STATUSES: PackageStatus[] = ['none', 'active', 'expired', 'terminated']
const EXPIRING: ExpiringWithin[] = [7, 3, 1]
const statusOptions = computed<DropdownOption[]>(() => [
  { value: '', label: t('admin.platformFees.venues.allStatuses') },
  ...STATUSES.map((status) => ({ value: status, label: t(`admin.packages.status.${status}`) })),
])
// Applied on submit, so typing sends no request per keystroke.
const search = ref('')

const update = (patch: Partial<PlatformFeeVenueFilters>) => {
  filters.value = { ...filters.value, ...patch }
}
const toggleExpiring = (days: ExpiringWithin) =>
  update({ expiringWithin: filters.value.expiringWithin === days ? 0 : days })
const time = (iso: string) => d(new Date(iso), 'datetime')
// An active venue needs no tag; the others say why bookings may be off regardless of the package.
const venueStatusKey = (status: string | null) => `admin.platformFees.venues.venueStatus.${status}`
const showVenueStatus = (status: string | null) => status !== 'active' && te(venueStatusKey(status))

const panel = ref<HTMLElement | null>(null)
let trigger: HTMLElement | null = null
async function toggle(venueId: number, event: MouseEvent) {
  if (openId.value === venueId) return close()
  trigger = event.currentTarget as HTMLElement
  void open(venueId)
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
  <section class="venues-tab" :aria-busy="loading">
    <div class="filter-bar" role="group" :aria-label="t('admin.platformFees.venues.filtersLabel')">
      <div class="form-group">
        <span class="form-label">{{ t('admin.platformFees.venues.status') }}</span>
        <SelectDropdown
          :model-value="filters.status"
          :options="statusOptions"
          variant="bordered"
          :aria-label="t('admin.platformFees.venues.status')"
          data-testid="platform-fee-venues-status"
          @update:model-value="(value) => update({ status: value as PackageStatus | '' })"
        />
      </div>
      <form class="form-group" role="search" @submit.prevent="update({ search })">
        <label class="form-label" for="platform-fee-venue-search">
          {{ t('admin.platformFees.venues.search') }}
        </label>
        <div class="search-row">
          <input
            id="platform-fee-venue-search"
            v-model="search"
            type="search"
            class="form-input"
            maxlength="100"
            :placeholder="t('admin.platformFees.venues.searchPlaceholder')"
            data-testid="platform-fee-venues-search"
          />
          <AuraButton type="submit" variant="outline" icon="search">
            {{ t('admin.platformFees.venues.searchSubmit') }}
          </AuraButton>
        </div>
      </form>
    </div>

    <div class="chips" role="group" :aria-label="t('admin.platformFees.venues.expiring')">
      <span class="chips-label">{{ t('admin.platformFees.venues.expiring') }}</span>
      <button
        v-for="days in EXPIRING"
        :key="days"
        type="button"
        class="chip"
        :class="{ active: filters.expiringWithin === days }"
        :aria-pressed="filters.expiringWithin === days"
        :data-testid="`platform-fee-expiring-${days}`"
        @click="toggleExpiring(days)"
      >
        {{
          t('admin.platformFees.venues.expiringChip', {
            days,
            count: expiringCounts ? expiringCounts[days] : '…',
          })
        }}
      </button>
    </div>

    <div class="toolbar">
      <p class="note">{{ t('admin.platformFees.venues.note') }}</p>
      <ExportButton :action="exportFile" />
    </div>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-else-if="loading && !venues.length" class="muted">{{ t('common.loading') }}</p>
    <p v-else-if="!venues.length" class="muted" data-testid="platform-fee-venues-empty">
      {{ t('admin.platformFees.venues.empty') }}
    </p>
    <template v-else>
      <p class="muted">{{ t('admin.platformFees.venues.total', { total }) }}</p>
      <div class="table-wrap">
        <table class="fee-table" data-testid="platform-fee-venues">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.platformFees.venues.columns.venue') }}</th>
              <th scope="col">{{ t('admin.platformFees.venues.columns.owner') }}</th>
              <th scope="col">{{ t('admin.platformFees.venues.columns.status') }}</th>
              <th scope="col">{{ t('admin.packages.fields.term') }}</th>
              <th scope="col">{{ t('admin.packages.fields.endsAt') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in venues"
              :key="row.venue_id"
              :data-testid="`platform-fee-venue-${row.venue_id}`"
            >
              <th scope="row">
                <button
                  type="button"
                  class="link-btn"
                  :aria-expanded="openId === row.venue_id"
                  aria-controls="platform-fee-venue-detail"
                  @click="toggle(row.venue_id, $event)"
                >
                  {{ row.venue_name || `#${row.venue_id}` }}
                </button>
                <span v-if="showVenueStatus(row.venue_status)" class="sub">
                  {{ t(venueStatusKey(row.venue_status)) }}
                </span>
              </th>
              <td>
                {{ row.organization.name || '—' }}
                <span v-if="row.owner" class="sub">
                  {{ [row.owner.name, row.owner.email].filter(Boolean).join(' · ') }}
                </span>
              </td>
              <td>
                <span :class="`status-${row.package_status}`">
                  {{ t(`admin.packages.status.${row.package_status}`) }}
                </span>
              </td>
              <td>
                {{
                  row.package ? t('admin.packages.termLabel', { days: row.package.term_days }) : '—'
                }}
              </td>
              <td>{{ row.package ? time(row.package.ends_at) : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <PlatformFeePager
        :page="page"
        :last-page="lastPage"
        :loading="loading"
        :label="t('admin.platformFees.venues.pager')"
        @go="load"
      />
    </template>

    <div
      v-if="openId"
      id="platform-fee-venue-detail"
      ref="panel"
      class="detail-panel"
      tabindex="-1"
    >
      <VenuePackageDetail
        :venue="opened"
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

.venues-tab {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.filter-bar {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));
  align-items: end;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.filter-bar .form-group {
  margin-bottom: 0;
}

.search-row {
  display: flex;
  gap: 8px;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.chips-label {
  font-size: 13px;
  font-weight: 600;
}

.chip {
  padding: 6px 12px;
  border: 1px solid var(--outline);
  border-radius: 999px;
  background: var(--surface);
  color: var(--ink);
  font: inherit;
  font-size: 13px;
  cursor: pointer;
}

.chip.active {
  border-color: var(--primary);
  background: var(--primary-container);
  color: var(--primary);
  font-weight: 700;
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

.sub {
  display: block;
  font-size: 12px;
  font-weight: 400;
}

.link-btn {
  padding: 0;
  border: none;
  background: none;
  color: var(--primary);
  font: inherit;
  font-weight: 700;
  text-decoration: underline;
  cursor: pointer;
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

.detail-panel {
  outline: none;
}
</style>
