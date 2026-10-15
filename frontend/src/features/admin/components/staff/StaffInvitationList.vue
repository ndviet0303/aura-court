<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { StaffInvitation } from '../../composables/useAdminStaff'

withDefaults(defineProps<{ invitations: StaffInvitation[]; saving?: boolean }>(), {
  saving: false,
})

const emit = defineEmits<{ (e: 'revoke', invitationId: number): void }>()

const { t, te } = useI18n()

const label = (group: string, key: string) => (te(`${group}.${key}`) ? t(`${group}.${key}`) : key)

const formatExpiry = (value: string | null) =>
  value ? new Date(value).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' }) : '—'
</script>

<template>
  <section v-if="invitations.length > 0" class="invitation-list" data-testid="staff-invitations">
    <h3 class="invitation-title">{{ t('admin.staff.invitationsTitle') }}</h3>
    <table class="invitation-table">
      <thead>
        <tr>
          <th>{{ t('admin.staff.workEmailLabel') }}</th>
          <th>{{ t('admin.staff.assignedRoleLabel') }}</th>
          <th>{{ t('admin.staff.invitationStatusLabel') }}</th>
          <th>{{ t('invitations.expiresAt') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="inv in invitations" :key="inv.id">
          <td class="font-mono">{{ inv.email }}</td>
          <td>{{ label('invitations.roles', inv.role) }}</td>
          <td>{{ label('admin.staff.invitationStatus', inv.status) }}</td>
          <td>{{ formatExpiry(inv.expiresAt) }}</td>
          <td class="invitation-actions">
            <AuraButton
              v-if="inv.status === 'pending'"
              variant="secondary"
              :disabled="saving"
              data-testid="revoke-invitation"
              @click="emit('revoke', inv.id)"
            >
              {{ t('admin.staff.revokeInvitationBtn') }}
            </AuraButton>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<style scoped>
.invitation-list {
  margin-top: 24px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
  overflow-x: auto;
}

.invitation-title {
  margin: 0 0 12px;
  color: var(--ink);
}

.invitation-table {
  width: 100%;
  border-collapse: collapse;
  color: var(--ink);
}

.invitation-table th {
  text-align: left;
  color: var(--muted);
  font-weight: 600;
  padding: 8px;
}

.invitation-table td {
  padding: 8px;
  border-top: 1px solid var(--outline);
}

.invitation-actions {
  text-align: right;
}
</style>
