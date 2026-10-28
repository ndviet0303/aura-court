<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import CreateTransferModal from '@/features/marketplace/components/CreateTransferModal.vue'
import type { ChatMessage, Conversation } from '../types/chat'
import MessageBubble from './MessageBubble.vue'
import VoucherGiftPicker from './VoucherGiftPicker.vue'

/**
 * FR-118: text or one image per message; the venue owner can gift a voucher here (FR-111). When the other
 * customer contacted me about my listing, I can create the transaction from here (UC-9.7).
 */
const props = defineProps<{
  conversation: Conversation
  messages: ChatMessage[]
  sending: boolean
  canGift: boolean
  giftOpen: boolean
}>()
const emit = defineEmits<{
  send: [body: string, image: File | null, done: (ok: boolean) => void]
  gift: [code: string, note: string, done: (ok: boolean) => void]
}>()
const { t } = useI18n()

const body = ref('')
const image = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const showGift = ref(props.giftOpen && props.canGift)
const scroller = ref<HTMLElement | null>(null)
const creatingTransfer = ref(false)

// Contact messages are sent by the buyer to the listing's seller, so one I received is about my listing.
const transferOffer = computed(() =>
  props.conversation.type === 'customer_customer'
    ? (props.messages.filter((m) => !m.mine && m.transfer).at(-1)?.transfer ?? null)
    : null,
)

watch(
  () => props.messages.length,
  async () => {
    await nextTick()
    scroller.value?.scrollTo({ top: scroller.value.scrollHeight })
  },
  { immediate: true },
)

function submit() {
  if (!body.value.trim() && !image.value) return
  emit('send', body.value.trim(), image.value, (ok) => {
    if (!ok) return
    body.value = ''
    image.value = null
    if (fileInput.value) fileInput.value.value = ''
  })
}

function gift(code: string, note: string) {
  emit('gift', code, note, (ok) => ok && (showGift.value = false))
}
</script>

<template>
  <section class="thread">
    <header class="thread-head">
      <h2 class="thread-title">{{ conversation.title }}</h2>
      <p v-if="conversation.venue && conversation.venue.name !== conversation.title" class="muted">
        {{ conversation.venue.name }}
      </p>
    </header>

    <div ref="scroller" class="messages" role="log" aria-live="polite">
      <p v-if="!messages.length" class="muted">{{ t('chat.noMessages') }}</p>
      <MessageBubble v-for="m in messages" :key="m.id" :message="m" />
    </div>

    <VoucherGiftPicker
      v-if="showGift && conversation.venue"
      :venue-id="conversation.venue.id"
      :busy="sending"
      @gift="gift"
      @close="showGift = false"
    />

    <form class="composer" @submit.prevent="submit">
      <textarea
        v-model="body"
        class="input"
        rows="2"
        maxlength="2000"
        :placeholder="t('chat.placeholder')"
        :aria-label="t('chat.placeholder')"
        @keydown.enter.exact.prevent="submit"
      />
      <div class="composer-actions">
        <label class="attach" :title="t('chat.attach')">
          <span class="material-symbols-outlined" aria-hidden="true">image</span>
          <span class="sr-only">{{ t('chat.attach') }}</span>
          <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            hidden
            @change="image = ($event.target as HTMLInputElement).files?.[0] ?? null"
          />
        </label>
        <span v-if="image" class="file-name">{{ image.name }}</span>
        <AuraButton
          v-if="transferOffer"
          type="button"
          size="sm"
          variant="outline"
          data-testid="chat-create-transfer"
          @click="creatingTransfer = true"
        >
          {{ t('chat.transfer.create') }}
        </AuraButton>
        <AuraButton
          v-if="canGift && !showGift"
          type="button"
          size="sm"
          variant="outline"
          @click="showGift = true"
        >
          {{ t('customers.gift') }}
        </AuraButton>
        <AuraButton type="submit" size="sm" :loading="sending">{{ t('chat.send') }}</AuraButton>
      </div>
    </form>

    <CreateTransferModal
      v-if="creatingTransfer && transferOffer"
      :listing-id="transferOffer.listing_id"
      :conversation-id="conversation.id"
      :reservation-ids="transferOffer.items.map((item) => item.reservation_id)"
      @close="creatingTransfer = false"
    />
  </section>
</template>

<style scoped>
.thread {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 0;
  height: 100%;
}

.thread-head {
  padding-bottom: 8px;
  border-bottom: 1px solid var(--outline);
}

.thread-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.messages {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 8px;
  min-height: 240px;
  max-height: 60vh;
  overflow-y: auto;
}

.composer {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background: var(--surface);
  color: var(--ink);
  resize: vertical;
}

.composer-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.attach {
  display: inline-flex;
  margin-right: auto;
  color: var(--muted);
  cursor: pointer;
}

.file-name {
  color: var(--muted);
  font-size: 12px;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
