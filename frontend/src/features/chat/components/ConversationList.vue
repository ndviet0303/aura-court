<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatDateTime } from '@/i18n'
import type { Conversation } from '../types/chat'

defineProps<{ conversations: Conversation[]; activeId: number | null }>()
const emit = defineEmits<{ select: [id: number] }>()
const { t } = useI18n()
</script>

<template>
  <p v-if="!conversations.length" class="muted">{{ t('chat.empty') }}</p>
  <ul v-else class="list">
    <li v-for="item in conversations" :key="item.id">
      <button
        type="button"
        class="item"
        :class="{ active: item.id === activeId }"
        :aria-current="item.id === activeId ? 'true' : undefined"
        @click="emit('select', item.id)"
      >
        <span class="title">{{ item.title }}</span>
        <span
          v-if="item.venue && item.side !== 'venue' && item.venue.name !== item.title"
          class="tag"
          >{{ item.venue.name }}</span
        >
        <span class="preview">
          <template v-if="item.last_message">
            {{ item.last_message.mine ? `${t('chat.you')}: ` : '' }}{{ item.last_message.preview }}
          </template>
        </span>
        <span v-if="item.last_message_at" class="time">{{
          formatDateTime(item.last_message_at)
        }}</span>
      </button>
    </li>
  </ul>
</template>

<style scoped>
.list {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.item {
  display: grid;
  width: 100%;
  gap: 2px;
  padding: 10px 12px;
  border: 0;
  border-radius: 12px;
  background: none;
  color: var(--ink);
  text-align: left;
  cursor: pointer;
}

.item:hover,
.item.active {
  background: var(--surface-muted, rgba(0, 0, 0, 0.05));
}

.title {
  font-weight: 700;
}

.tag,
.time {
  color: var(--muted);
  font-size: 12px;
}

.preview {
  overflow: hidden;
  color: var(--muted);
  font-size: 13px;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
