import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage } from '@/core/utils/errorMessage'
import { chatApi } from '../api/chatApi'
import type { ChatMessage, Conversation } from '../types/chat'

// ponytail: polling, not websockets; FR-118 needs delivery, not instant push. Move to Reverb if chat volume grows.
const MESSAGE_POLL_MS = 10_000
const LIST_POLL_MS = 30_000

/** FR-118 (UC-7.13–UC-7.15): the viewer's conversations and the open one, kept fresh by polling. */
export function useChat() {
  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()

  const conversations = ref<Conversation[]>([])
  const active = ref<Conversation | null>(null)
  const messages = ref<ChatMessage[]>([])
  const loading = ref(false)
  const sending = ref(false)
  const error = ref('')
  const activeId = computed(() => Number(route.query.conversation) || null)
  let timers: number[] = []

  async function loadList() {
    try {
      conversations.value = (await chatApi.list()).data
    } catch (e) {
      error.value = errorMessage(e, t('chat.loadError'))
    }
  }

  async function loadMessages(fresh: boolean) {
    const id = activeId.value
    if (!id) return
    const after = fresh ? undefined : messages.value.at(-1)?.id
    if (fresh) loading.value = true
    try {
      const result = await chatApi.messages(id, after)
      if (id !== activeId.value) return
      active.value = result.conversation
      messages.value = fresh ? result.data : [...messages.value, ...result.data]
      error.value = ''
    } catch (e) {
      error.value = errorMessage(e, t('chat.loadError'))
    } finally {
      if (fresh) loading.value = false
    }
  }

  function append(message: ChatMessage) {
    if (!messages.value.some((m) => m.id === message.id)) messages.value.push(message)
    void loadList()
  }

  async function send(body: string, image: File | null): Promise<boolean> {
    if (!activeId.value) return false
    sending.value = true
    try {
      append((await chatApi.send(activeId.value, body, image)).message)
      return true
    } catch (e) {
      error.value = errorMessage(e, t('chat.sendError'))
      return false
    } finally {
      sending.value = false
    }
  }

  async function gift(code: string, note: string): Promise<boolean> {
    if (!activeId.value) return false
    sending.value = true
    try {
      append((await chatApi.gift(activeId.value, code, note)).message)
      return true
    } catch (e) {
      error.value = errorMessage(e, t('chat.giftError'))
      return false
    } finally {
      sending.value = false
    }
  }

  watch(activeId, () => {
    active.value = null
    messages.value = []
    void loadMessages(true)
  })

  onMounted(() => {
    void loadList()
    void loadMessages(true)
    const tick = (fn: () => void) => () => document.visibilityState === 'visible' && fn()
    timers = [
      window.setInterval(
        tick(() => void loadMessages(false)),
        MESSAGE_POLL_MS,
      ),
      window.setInterval(
        tick(() => void loadList()),
        LIST_POLL_MS,
      ),
    ]
  })
  onUnmounted(() => timers.forEach((id) => window.clearInterval(id)))

  return {
    conversations,
    active,
    activeId,
    messages,
    loading,
    sending,
    error,
    send,
    gift,
    select: (id: number) => router.replace({ query: { conversation: id } }),
  }
}
