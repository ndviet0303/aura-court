<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { venueDateTime } from '@/core/utils/venueTime'
import { AuraButton, SelectDropdown } from '@/shared/components'
import PlatformFeePager from '@/features/platform-fees/components/PlatformFeePager.vue'
import { REMEDIATION_STATUSES, type RemediationStatus } from '../types/suspension'
import { useRemediationQueue } from '../composables/useRemediationQueue'
import RemediationStatusChip from '../components/suspension/RemediationStatusChip.vue'

// Task 5.10: the platform admin's remediation queue.
const { t } = useI18n()
const { status, rows, page, lastPage, loading, error, load } = useRemediationQueue()
const statusOptions = [
  { value: '', label: t('common.all') },
  ...REMEDIATION_STATUSES.map((s) => ({ value: s, label: t(`admin.remediation.status.${s}`) })),
]
</script>

<template>
  <div class="admin-detail-page">
    <header class="admin-detail-header">
      <div>
        <h1 class="admin-detail-title">{{ t('admin.remediation.queueTitle') }}</h1>
        <p class="admin-detail-muted">{{ t('admin.remediation.queueSubtitle') }}</p>
      </div>
      <AuraButton variant="outline" size="sm" :loading="loading" @click="load(page)">
        {{ t('common.refresh') }}
      </AuraButton>
    </header>

    <section class="admin-detail-card">
      <label class="queue-filter">
        <span class="form-label">{{ t('admin.remediation.statusLabel') }}</span>
        <SelectDropdown
          :model-value="status"
          :options="statusOptions"
          variant="bordered"
          :aria-label="t('admin.remediation.statusLabel')"
          @update:model-value="status = $event as RemediationStatus | ''"
        />
      </label>
    </section>

    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>

    <section class="admin-detail-card">
      <p v-if="loading && !rows.length" class="admin-detail-muted">{{ t('common.loading') }}</p>
      <p v-else-if="!rows.length && !error" class="admin-detail-muted">
        {{ t('admin.remediation.queueEmpty') }}
      </p>
      <div v-else-if="rows.length" class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('admin.remediation.columns.venue') }}</th>
              <th>{{ t('admin.remediation.columns.status') }}</th>
              <th>{{ t('admin.remediation.columns.submittedAt') }}</th>
              <th>{{ t('admin.remediation.columns.submissions') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <strong>{{ resolveLocalizedText(row.suspension?.venue?.name) }}</strong>
                <div class="admin-detail-muted">
                  {{ row.suspension?.venue?.organization?.name }}
                </div>
              </td>
              <td><RemediationStatusChip :status="row.status" /></td>
              <td>{{ venueDateTime(row.submitted_at) }}</td>
              <td>{{ row.submission_count ?? '—' }}</td>
              <td>
                <RouterLink :to="{ name: 'admin-venue-remediation', params: { id: row.id } }">
                  {{ t('admin.remediation.open') }}
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <PlatformFeePager
      :page="page"
      :last-page="lastPage"
      :loading="loading"
      :label="t('admin.remediation.queueTitle')"
      @go="load"
    />
  </div>
</template>

<style scoped>
.queue-filter {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 260px;
}
</style>
