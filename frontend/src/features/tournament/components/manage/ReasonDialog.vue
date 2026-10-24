<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'

// Reject (reason required) and stop (reason optional) both confirm with a short note.
const props = defineProps<{
  title: string
  message?: string
  required: boolean
  confirmText: string
  busy: boolean
  error: string
}>()
const emit = defineEmits<{ close: []; confirm: [reason: string] }>()
const { t } = useI18n()
const reason = ref('')
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm" role="dialog" aria-modal="true" aria-labelledby="reason-title">
      <div class="modal-header">
        <h3 id="reason-title" class="modal-title">{{ title }}</h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="modal-body">
        <p v-if="message" class="admin-detail-muted">{{ message }}</p>
        <label class="form-group">
          <span class="form-label">
            {{ t('tournament.manage.reason') }}{{ props.required ? ' *' : '' }}
          </span>
          <textarea v-model="reason" rows="3" maxlength="1000" class="form-textarea" />
        </label>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      </div>
      <div class="modal-footer">
        <AuraButton variant="outline" :disabled="busy" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          variant="danger"
          :loading="busy"
          :disabled="required && !reason.trim()"
          @click="emit('confirm', reason)"
        >
          {{ confirmText }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>
