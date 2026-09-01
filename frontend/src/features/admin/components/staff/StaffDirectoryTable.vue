<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { StaffMember } from '../../composables/useAdminStaff'

defineProps<{
  staff: StaffMember[]
  searchQuery: string
  selectedRoleFilter: string
}>()

const emit = defineEmits<{
  (e: 'update:searchQuery', val: string): void
  (e: 'update:selectedRoleFilter', val: string): void
  (e: 'openInviteModal'): void
}>()

const { t } = useI18n()

function getRoleLabel(role: string): string {
  switch (role) {
    case 'Owner': return t('admin.staff.roleOwner')
    case 'Manager': return t('admin.staff.roleManager')
    case 'Receptionist': return t('admin.staff.roleReceptionist')
    case 'Technician': return t('admin.staff.roleTechnician')
    case 'Referee': return t('admin.staff.roleReferee')
    default: return role
  }
}
</script>

<template>
  <div class="staff-directory-container">
    <!-- Toolbar -->
    <div class="directory-toolbar">
      <div class="search-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="header-search-input"
          :placeholder="t('admin.staff.searchStaffPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="filter-actions-group">
        <select
          :value="selectedRoleFilter"
          class="role-filter-select"
          @change="emit('update:selectedRoleFilter', ($event.target as HTMLSelectElement).value)"
        >
          <option value="all">{{ t('admin.staff.allRoles') }}</option>
          <option value="Owner">{{ t('admin.staff.roleOwner') }}</option>
          <option value="Manager">{{ t('admin.staff.roleManager') }}</option>
          <option value="Receptionist">{{ t('admin.staff.roleReceptionist') }}</option>
          <option value="Technician">{{ t('admin.staff.roleTechnician') }}</option>
          <option value="Referee">{{ t('admin.staff.roleReferee') }}</option>
        </select>

        <button type="button" class="btn-invite-staff btn-primary-cta" @click="emit('openInviteModal')">
          <span class="material-symbols-outlined icon-16">person_add</span>
          <span>{{ t('admin.staff.inviteStaffBtn') }}</span>
        </button>
      </div>
    </div>

    <!-- Staff Table Grid -->
    <div class="staff-table-wrap">
      <div class="table-header-row">
        <span>{{ t('admin.staff.hdrMember') }}</span>
        <span>{{ t('admin.staff.hdrRole') }}</span>
        <span>{{ t('admin.staff.hdrVenue') }}</span>
        <span>{{ t('admin.staff.hdrPermissions') }}</span>
        <span>{{ t('admin.staff.hdrStatus') }}</span>
        <span>{{ t('admin.staff.hdrActions') }}</span>
      </div>

      <div class="table-body-rows">
        <div
          v-for="member in staff"
          :key="member.id"
          class="table-grid-row"
        >
          <div class="member-col">
            <div class="avatar-box">
              <img v-if="member.avatar" :src="member.avatar" :alt="member.name" />
              <span v-else class="initials">{{ member.name.charAt(0) }}</span>
            </div>
            <div class="member-info">
              <strong class="member-name">{{ member.name }}</strong>
              <span class="member-email">{{ member.email }}</span>
            </div>
          </div>

          <div class="role-col">
            <span class="role-badge" :class="`role-${member.role.toLowerCase()}`">
              {{ getRoleLabel(member.role) }}
            </span>
          </div>

          <div class="venue-col">
            <span>{{ resolveLocalizedText(member.venueName) }}</span>
          </div>

          <div class="perms-col">
            <span class="perms-badge">{{ t('admin.staff.permissionsCount', { count: member.permissionsCount }) }}</span>
          </div>

          <div class="status-col">
            <span
              class="status-indicator"
              :class="member.status === 'active' ? 'active' : 'invited'"
            >
              ● {{ member.status === 'active' ? t('admin.staff.statusActive') : t('admin.staff.statusInvited') }}
            </span>
          </div>

          <div class="actions-col">
            <button type="button" class="btn-action-sm" :title="t('admin.staff.editStaff')">
              <span class="material-symbols-outlined icon-16">edit</span>
            </button>
            <button type="button" class="btn-action-sm" :title="t('admin.staff.lockStaff')">
              <span class="material-symbols-outlined icon-16">lock</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.staff-directory-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.directory-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-wrap {
  flex: 1;
  min-width: 280px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.search-icon {
  font-size: 18px;
  color: var(--muted);
}

.header-search-input {
  width: 100%;
  border: none;
  background: transparent;
  color: var(--ink);
  font-size: 13px;
  outline: none;
}

.filter-actions-group {
  display: flex;
  gap: 10px;
}

.role-filter-select {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.btn-invite-staff {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
}

.staff-table-wrap {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-header-row {
  display: grid;
  grid-template-columns: 2fr 1.2fr 1.5fr 1fr 1fr 90px;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.table-body-rows {
  display: flex;
  flex-direction: column;
}

.table-grid-row {
  display: grid;
  grid-template-columns: 2fr 1.2fr 1.5fr 1fr 1fr 90px;
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
  transition: background 0.1s;
}

.table-grid-row:last-child {
  border-bottom: 0;
}

.table-grid-row:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.01));
}

.member-col {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar-box {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  background: rgba(236, 72, 153, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.avatar-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.member-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.member-name {
  color: var(--ink);
}

.member-email {
  font-size: 11px;
  color: var(--muted);
}

.role-badge {
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.role-owner { background: #8b5cf6; color: #fff; }
.role-manager { background: #3b82f6; color: #fff; }
.role-receptionist { background: #10b981; color: #fff; }
.role-technician { background: #f59e0b; color: #fff; }
.role-referee { background: #ec4899; color: #fff; }

.perms-badge {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
}

.status-indicator {
  font-size: 12px;
  font-weight: 600;
}

.status-indicator.active { color: #10b981; }
.status-indicator.invited { color: #f59e0b; }

.actions-col {
  display: flex;
  gap: 6px;
}

.btn-action-sm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  cursor: pointer;
  transition: all 0.15s;
}

.btn-action-sm:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.icon-16 {
  font-size: 16px;
}
</style>
