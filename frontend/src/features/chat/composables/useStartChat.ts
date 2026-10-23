import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage } from '@/core/utils/errorMessage'
import { chatApi } from '../api/chatApi'
import type { OpenConversationPayload } from '../types/chat'

/** Opens (or finds) a conversation and goes to it, in the admin layout when called from there. */
export function useStartChat() {
  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()

  return async function start(
    payload: OpenConversationPayload,
    gift = false,
  ): Promise<string | null> {
    try {
      const { conversation } = await chatApi.open(payload)
      const path = route.path.startsWith('/admin') ? '/admin/messages' : '/messages'
      await router.push({
        path,
        query: { conversation: conversation.id, gift: gift ? '1' : undefined },
      })
      return null
    } catch (e) {
      return errorMessage(e, t('chat.openError'))
    }
  }
}
