<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { OrgMember } from '../../composables/useOrganizationOwner'

defineProps<{
  members: OrgMember[]
}>()

const emit = defineEmits<{
  (e: 'invite', email: string, role: string): void
}>()

const { t } = useI18n()

const emailInput = ref('')
const roleInput = ref('staff')

function handleInvite() {
  if (!emailInput.value.trim()) return
  emit('invite', emailInput.value.trim(), roleInput.value)
  emailInput.value = ''
}
</script>

<template>
  <div class="org-members-tab-card">
    <div class="card-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">group</span>
        <div>
          <h3 class="tab-title">{{ t('admin.org.membersTitle') }}</h3>
          <span class="sub">{{ t('admin.org.membersSub') }}</span>
        </div>
      </div>
    </div>

    <!-- Quick invite form -->
    <div class="invite-form-box">
      <input
        v-model="emailInput"
        type="email"
        :placeholder="t('admin.org.inviteEmailPlaceholder')"
        class="form-input font-mono"
      />
      <select v-model="roleInput" class="form-input role-select">
        <option value="venue_manager">{{ t('admin.org.roleManager') }}</option>
        <option value="cashier">{{ t('admin.org.roleCashier') }}</option>
        <option value="staff">{{ t('admin.org.roleStaff') }}</option>
      </select>
      <button type="button" class="btn-invite" :disabled="!emailInput.trim()" @click="handleInvite">
        <span class="material-symbols-outlined icon-16">person_add</span>
        <span>{{ t('admin.org.sendInviteBtn') }}</span>
      </button>
    </div>

    <div class="members-table">
      <div class="table-hdr">
        <span>{{ t('admin.org.hdrMember') }}</span>
        <span>{{ t('admin.org.hdrEmail') }}</span>
        <span>{{ t('admin.org.hdrRole') }}</span>
        <span>{{ t('admin.org.hdrStatus') }}</span>
      </div>

      <div class="table-body">
        <div v-for="m in members" :key="m.id" class="member-row">
          <strong>{{ m.user?.name || t('admin.staff.hdrMember') }}</strong>
          <span class="email-val font-mono">{{ m.user?.email }}</span>
          <span class="role-badge">{{ m.role }}</span>
          <span class="status-badge" :class="`status-${m.status}`">● {{ m.status }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.org-members-tab-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 24px;
  color: var(--pink-dark, #db2777);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.tab-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.invite-form-box {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.invite-form-box input {
  flex: 1;
  min-width: 220px;
}

.role-select {
  width: 200px;
}

.btn-invite {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 18px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.members-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-hdr {
  display: grid;
  grid-template-columns: 1.5fr 2fr 1.5fr 1fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.member-row {
  display: grid;
  grid-template-columns: 1.5fr 2fr 1.5fr 1fr;
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.member-row:last-child {
  border-bottom: 0;
}

.email-val {
  color: var(--muted);
  font-size: 12px;
}

.role-badge {
  font-size: 11px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.status-badge {
  font-size: 11px;
  font-weight: 700;
  color: #10b981;
}

.icon-16 {
  font-size: 16px;
}
</style>
