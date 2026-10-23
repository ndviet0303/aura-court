<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { venueDateTime } from '@/core/utils/venueTime'
import { AuraButton, SelectDropdown } from '@/shared/components'
import PlatformFeePager from '@/features/platform-fees/components/PlatformFeePager.vue'
import { useContentReports } from '../composables/useContentReports'
import type { ReportTargetType } from '../types/community'
import type { ReportFilters } from '../types/moderation'

// FR-132: "Báo cáo vi phạm" — the platform admin's queue; reports never hide content by themselves.
const { t } = useI18n()
const { filters, rows, page, lastPage, loading, error, load } = useContentReports()

const statusOptions = (['pending', 'resolved', 'all'] as const).map((s) => ({
  value: s,
  label: t(`moderation.status.${s}`),
}))
const typeOptions = [
  { value: '', label: t('common.all') },
  ...(['post', 'comment', 'review'] as const).map((s) => ({
    value: s,
    label: t(`moderation.type.${s}`),
  })),
]
</script>

<template>
  <div class="admin-detail-page">
    <header class="admin-detail-header">
      <div>
        <h1 class="admin-detail-title">{{ t('moderation.title') }}</h1>
        <p class="admin-detail-muted">{{ t('moderation.subtitle') }}</p>
      </div>
      <AuraButton variant="outline" size="sm" :loading="loading" @click="load(page)">
        {{ t('common.refresh') }}
      </AuraButton>
    </header>

    <section class="admin-detail-card reports-filters">
      <label class="form-group">
        <span class="form-label">{{ t('moderation.statusLabel') }}</span>
        <SelectDropdown
          :model-value="filters.status"
          :options="statusOptions"
          variant="bordered"
          :aria-label="t('moderation.statusLabel')"
          @update:model-value="filters.status = $event as ReportFilters['status']"
        />
      </label>
      <label class="form-group">
        <span class="form-label">{{ t('moderation.typeLabel') }}</span>
        <SelectDropdown
          :model-value="filters.type"
          :options="typeOptions"
          variant="bordered"
          :aria-label="t('moderation.typeLabel')"
          @update:model-value="filters.type = $event as ReportTargetType | ''"
        />
      </label>
    </section>

    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>

    <section class="admin-detail-card">
      <p v-if="loading && !rows.length" class="admin-detail-muted">{{ t('common.loading') }}</p>
      <p v-else-if="!rows.length && !error" class="admin-detail-muted">
        {{ t('moderation.empty') }}
      </p>
      <div v-else-if="rows.length" class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('moderation.columns.content') }}</th>
              <th>{{ t('moderation.columns.reason') }}</th>
              <th>{{ t('moderation.columns.reporter') }}</th>
              <th>{{ t('moderation.columns.status') }}</th>
              <th>{{ t('moderation.columns.createdAt') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <strong>{{ t(`moderation.type.${row.type}`) }}</strong>
                <div v-if="row.target" class="admin-detail-muted">
                  {{ row.target.author.name }}: {{ row.target.excerpt }}
                  <template v-if="row.target.is_hidden"> · {{ t('moderation.hidden') }}</template>
                </div>
                <div v-else class="admin-detail-muted">{{ t('moderation.targetDeleted') }}</div>
              </td>
              <td class="reports-reason">{{ row.reason }}</td>
              <td>{{ row.reporter.name }}</td>
              <td>
                {{ t(`moderation.status.${row.status}`) }}
                <div v-if="row.action_taken" class="admin-detail-muted">
                  {{ t(`moderation.action.${row.action_taken}`) }}
                </div>
              </td>
              <td>{{ venueDateTime(row.created_at) }}</td>
              <td>
                <RouterLink :to="{ name: 'admin-content-report', params: { id: row.id } }">
                  {{ t('moderation.open') }}
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
      :label="t('moderation.title')"
      @go="load"
    />
  </div>
</template>

<style scoped>
.reports-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.reports-filters .form-group {
  min-width: 200px;
}

.reports-reason {
  max-width: 320px;
  overflow-wrap: anywhere;
}
</style>
