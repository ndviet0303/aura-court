<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { RemediationDecision } from '../../types/suspension'

defineProps<{
  busy?: boolean
  error?: string
  fieldErrors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'submit', payload: { decision: RemediationDecision; feedback: string }): void
}>()

const { t } = useI18n()

const decisions: RemediationDecision[] = ['request_more_info', 'reject', 'approve']
const decision = ref<RemediationDecision>('request_more_info')
const feedback = ref('')
const canSubmit = computed(() => feedback.value.trim().length > 0)

function submit() {
  if (canSubmit.value) emit('submit', { decision: decision.value, feedback: feedback.value.trim() })
}
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">{{ t('admin.remediation.review.title') }}</h2>
    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>
    <fieldset class="review-options">
      <legend class="form-label">{{ t('admin.remediation.review.decision') }}</legend>
      <label v-for="option in decisions" :key="option" class="review-option">
        <input v-model="decision" type="radio" name="remediation-decision" :value="option" />
        <span>{{ t(`admin.remediation.review.decisions.${option}`) }}</span>
      </label>
    </fieldset>
    <p v-if="decision === 'approve'" class="review-warning">
      {{ t('admin.remediation.review.approveWarning') }}
    </p>
    <label class="form-group">
      <span class="form-label">{{ t('admin.remediation.review.feedback') }} *</span>
      <textarea v-model="feedback" rows="4" class="form-textarea" />
      <span v-if="fieldErrors?.feedback?.[0]" class="form-error">{{
        fieldErrors.feedback[0]
      }}</span>
    </label>
    <AuraButton variant="primary" :loading="busy" :disabled="busy || !canSubmit" @click="submit">
      {{ t('admin.remediation.review.submit') }}
    </AuraButton>
  </section>
</template>

<style scoped>
.review-options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  margin: 0 0 12px;
  padding: 0;
  border: none;
}

.review-options legend {
  margin-bottom: 6px;
}

.review-option {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
}

.review-warning {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.1);
  color: #047857;
  font-size: 12px;
}
</style>
