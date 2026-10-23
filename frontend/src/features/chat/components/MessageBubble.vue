<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency, formatDateTime } from '@/i18n'
import type { ChatMessage } from '../types/chat'

/** BR-158 names the side; FR-111 shows a gifted code with what it offered when sent. */
const props = defineProps<{ message: ChatMessage }>()
const { t } = useI18n()

const discount = computed(() => {
  const v = props.message.voucher
  if (!v) return ''
  if (v.discount_kind === 'percent') {
    const cap = v.max_discount
      ? ` (${t('chat.voucher.max')} ${formatCurrency(v.max_discount)})`
      : ''
    return `-${(v.percent_bp ?? 0) / 100}%${cap}`
  }
  return `-${formatCurrency(v.amount ?? 0)}`
})
</script>

<template>
  <div class="bubble-row" :class="{ mine: message.mine }">
    <div class="bubble">
      <p class="sender">
        {{ message.sender_name }}
        <span v-if="message.sent_by" class="sent-by">· {{ message.sent_by }}</span>
      </p>
      <div v-if="message.voucher" class="voucher">
        <p class="voucher-code">{{ message.voucher.code }}</p>
        <p>{{ message.voucher.program }} · {{ discount }}</p>
        <p v-if="message.voucher.min_rental">
          {{ t('chat.voucher.minRental') }} {{ formatCurrency(message.voucher.min_rental) }}
        </p>
        <p v-if="message.voucher.courts.length">
          {{ t('chat.voucher.courts') }}: {{ message.voucher.courts.join(', ') }}
        </p>
        <p v-if="message.voucher.ends_at">
          {{ t('chat.voucher.until') }} {{ formatDateTime(message.voucher.ends_at) }}
        </p>
      </div>
      <a v-if="message.image_url" :href="message.image_url" target="_blank" rel="noopener">
        <img :src="message.image_url" :alt="t('chat.image')" class="image" loading="lazy" />
      </a>
      <p v-if="message.body" class="body">{{ message.body }}</p>
      <time class="time" :datetime="message.created_at">{{
        formatDateTime(message.created_at)
      }}</time>
    </div>
  </div>
</template>

<style scoped>
.bubble-row {
  display: flex;
}

.bubble-row.mine {
  justify-content: flex-end;
}

.bubble {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-width: min(75%, 520px);
  padding: 8px 12px;
  border-radius: 14px;
  background: var(--surface-muted, rgba(0, 0, 0, 0.05));
}

.mine .bubble {
  background: var(--primary-soft, rgba(107, 45, 63, 0.12));
}

.sender {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
}

.sent-by,
.time {
  color: var(--muted);
  font-size: 11px;
  font-weight: 400;
}

.body {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.image {
  max-width: 240px;
  max-height: 240px;
  border-radius: 10px;
}

.voucher {
  padding: 8px 10px;
  border: 1px dashed var(--primary);
  border-radius: 10px;
  font-size: 13px;
}

.voucher p {
  margin: 0;
}

.voucher-code {
  font-weight: 800;
  letter-spacing: 0.04em;
}
</style>
