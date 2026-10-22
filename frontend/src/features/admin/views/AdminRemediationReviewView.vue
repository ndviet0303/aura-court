<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { useRemediationReview } from '../composables/useRemediationReview'
import SuspensionDecisionCard from '../components/suspension/SuspensionDecisionCard.vue'
import RemediationTimeline from '../components/suspension/RemediationTimeline.vue'
import RemediationReviewForm from '../components/suspension/RemediationReviewForm.vue'

// Task 5.10: the platform admin reviews one submission against the decision and its history (D14).
const route = useRoute()
const { t } = useI18n()

const remediationId = computed(() => Number(route.params.id))
const { loading, error, remediation, saving, reviewError, fieldErrors, notice, review } =
  useRemediationReview(remediationId)
const suspension = computed(() => remediation.value?.suspension ?? null)
</script>

<template>
  <div class="admin-detail-page">
    <RouterLink :to="{ name: 'admin-venue-remediations' }" class="admin-detail-back">
      ← {{ t('admin.remediation.backToQueue') }}
    </RouterLink>

    <p v-if="loading && !remediation" class="admin-detail-muted">{{ t('common.loading') }}</p>
    <p v-else-if="error && !remediation" class="admin-detail-error" role="alert">{{ error }}</p>

    <template v-else-if="remediation">
      <header class="admin-detail-header">
        <div>
          <h1 class="admin-detail-title">
            {{ resolveLocalizedText(suspension?.venue?.name) }}
          </h1>
          <p class="admin-detail-muted">{{ suspension?.venue?.organization?.name }}</p>
        </div>
        <RouterLink
          v-if="suspension"
          :to="{ name: 'admin-monitoring-venue-detail', params: { venueId: suspension.venue_id } }"
        >
          {{ t('admin.monitoring.openVenue') }}
        </RouterLink>
      </header>

      <p v-if="notice" class="admin-detail-notice" role="status">{{ notice }}</p>
      <SuspensionDecisionCard v-if="suspension" :suspension="suspension" />
      <RemediationTimeline
        :remediations="suspension?.remediations ?? [remediation]"
        :highlight-id="remediation.id"
      />
      <RemediationReviewForm
        v-if="remediation.status === 'pending' && suspension?.is_active"
        :busy="saving"
        :error="reviewError"
        :field-errors="fieldErrors"
        @submit="review"
      />
      <p v-else-if="reviewError" class="admin-detail-error" role="alert">{{ reviewError }}</p>
    </template>
  </div>
</template>
