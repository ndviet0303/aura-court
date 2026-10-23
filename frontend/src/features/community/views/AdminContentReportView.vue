<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { venueDateTime } from '@/core/utils/venueTime'
import { useContentReportDetail } from '../composables/useContentReports'
import ModerationActions from '../components/ModerationActions.vue'
import ReportedContent from '../components/ReportedContent.vue'

// FR-132 / BR-160: the admin decides each report with a note; nothing is hidden automatically.
const { t, te } = useI18n()
const route = useRoute()
const { detail, loading, acting, error, act } = useContentReportDetail(
  computed(() => Number(route.params.id)),
)
const report = computed(() => detail.value?.report)
// History actions are open-ended on the API; an unknown one shows as sent.
const actionLabel = (action: string) =>
  te(`moderation.action.${action}`) ? t(`moderation.action.${action}`) : action
</script>

<template>
  <div class="admin-detail-page">
    <RouterLink :to="{ name: 'admin-content-reports' }" class="admin-detail-back">
      ← {{ t('moderation.back') }}
    </RouterLink>
    <p v-if="loading && !detail" class="admin-detail-muted">{{ t('common.loading') }}</p>
    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>

    <template v-if="detail && report">
      <header class="admin-detail-header">
        <div>
          <h1 class="admin-detail-title">
            {{ t('moderation.detailTitle', { id: report.id }) }} ·
            {{ t(`moderation.type.${report.type}`) }}
          </h1>
          <p class="admin-detail-muted">
            {{ t('moderation.pendingCount', { count: detail.pending_reports }) }}
          </p>
        </div>
      </header>

      <section class="admin-detail-card">
        <dl class="admin-detail-grid">
          <dt>{{ t('moderation.columns.reporter') }}</dt>
          <dd>{{ report.reporter.name }} · {{ venueDateTime(report.created_at) }}</dd>
          <dt>{{ t('moderation.columns.reason') }}</dt>
          <dd>{{ report.reason }}</dd>
          <dt>{{ t('moderation.columns.status') }}</dt>
          <dd>
            {{ t(`moderation.status.${report.status}`) }}
            <template v-if="report.action_taken">
              · {{ t(`moderation.action.${report.action_taken}`) }}
            </template>
            <template v-if="report.reviewer">
              · {{ report.reviewer.name }} {{ venueDateTime(report.reviewed_at) }}
            </template>
          </dd>
        </dl>
      </section>

      <ReportedContent :target="detail.target" :context="detail.context" />

      <ModerationActions
        v-if="detail.target"
        :hidden="detail.target.is_hidden"
        :busy="acting"
        @act="async (command, note, done) => done(await act(command, note))"
      />

      <section class="admin-detail-card">
        <h2 class="admin-detail-section-title">{{ t('moderation.history') }}</h2>
        <p v-if="!detail.history.length" class="admin-detail-muted">
          {{ t('moderation.noHistory') }}
        </p>
        <dl v-else class="admin-detail-grid">
          <template v-for="(entry, index) in detail.history" :key="index">
            <dt>{{ venueDateTime(entry.created_at) }}</dt>
            <dd>
              <strong>{{ actionLabel(entry.action) }}</strong>
              · {{ entry.actor.name }}<template v-if="entry.reason">: {{ entry.reason }}</template>
            </dd>
          </template>
        </dl>
      </section>
    </template>
  </div>
</template>
