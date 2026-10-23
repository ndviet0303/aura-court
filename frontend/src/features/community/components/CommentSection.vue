<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { COMMENT_MAX, type Comment } from '../types/community'
import CommentItem from './CommentItem.vue'
import LoginPrompt from './LoginPrompt.vue'

defineProps<{ comments: Comment[]; signedIn: boolean; sending: boolean }>()
const emit = defineEmits<{
  add: [content: string, done: (ok: boolean) => void]
  save: [comment: Comment, content: string, done: (ok: boolean) => void]
  delete: [comment: Comment]
  report: [comment: Comment]
}>()

const { t } = useI18n()
const draft = ref('')

function add() {
  emit('add', draft.value.trim(), (ok) => {
    if (ok) draft.value = ''
  })
}
</script>

<template>
  <section class="comments">
    <h2 class="comments-title">{{ t('community.commentsCount', { count: comments.length }) }}</h2>
    <form v-if="signedIn" class="form-group" @submit.prevent="add">
      <textarea
        v-model="draft"
        rows="3"
        :maxlength="COMMENT_MAX"
        class="form-textarea"
        :aria-label="t('community.comment.placeholder')"
        :placeholder="t('community.comment.placeholder')"
      />
      <div class="comments-form-actions">
        <span class="form-hint">{{ draft.length }}/{{ COMMENT_MAX }}</span>
        <AuraButton type="submit" size="sm" :loading="sending" :disabled="!draft.trim()">
          {{ t('community.comment.submit') }}
        </AuraButton>
      </div>
    </form>
    <LoginPrompt v-else :text="t('community.comment.loginToComment')" />

    <ul class="comments-list">
      <CommentItem
        v-for="comment in comments"
        :key="comment.id"
        :comment="comment"
        :signed-in="signedIn"
        @save="(content, done) => emit('save', comment, content, done)"
        @delete="emit('delete', comment)"
        @report="emit('report', comment)"
      />
    </ul>
  </section>
</template>

<style scoped>
.comments {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.comments-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.comments-form-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.comments-list {
  margin: 0;
  padding: 0;
  list-style: none;
}
</style>
