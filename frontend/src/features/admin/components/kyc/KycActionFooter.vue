<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'

const props = defineProps<{
  selectedReason: string
  customReason: string
  isSubmitting: boolean
  isApproved?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:selectedReason', val: string): void
  (e: 'update:customReason', val: string): void
  (e: 'approve'): void
  (e: 'reject'): void
  (e: 'requestSupplement'): void
}>()

const { t } = useI18n()

// A template only pre-fills the reason; the reviewer can edit it before deciding.
function pickTemplate(value: string) {
  emit('update:selectedReason', value)
  if (value) emit('update:customReason', value)
}

const reasonOptions = computed(() => [
  { value: '', label: t('admin.kyc.reasonSelectPlaceholder') },
  { value: t('admin.kyc.reasonCccdBlurry'), label: t('admin.kyc.reasonCccdBlurry') },
  { value: t('admin.kyc.reasonNameMismatch'), label: t('admin.kyc.reasonNameMismatch') },
  { value: t('admin.kyc.reasonBankMismatch'), label: t('admin.kyc.reasonBankMismatch') },
  { value: t('admin.kyc.reasonMissingLease'), label: t('admin.kyc.reasonMissingLease') },
  { value: t('admin.kyc.reasonTaxSuspended'), label: t('admin.kyc.reasonTaxSuspended') },
])
</script>

<template>
  <div class="kyc-action-footer">
    <div class="footer-left">
      <SelectDropdown
        :model-value="selectedReason"
        :options="reasonOptions"
        variant="bordered"
        :placeholder="t('admin.kyc.reasonSelectPlaceholder')"
        @update:model-value="pickTemplate(String($event))"
      />
      <label class="reason-label" for="kyc-review-reason">{{ t('admin.kyc.reasonLabel') }}</label>
      <textarea
        id="kyc-review-reason"
        class="reason-input"
        :value="props.customReason"
        rows="3"
        maxlength="1000"
        :placeholder="t('admin.kyc.reasonInputPlaceholder')"
        @input="emit('update:customReason', ($event.target as HTMLTextAreaElement).value)"
      />
      <span class="reason-count">{{ props.customReason.trim().length }}/1000</span>
    </div>

    <div class="footer-actions">
      <!-- Request Supplement -->
      <AuraButton variant="outline" :disabled="isSubmitting" @click="emit('requestSupplement')">
        <span class="material-symbols-outlined icon-18">edit_note</span>
        <span>{{ t('admin.kyc.btnSupplement') }}</span>
      </AuraButton>

      <!-- Reject -->
      <AuraButton variant="danger" :disabled="isSubmitting" @click="emit('reject')">
        <span class="material-symbols-outlined icon-18">block</span>
        <span>{{ t('admin.kyc.btnReject') }}</span>
      </AuraButton>

      <!-- Approve & Activate -->
      <AuraButton variant="primary" :disabled="isSubmitting || isApproved" @click="emit('approve')">
        <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': isSubmitting }">
          {{ isSubmitting ? 'sync' : 'verified' }}
        </span>
        <span>{{ isSubmitting ? t('admin.kyc.submitting') : t('admin.kyc.btnApprove') }}</span>
      </AuraButton>
    </div>
  </div>
</template>

<style scoped>
.kyc-action-footer {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 16px;
  padding: 14px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
  flex-wrap: wrap;
}

.footer-left {
  flex: 1;
  min-width: 280px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.reason-label {
  font-size: 13px;
  font-weight: 600;
}

.reason-input {
  width: 100%;
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 10px;
  padding: 8px 10px;
  font: inherit;
  resize: vertical;
}

.reason-count {
  align-self: flex-end;
  font-size: 12px;
  color: var(--muted, #6e6668);
}

.footer-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.icon-18 {
  font-size: 18px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}
</style>
