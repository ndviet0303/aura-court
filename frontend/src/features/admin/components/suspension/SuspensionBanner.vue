<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { venueDateTime } from '@/core/utils/venueTime'
import type { VenueSuspension } from '@/features/court/types/court'

// Task 5.11: the owner sees the active suspension with its reason and a link to the decision.
defineProps<{ suspension: VenueSuspension; organizationId: number; venueId: number }>()

const { t } = useI18n()
</script>

<template>
  <div class="suspension-banner" role="alert">
    <strong>{{ t('admin.suspension.bannerTitle') }}</strong>
    <p class="suspension-banner-text">
      {{ suspension.reason }}
      <span class="suspension-banner-time">· {{ venueDateTime(suspension.suspended_at) }}</span>
    </p>
    <p class="suspension-banner-text">{{ t('admin.suspension.bannerHint') }}</p>
    <RouterLink
      :to="{
        name: 'admin-venue-suspensions',
        params: { organizationId, venueId, suspensionId: suspension.id },
      }"
      class="suspension-banner-link"
    >
      {{ t('admin.suspension.openDecision') }} →
    </RouterLink>
  </div>
</template>

<style scoped>
.suspension-banner {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.08);
  color: #991b1b;
  font-size: 13px;
}

.suspension-banner-text {
  margin: 0;
  white-space: pre-line;
}

.suspension-banner-time {
  color: var(--muted);
}

.suspension-banner-link {
  font-weight: 700;
  color: #991b1b;
}
</style>
