<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { REVIEW_BODY_MAX, type Review } from '../types/review'

const props = defineProps<{ review: Review }>()
const emit = defineEmits<{ save: [body: string, done: (error: string) => void] }>()

const { t } = useI18n()
const open = ref(false)
const draft = ref('')
const saving = ref(false)
const error = ref('')

function start() {
  draft.value = props.review.reply?.body ?? ''
  error.value = ''
  open.value = true
}

function save() {
  saving.value = true
  emit('save', draft.value, (message) => {
    saving.value = false
    error.value = message
    if (!message) open.value = false
  })
}
</script>

<template>
  <div class="reply-editor">
    <AuraButton v-if="!open" size="sm" variant="outline" icon="reply" @click="start">
      {{ review.reply ? t('reviews.editReply') : t('reviews.reply') }}
    </AuraButton>
    <div v-else class="form-group">
      <textarea
        v-model="draft"
        rows="3"
        :maxlength="REVIEW_BODY_MAX"
        class="form-textarea"
        :aria-label="t('reviews.reply')"
        :placeholder="t('reviews.replyPlaceholder')"
      />
      <div class="reply-actions">
        <span class="form-hint">{{ draft.length }}/{{ REVIEW_BODY_MAX }}</span>
        <AuraButton size="sm" variant="outline" :disabled="saving" @click="open = false">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton size="sm" :loading="saving" :disabled="!draft.trim()" @click="save">
          {{ t('common.save') }}
        </AuraButton>
      </div>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    </div>
  </div>
</template>

<style scoped>
.reply-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.reply-actions .form-hint {
  margin-right: auto;
}
</style>
