<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { venueDateTime } from '@/core/utils/venueTime'
import type { Post } from '../types/community'
import AuthorLine from './AuthorLine.vue'
import ImageGrid from './ImageGrid.vue'

defineProps<{ post: Post; signedIn: boolean; linked?: boolean }>()
const emit = defineEmits<{ edit: []; delete: []; report: []; message: [] }>()
const { t } = useI18n()
</script>

<template>
  <article class="post-card">
    <AuthorLine
      :author="post.author"
      :at="post.created_at"
      :edited="post.updated_at !== post.created_at"
    >
      <span class="post-type" :class="`post-type--${post.type}`">
        {{ t(`community.types.${post.type}`) }}
      </span>
    </AuthorLine>

    <p class="post-content">{{ post.content }}</p>
    <ImageGrid :images="post.images" />

    <!-- BR-146: details to talk about only; nothing is joined, held or paid here. -->
    <div v-if="post.type === 'matchmaking'" class="post-match">
      <dl class="admin-detail-grid">
        <template v-if="post.sport">
          <dt>{{ t('community.match.sport') }}</dt>
          <dd>{{ t(`sportTypes.${post.sport}`) }}</dd>
        </template>
        <dt>{{ t('community.match.place') }}</dt>
        <dd>
          {{ post.venue?.name ?? post.address }}
          <template v-if="post.court_code"> · {{ post.court_code }}</template>
        </dd>
        <template v-if="post.play_at">
          <dt>{{ t('community.match.playAt') }}</dt>
          <dd>{{ venueDateTime(post.play_at) }}</dd>
        </template>
      </dl>
      <p class="post-disclaimer">{{ t('community.match.disclaimer') }}</p>
    </div>

    <footer class="post-actions">
      <RouterLink v-if="linked" :to="`/community/posts/${post.id}`" class="post-action">
        <span class="material-symbols-outlined">chat_bubble</span>
        {{ t('community.commentsCount', { count: post.comments_count }) }}
      </RouterLink>
      <span v-else class="post-action">
        <span class="material-symbols-outlined">chat_bubble</span>
        {{ t('community.commentsCount', { count: post.comments_count }) }}
      </span>
      <template v-if="signedIn && !post.is_mine">
        <button type="button" class="post-action" @click="emit('message')">
          <span class="material-symbols-outlined">mail</span>{{ t('community.post.message') }}
        </button>
        <button type="button" class="post-action" @click="emit('report')">
          <span class="material-symbols-outlined">flag</span>{{ t('community.report.action') }}
        </button>
      </template>
      <template v-if="post.is_mine">
        <button type="button" class="post-action" @click="emit('edit')">
          <span class="material-symbols-outlined">edit</span>{{ t('common.edit') }}
        </button>
        <button type="button" class="post-action danger" @click="emit('delete')">
          <span class="material-symbols-outlined">delete</span>{{ t('common.delete') }}
        </button>
      </template>
    </footer>
  </article>
</template>

<style scoped>
.post-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.post-type {
  margin-left: auto;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
  background: rgba(2, 132, 199, 0.12);
  color: #0284c7;
}

.post-type--matchmaking {
  background: rgba(5, 150, 105, 0.12);
  color: #059669;
}

.post-content {
  margin: 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
  font-size: 14px;
  line-height: 1.5;
}

.post-match {
  padding: 12px;
  border-radius: 12px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  font-size: 13px;
}

.post-disclaimer {
  margin: 8px 0 0;
  color: var(--muted);
  font-size: 12px;
  font-style: italic;
}

.post-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 14px;
}

.post-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 0;
  border: 0;
  background: none;
  color: var(--muted);
  font-size: 13px;
  text-decoration: none;
  cursor: pointer;
}

.post-action:hover {
  color: var(--ink);
}

.post-action.danger:hover {
  color: var(--danger, #ef4444);
}

.post-action .material-symbols-outlined {
  font-size: 18px;
}
</style>
