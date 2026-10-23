<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { venueDateTime } from '@/core/utils/venueTime'
import type { ContentReportDetail } from '../types/moderation'
import ImageGrid from './ImageGrid.vue'
import StarRating from './StarRating.vue'

defineProps<{
  target: ContentReportDetail['target']
  context: ContentReportDetail['context']
}>()
const { t } = useI18n()
</script>

<template>
  <section class="admin-detail-card reported">
    <h2 class="admin-detail-section-title">{{ t('moderation.content') }}</h2>
    <p v-if="!target" class="admin-detail-muted">{{ t('moderation.targetDeleted') }}</p>
    <template v-else>
      <p class="admin-detail-muted">
        {{ target.author.name }} · {{ venueDateTime(target.created_at) }}
        <strong v-if="target.is_hidden" class="reported-hidden">
          · {{ t('moderation.hidden') }}
        </strong>
      </p>
      <StarRating v-if="target.rating" :rating="target.rating" />
      <p class="reported-text">{{ target.content }}</p>
      <ImageGrid :images="target.images" />
    </template>

    <h2 class="admin-detail-section-title reported-context">{{ t('moderation.context') }}</h2>
    <dl class="admin-detail-grid">
      <template v-if="context.venue">
        <dt>{{ t('moderation.venue') }}</dt>
        <dd>{{ context.venue.name }}</dd>
      </template>
      <template v-if="context.reservation_code">
        <dt>{{ t('moderation.reservation') }}</dt>
        <dd>{{ context.reservation_code }}</dd>
      </template>
      <template v-if="context.post">
        <dt>{{ t('moderation.parentPost') }}</dt>
        <dd>
          <strong>{{ context.post.author.name }}:</strong> {{ context.post.content }}
        </dd>
      </template>
    </dl>
    <template v-if="context.comments?.length">
      <p class="form-label">{{ t('moderation.nearbyComments') }}</p>
      <ul class="reported-comments">
        <li
          v-for="comment in context.comments"
          :key="comment.id"
          :class="{ current: target?.type === 'comment' && comment.id === target.id }"
        >
          <strong>{{ comment.author.name }}:</strong> {{ comment.content }}
        </li>
      </ul>
    </template>
  </section>
</template>

<style scoped>
.reported {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.reported-hidden {
  color: var(--danger, #ef4444);
}

.reported-text {
  margin: 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.reported-context {
  margin-top: 12px;
}

.reported-comments {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
}

.reported-comments li {
  padding: 6px 10px;
  border-radius: 8px;
  overflow-wrap: anywhere;
}

.reported-comments li.current {
  background: rgba(239, 68, 68, 0.1);
}
</style>
