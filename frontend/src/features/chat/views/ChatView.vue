<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { useOwnerCheck } from '@/features/admin/composables/useLifecycleCommands'
import { AuraButton } from '@/shared/components'
import ChatThread from '../components/ChatThread.vue'
import ConversationList from '../components/ConversationList.vue'
import { useChat } from '../composables/useChat'
import { useStartChat } from '../composables/useStartChat'

// Module 07 (UC-7.13–UC-7.15): one page for customers, venue operators, owners and the platform admin.
const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const chat = useChat()
const { conversations, active, activeId, messages, sending, error } = chat
const isOwnerOf = useOwnerCheck()
const startChat = useStartChat()
const startError = ref('')

const canGift = computed(
  () =>
    active.value?.type === 'customer_venue' &&
    active.value.side === 'venue' &&
    isOwnerOf(active.value.venue?.organization_id),
)
// UC-7.15: an owner opens the support chat for an organization they own.
const ownedOrganizations = computed(() =>
  route.path.startsWith('/admin')
    ? (auth.user?.organizations ?? []).flatMap((o) => {
        const id = o.organization_id ?? o.id
        return o.status === 'active' && o.role === 'organization_owner' && id
          ? [{ id, name: o.name ?? '' }]
          : []
      })
    : [],
)

async function support(organizationId: number) {
  startError.value =
    (await startChat({ type: 'owner_admin', organization_id: organizationId })) ?? ''
}
</script>

<template>
  <div class="chat-page">
    <header class="chat-header">
      <h1 class="chat-title">{{ t('chat.title') }}</h1>
      <div class="chat-header-actions">
        <AuraButton
          v-for="org in ownedOrganizations"
          :key="org.id"
          size="sm"
          variant="outline"
          @click="support(org.id)"
        >
          {{ t('chat.support') }}{{ ownedOrganizations.length > 1 ? ` · ${org.name}` : '' }}
        </AuraButton>
      </div>
    </header>

    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <p v-if="startError" class="form-error" role="alert">{{ startError }}</p>

    <div class="chat-body">
      <aside class="chat-card">
        <ConversationList
          :conversations="conversations"
          :active-id="activeId"
          @select="chat.select"
        />
      </aside>
      <section class="chat-card">
        <ChatThread
          v-if="active"
          :key="active.id"
          :conversation="active"
          :messages="messages"
          :sending="sending"
          :can-gift="canGift"
          :gift-open="route.query.gift === '1'"
          @send="async (body, image, done) => done(await chat.send(body, image))"
          @gift="async (code, note, done) => done(await chat.gift(code, note))"
        />
        <p v-else class="muted">{{ t('chat.pick') }}</p>
      </section>
    </div>
  </div>
</template>

<style scoped>
.chat-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 1180px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.chat-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.chat-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.chat-header-actions {
  display: flex;
  gap: 8px;
}

.chat-body {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 16px;
}

@media (min-width: 900px) {
  .chat-body {
    grid-template-columns: 320px minmax(0, 1fr);
  }
}

.chat-card {
  padding: 12px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
