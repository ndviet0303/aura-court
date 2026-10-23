<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useContentReport } from '../composables/useContentReport'
import { REPORT_REASON_MAX, type ReportTarget } from '../types/community'

const props = defineProps<{ target: ReportTarget }>()
const emit = defineEmits<{ close: []; done: [] }>()

const { t } = useI18n()
const { sending, error, submit } = useContentReport()
const reason = ref('')
const sent = ref(false)

async function send() {
  if (await submit(props.target, reason.value)) sent.value = true
}
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm" role="dialog" aria-modal="true" aria-labelledby="report-title">
      <div class="modal-header">
        <h3 id="report-title" class="modal-title">
          {{ t(`community.report.title.${target.type}`) }}
        </h3>
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
        <p v-if="sent" role="status">{{ t('community.report.sent') }}</p>
        <label v-else class="form-group">
          <span class="form-label">{{ t('community.report.reason') }} *</span>
          <textarea
            v-model="reason"
            rows="4"
            :maxlength="REPORT_REASON_MAX"
            class="form-textarea"
            :placeholder="t('community.report.reasonPlaceholder')"
          />
          <span class="form-hint">{{ reason.length }}/{{ REPORT_REASON_MAX }}</span>
        </label>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      </div>
      <div class="modal-footer">
        <AuraButton v-if="sent" @click="emit('done')">{{ t('common.close') }}</AuraButton>
        <template v-else>
          <AuraButton variant="outline" :disabled="sending" @click="emit('close')">
            {{ t('common.cancel') }}
          </AuraButton>
          <AuraButton variant="danger" :loading="sending" :disabled="!reason.trim()" @click="send">
            {{ t('community.report.submit') }}
          </AuraButton>
        </template>
      </div>
    </div>
  </div>
</template>
