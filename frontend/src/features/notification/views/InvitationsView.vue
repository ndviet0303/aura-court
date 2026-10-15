<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { errorMessage } from '@/core/utils/errorMessage'
import { notificationApi } from '../api/notificationApi'
import type { StaffInvitation } from '../types/notification'

const { t, te } = useI18n()

const invitations = ref<StaffInvitation[]>([])
const loading = ref(false)
const busyId = ref<number | null>(null)
const notice = ref('')
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    invitations.value = (await notificationApi.myInvitations()).invitations ?? []
  } catch (e: unknown) {
    error.value = errorMessage(e, t('invitations.loadError'))
  } finally {
    loading.value = false
  }
}

async function respond(invitation: StaffInvitation, answer: 'accept' | 'decline') {
  busyId.value = invitation.id
  notice.value = ''
  error.value = ''
  try {
    const res =
      answer === 'accept'
        ? await notificationApi.acceptInvitation(invitation.id)
        : await notificationApi.declineInvitation(invitation.id)
    notice.value = res?.message ?? ''
    invitations.value = invitations.value.filter((i) => i.id !== invitation.id)
  } catch (e: unknown) {
    error.value = errorMessage(e, t('invitations.respondError'))
  } finally {
    busyId.value = null
  }
}

function roleLabel(role: string): string {
  return te(`invitations.roles.${role}`) ? t(`invitations.roles.${role}`) : role
}

function venueName(venue: StaffInvitation['venue']): string {
  if (!venue) return t('invitations.noVenue')
  return typeof venue.name === 'string' ? venue.name : venue.name.vi || venue.name.en || ''
}

function formatExpiry(value: string | null): string {
  return value ? new Date(value).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' }) : '—'
}

onMounted(load)
</script>

<template>
  <div class="invitations-page">
    <header class="invitations-header">
      <h1>{{ t('invitations.title') }}</h1>
      <p>{{ t('invitations.subtitle') }}</p>
    </header>

    <div v-if="notice" class="inv-alert inv-alert-ok" role="status">{{ notice }}</div>
    <div v-if="error" class="inv-alert inv-alert-error" role="alert">{{ error }}</div>

    <p v-if="loading" class="inv-muted">{{ t('invitations.loading') }}</p>
    <p v-else-if="invitations.length === 0" class="inv-muted" data-testid="invitations-empty">
      {{ t('invitations.empty') }}
    </p>

    <ul v-else class="inv-list">
      <li v-for="inv in invitations" :key="inv.id" class="inv-card" data-testid="invitation-card">
        <div class="inv-body">
          <strong>{{ inv.organization?.name }}</strong>
          <span>{{ t('invitations.role') }}: {{ roleLabel(inv.role) }}</span>
          <span>{{ t('invitations.venue') }}: {{ venueName(inv.venue) }}</span>
          <span>{{ t('invitations.invitedBy') }}: {{ inv.inviter?.name ?? '—' }}</span>
          <span class="inv-muted">
            {{ t('invitations.expiresAt') }}: {{ formatExpiry(inv.expires_at) }}
          </span>
        </div>
        <div class="inv-actions">
          <AuraButton
            variant="secondary"
            :disabled="busyId !== null"
            data-testid="decline-invitation"
            @click="respond(inv, 'decline')"
          >
            {{ t('invitations.decline') }}
          </AuraButton>
          <AuraButton
            variant="primary"
            :disabled="busyId !== null"
            data-testid="accept-invitation"
            @click="respond(inv, 'accept')"
          >
            {{ t('invitations.accept') }}
          </AuraButton>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.invitations-page {
  max-width: 760px;
  margin: 0 auto;
  padding: 32px 16px;
}

.invitations-header h1 {
  margin: 0 0 4px;
  color: var(--ink);
}

.invitations-header p,
.inv-muted {
  color: var(--muted);
}

.inv-list {
  list-style: none;
  padding: 0;
  margin: 24px 0 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.inv-card {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 16px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.inv-body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  color: var(--ink);
}

.inv-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.inv-alert {
  margin-top: 16px;
  padding: 10px 14px;
  border-radius: 12px;
}

.inv-alert-ok {
  background: rgba(34, 197, 94, 0.12);
  color: var(--ink);
}

.inv-alert-error {
  background: rgba(239, 68, 68, 0.12);
  color: var(--ink);
}
</style>
