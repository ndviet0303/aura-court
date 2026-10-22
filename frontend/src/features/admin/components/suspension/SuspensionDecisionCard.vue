<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { venueDateTime } from '@/core/utils/venueTime'
import type { Suspension } from '../../types/suspension'
import RemediationStatusChip from './RemediationStatusChip.vue'

defineProps<{ suspension: Suspension }>()

const { t } = useI18n()
</script>

<template>
  <section class="admin-detail-card">
    <div class="decision-head">
      <h2 class="admin-detail-section-title">
        {{ t('admin.suspension.decisionTitle', { id: suspension.id }) }}
      </h2>
      <span class="decision-state" :class="{ 'decision-active': suspension.is_active }">
        {{ t(suspension.is_active ? 'admin.suspension.active' : 'admin.suspension.lifted') }}
      </span>
    </div>
    <dl class="admin-detail-grid">
      <dt>{{ t('admin.suspension.reason') }}</dt>
      <dd>{{ suspension.reason }}</dd>
      <dt>{{ t('admin.suspension.suspendedAt') }}</dt>
      <dd>{{ venueDateTime(suspension.suspended_at) }}</dd>
      <dt>{{ t('admin.suspension.suspendedBy') }}</dt>
      <dd>{{ suspension.suspended_by?.name ?? '—' }}</dd>
      <template v-if="!suspension.is_active">
        <dt>{{ t('admin.suspension.liftedAt') }}</dt>
        <dd>{{ venueDateTime(suspension.lifted_at) || '—' }}</dd>
        <dt>{{ t('admin.suspension.liftedBy') }}</dt>
        <dd>{{ suspension.lifted_by?.name ?? '—' }}</dd>
        <dt>{{ t('admin.suspension.conclusion') }}</dt>
        <dd>{{ suspension.conclusion || '—' }}</dd>
      </template>
      <dt>{{ t('admin.suspension.remediationState') }}</dt>
      <dd><RemediationStatusChip :status="suspension.remediation_status" /></dd>
    </dl>
  </section>
</template>

<style scoped>
.decision-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}

.decision-state {
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  background: rgba(100, 116, 139, 0.12);
  color: #475569;
}

.decision-active {
  background: rgba(239, 68, 68, 0.12);
  color: #b91c1c;
}
</style>
