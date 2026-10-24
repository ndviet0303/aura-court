<script setup lang="ts">
import { venueDateTime } from '@/core/utils/venueTime'
import type { Review } from '../types/review'
import AuthorLine from './AuthorLine.vue'
import ImageGrid from './ImageGrid.vue'
import StarRating from './StarRating.vue'

defineProps<{ review: Review }>()
</script>

<template>
  <article class="review">
    <AuthorLine :author="review.author" :at="review.created_at">
      <StarRating class="review-stars" :rating="review.rating" />
    </AuthorLine>
    <p v-if="review.court" class="review-court">
      {{ review.court.code }}
    </p>
    <p v-if="review.body" class="review-body">{{ review.body }}</p>
    <ImageGrid :images="review.images" />
    <!-- The venue's reply is read-only everywhere except the owner's reply editor. -->
    <div v-if="review.reply" class="review-reply">
      <strong>{{ $t('reviews.replyFromVenue') }}</strong>
      <span class="review-reply-time">{{ venueDateTime(review.reply.replied_at) }}</span>
      <p>{{ review.reply.body }}</p>
    </div>
    <slot />
  </article>
</template>

<style scoped>
.review {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 0;
  border-top: 1px solid var(--outline);
}

.review-stars {
  margin-left: auto;
}

.review-court {
  margin: 0;
  color: var(--muted);
  font-size: 12px;
}

.review-body {
  margin: 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
  font-size: 14px;
  line-height: 1.5;
}

.review-reply {
  padding: 10px 12px;
  border-left: 3px solid var(--primary, #6b2d3f);
  border-radius: 8px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  font-size: 13px;
}

.review-reply-time {
  margin-left: 8px;
  color: var(--muted);
  font-size: 12px;
}

.review-reply p {
  margin: 4px 0 0;
  white-space: pre-line;
}
</style>
