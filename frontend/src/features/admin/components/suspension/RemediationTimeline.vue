<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { venueDateTime } from '@/core/utils/venueTime'
import type { Remediation } from '../../types/suspension'
import RemediationStatusChip from './RemediationStatusChip.vue'

// Every submission is immutable and listed oldest first, each with the admin's response (D14).
defineProps<{ remediations: Remediation[]; highlightId?: number }>()

const { t } = useI18n()

const fileSize = (bytes: number) =>
  bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.ceil(bytes / 1024)} KB`
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">{{ t('admin.remediation.historyTitle') }}</h2>
    <p v-if="!remediations.length" class="admin-detail-muted">
      {{ t('admin.remediation.noSubmissions') }}
    </p>
    <ol v-else class="remediation-list">
      <li
        v-for="(item, index) in remediations"
        :key="item.id"
        class="remediation-item"
        :class="{ 'remediation-current': item.id === highlightId }"
      >
        <div class="remediation-head">
          <strong>{{ t('admin.remediation.submissionNo', { n: index + 1 }) }}</strong>
          <RemediationStatusChip :status="item.status" />
          <span class="admin-detail-muted">
            {{ venueDateTime(item.submitted_at) }}
            <template v-if="item.submitted_by"> · {{ item.submitted_by.name }}</template>
          </span>
        </div>
        <p class="remediation-content">{{ item.content }}</p>
        <ul v-if="item.files.length" class="remediation-files">
          <li v-for="file in item.files" :key="file.url">
            <a :href="file.url" target="_blank" rel="noopener noreferrer">{{ file.name }}</a>
            <span class="admin-detail-muted"> ({{ fileSize(file.size) }})</span>
          </li>
        </ul>
        <div v-if="item.review_note" class="remediation-review">
          <span class="admin-detail-muted">
            {{ t('admin.remediation.reviewedBy') }}
            {{ item.reviewed_by?.name ?? '—' }} · {{ venueDateTime(item.reviewed_at) }}
          </span>
          <p class="remediation-content">{{ item.review_note }}</p>
        </div>
      </li>
    </ol>
    <p v-if="remediations.some((item) => item.files.length)" class="admin-detail-muted">
      {{ t('admin.remediation.linksExpire') }}
    </p>
  </section>
</template>

<style scoped>
.remediation-list {
  list-style: none;
  margin: 0 0 8px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.remediation-item {
  padding: 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
}

.remediation-current {
  border-color: var(--primary, #6b2d3f);
}

.remediation-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.remediation-content {
  margin: 8px 0 0;
  font-size: 13px;
  white-space: pre-line;
}

.remediation-files {
  margin: 8px 0 0;
  padding-left: 18px;
  font-size: 13px;
}

.remediation-review {
  margin-top: 10px;
  padding: 10px;
  border-radius: 10px;
  background: rgba(100, 116, 139, 0.08);
  font-size: 12px;
}
</style>
