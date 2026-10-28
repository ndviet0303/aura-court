<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatDateTime } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { ListingContact } from '../types/transfer'

// UC-9.7: the seller sees who contacted about the listing and starts a transaction for one of them.
defineProps<{ contacts: ListingContact[]; canCreate: boolean }>()
const emit = defineEmits<{ create: [contact: ListingContact] }>()
const { t } = useI18n()
</script>

<template>
  <section class="contacts">
    <h2>{{ t('resale.detail.contacts') }}</h2>
    <p v-if="!contacts.length" class="muted">{{ t('resale.detail.noContacts') }}</p>
    <ul v-else class="contact-list">
      <li v-for="c in contacts" :key="c.conversation_id" class="contact">
        <img v-if="c.user.avatar_url" :src="c.user.avatar_url" alt="" class="avatar" />
        <span v-else class="avatar" aria-hidden="true">{{ c.user.name.charAt(0) }}</span>
        <div class="contact-info">
          <strong>{{ c.user.name }}</strong>
          <span class="muted">
            {{
              t('resale.detail.contactedAbout', {
                count: c.reservation_ids.length,
                time: formatDateTime(c.contacted_at),
              })
            }}
          </span>
        </div>
        <div class="contact-actions">
          <RouterLink :to="{ path: '/messages', query: { conversation: c.conversation_id } }">
            {{ t('resale.detail.openChat') }}
          </RouterLink>
          <AuraButton size="sm" :disabled="!canCreate" @click="emit('create', c)">{{
            t('resale.create.open')
          }}</AuraButton>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.contacts {
  display: grid;
  gap: 8px;
}
h2 {
  margin: 0;
  color: var(--ink);
  font-size: 16px;
}
.contact-list {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.contact {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 12px;
  align-items: center;
  padding: 10px 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}
.avatar {
  display: grid;
  flex: none;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--primary) 14%, transparent);
  color: var(--primary);
  font-weight: 700;
  object-fit: cover;
}
.contact-info {
  display: grid;
  flex: 1 1 160px;
  min-width: 0;
}
.contact-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}
.muted {
  margin: 0;
  color: var(--muted);
  font-size: 12px;
}
</style>
