<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { venueDateTime } from '@/core/utils/venueTime'
import { useVenueSuspensions } from '../composables/useVenueSuspensions'
import SuspensionDecisionCard from '../components/suspension/SuspensionDecisionCard.vue'
import RemediationTimeline from '../components/suspension/RemediationTimeline.vue'
import RemediationForm from '../components/suspension/RemediationForm.vue'

// Task 5.11: owner-only decision history, remediation history and submission form (BR-45).
const route = useRoute()
const { t } = useI18n()

const organizationId = computed(() => Number(route.params.organizationId))
const venueId = computed(() => Number(route.params.venueId))
const suspensionId = computed(() => Number(route.params.suspensionId))

const {
  loading,
  error,
  suspensions,
  decision,
  canSubmit,
  submitting,
  submitError,
  fieldErrors,
  notice,
  submit,
} = useVenueSuspensions(organizationId, venueId, suspensionId)
</script>

<template>
  <div class="admin-detail-page">
    <RouterLink
      :to="{ name: 'admin-venue-detail', params: { organizationId, venueId } }"
      class="admin-detail-back"
    >
      ← {{ t('admin.suspension.backToVenue') }}
    </RouterLink>
    <h1 class="admin-detail-title">{{ t('admin.suspension.historyTitle') }}</h1>

    <p v-if="loading && !decision" class="admin-detail-muted">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="admin-detail-error" role="alert">{{ error }}</p>

    <template v-else-if="decision">
      <nav v-if="suspensions.length > 1" class="decision-tabs">
        <RouterLink
          v-for="item in suspensions"
          :key="item.id"
          :to="{
            name: 'admin-venue-suspensions',
            params: { organizationId, venueId, suspensionId: item.id },
          }"
          class="decision-tab"
          :class="{ 'decision-tab-active': item.id === decision.id }"
        >
          {{ venueDateTime(item.suspended_at) }}
          · {{ t(item.is_active ? 'admin.suspension.active' : 'admin.suspension.lifted') }}
        </RouterLink>
      </nav>

      <p v-if="notice" class="admin-detail-notice" role="status">{{ notice }}</p>
      <SuspensionDecisionCard :suspension="decision" />
      <RemediationTimeline :remediations="decision.remediations ?? []" />
      <RemediationForm
        v-if="canSubmit"
        :busy="submitting"
        :error="submitError"
        :field-errors="fieldErrors"
        @submit="submit"
      />
      <p v-else-if="decision.is_active" class="admin-detail-muted">
        {{ t(`admin.remediation.blocked.${decision.remediation_status}`) }}
      </p>
    </template>
  </div>
</template>

<style scoped>
.decision-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.decision-tab {
  padding: 6px 12px;
  border: 1px solid var(--outline);
  border-radius: 999px;
  color: var(--muted);
  font-size: 12px;
  text-decoration: none;
}

.decision-tab-active {
  border-color: var(--ink);
  color: var(--ink);
  font-weight: 700;
}
</style>
