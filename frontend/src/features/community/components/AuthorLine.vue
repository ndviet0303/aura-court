<script setup lang="ts">
import { venueDateTime } from '@/core/utils/venueTime'
import type { Author } from '../types/community'

defineProps<{ author: Author; at: string; edited?: boolean }>()
</script>

<template>
  <div class="author-line">
    <img v-if="author.avatar_url" :src="author.avatar_url" alt="" class="author-avatar" />
    <span v-else class="author-avatar" aria-hidden="true">{{
      author.name.charAt(0).toUpperCase()
    }}</span>
    <div class="author-meta">
      <strong>{{ author.name }}</strong>
      <span class="author-time">
        <time :datetime="at">{{ venueDateTime(at) }}</time>
        <template v-if="edited"> · {{ $t('community.edited') }}</template>
      </span>
    </div>
    <slot />
  </div>
</template>

<style scoped>
.author-line {
  display: flex;
  align-items: center;
  gap: 10px;
}

.author-avatar {
  display: inline-flex;
  flex: none;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 999px;
  object-fit: cover;
  background: var(--primary, #6b2d3f);
  color: var(--on-primary, #fff);
  font-weight: 700;
}

.author-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
  font-size: 13px;
}

.author-time {
  color: var(--muted);
  font-size: 12px;
}
</style>
