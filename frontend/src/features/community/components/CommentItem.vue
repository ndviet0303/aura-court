<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { COMMENT_MAX, type Comment } from '../types/community'
import AuthorLine from './AuthorLine.vue'

const props = defineProps<{ comment: Comment; signedIn: boolean }>()
const emit = defineEmits<{
  save: [content: string, done: (ok: boolean) => void]
  delete: []
  report: []
}>()

const { t } = useI18n()
const editing = ref(false)
const draft = ref('')
const saving = ref(false)

function startEdit() {
  draft.value = props.comment.content
  editing.value = true
}

function save() {
  saving.value = true
  emit('save', draft.value.trim(), (ok) => {
    saving.value = false
    if (ok) editing.value = false
  })
}
</script>

<template>
  <li class="comment">
    <AuthorLine
      :author="comment.author"
      :at="comment.created_at"
      :edited="comment.updated_at !== comment.created_at"
    />
    <div v-if="editing" class="form-group">
      <textarea
        v-model="draft"
        rows="3"
        :maxlength="COMMENT_MAX"
        class="form-textarea"
        :aria-label="t('community.comment.edit')"
      />
      <div class="comment-edit-actions">
        <span class="form-hint">{{ draft.length }}/{{ COMMENT_MAX }}</span>
        <AuraButton size="sm" variant="outline" :disabled="saving" @click="editing = false">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton size="sm" :loading="saving" :disabled="!draft.trim()" @click="save">
          {{ t('common.save') }}
        </AuraButton>
      </div>
    </div>
    <p v-else class="comment-body">{{ comment.content }}</p>
    <div v-if="!editing" class="comment-actions">
      <template v-if="comment.is_mine">
        <button type="button" @click="startEdit">{{ t('common.edit') }}</button>
        <button type="button" @click="emit('delete')">{{ t('common.delete') }}</button>
      </template>
      <button v-else-if="signedIn" type="button" @click="emit('report')">
        {{ t('community.report.action') }}
      </button>
    </div>
  </li>
</template>

<style scoped>
.comment {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px 0;
  border-top: 1px solid var(--outline);
}

.comment-body {
  margin: 0 0 0 46px;
  white-space: pre-line;
  overflow-wrap: anywhere;
  font-size: 14px;
}

.comment-actions {
  display: flex;
  gap: 12px;
  margin-left: 46px;
}

.comment-actions button {
  padding: 0;
  border: 0;
  background: none;
  color: var(--muted);
  font-size: 12px;
  cursor: pointer;
}

.comment-actions button:hover {
  color: var(--ink);
}

.comment-edit-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.comment-edit-actions .form-hint {
  margin-right: auto;
}
</style>
