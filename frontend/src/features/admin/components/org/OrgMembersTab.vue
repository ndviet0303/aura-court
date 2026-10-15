<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import SelectDropdown, { type DropdownOption } from '@/shared/components/forms/SelectDropdown.vue'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'
import type { OrgMember } from '../../composables/useOrganizationOwner'

const props = defineProps<{
  members: OrgMember[]
  loading?: boolean
}>()

const { t } = useI18n()

const searchQuery = ref('')
const roleFilter = ref('all')

const roleLabelKeys: Record<string, string> = {
  organization_owner: 'admin.org.roleOwner',
  staff: 'admin.org.roleStaff',
  customer: 'admin.org.roleCustomer',
}

const filterRoleOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.org.filterAllRoles'), icon: 'filter_list' },
  { value: 'organization_owner', label: t('admin.org.roleOwner'), icon: 'shield_person' },
  { value: 'staff', label: t('admin.org.roleStaff'), icon: 'badge' },
  { value: 'customer', label: t('admin.org.roleCustomer'), icon: 'person' },
])

const filteredMembers = computed(() => {
  return props.members.filter((m) => {
    // Search query
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const name = (m.user?.name || '').toLowerCase()
      const email = (m.user?.email || '').toLowerCase()
      const role = (m.role || '').toLowerCase()
      if (!name.includes(q) && !email.includes(q) && !role.includes(q)) return false
    }

    // Role filter
    if (roleFilter.value !== 'all' && m.role !== roleFilter.value) {
      return false
    }

    return true
  })
})

const getRoleLabel = (role: string) => (roleLabelKeys[role] ? t(roleLabelKeys[role]) : role)
</script>

<template>
  <div class="org-members-tab-card">
    <!-- Header -->
    <div class="card-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">group</span>
        <div>
          <h3 class="tab-title">{{ t('admin.org.membersTitle') }}</h3>
          <p class="tab-sub">{{ t('admin.org.membersSubtitle') }}</p>
        </div>
      </div>
      <!-- Staff invitations, venue access and status are managed on the staff page. -->
      <AuraButton variant="primary" to="/admin/staff" data-testid="org-manage-staff-link">
        <span class="material-symbols-outlined icon-18">badge</span>
        <span>{{ t('admin.org.manageStaffBtn') }}</span>
      </AuraButton>
    </div>

    <!-- Search & Filter Toolbar -->
    <div class="members-toolbar">
      <div class="search-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          v-model="searchQuery"
          type="text"
          class="members-search-input"
          :placeholder="t('admin.org.searchMembersPlaceholder')"
        />
        <button v-if="searchQuery" type="button" class="clear-btn" @click="searchQuery = ''">
          ✕
        </button>
      </div>

      <div class="filter-wrap">
        <SelectDropdown v-model="roleFilter" :options="filterRoleOptions" variant="bordered" />
      </div>
    </div>

    <!-- Skeleton Loading Table -->
    <div v-if="loading" class="members-table">
      <div v-for="i in 4" :key="i" class="member-row skeleton-row">
        <SkeletonLoader type="rect" height="56px" />
      </div>
    </div>

    <!-- Members Table -->
    <div v-else-if="filteredMembers.length > 0" class="members-table-container">
      <table class="members-table">
        <thead>
          <tr>
            <th>{{ t('admin.org.hdrMember') }}</th>
            <th>{{ t('admin.org.hdrEmail') }}</th>
            <th>{{ t('admin.org.hdrRole') }}</th>
            <th>{{ t('admin.org.hdrStatus') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in filteredMembers" :key="m.id" class="member-row">
            <!-- Member Info -->
            <td>
              <div class="member-profile-cell">
                <div class="member-avatar">
                  <img v-if="m.user?.avatar_url" :src="m.user.avatar_url" :alt="m.user.name" />
                  <span v-else class="avatar-initial">
                    {{ (m.user?.name || m.user?.email || 'M').charAt(0).toUpperCase() }}
                  </span>
                </div>
                <div class="member-names">
                  <strong class="user-name">{{
                    m.user?.name || t('admin.staff.hdrMember')
                  }}</strong>
                  <span class="user-id font-mono">UID: #{{ m.user_id }}</span>
                </div>
              </div>
            </td>

            <!-- Email & Phone -->
            <td>
              <div class="contact-cell font-mono">
                <span class="email-text">{{ m.user?.email || '—' }}</span>
                <span v-if="m.user?.phone" class="phone-text">{{ m.user.phone }}</span>
              </div>
            </td>

            <!-- Role Badge -->
            <td>
              <span class="role-badge" :class="`role-${m.role}`">
                {{ getRoleLabel(m.role) }}
              </span>
            </td>

            <!-- Status Badge -->
            <td>
              <span class="status-badge" :class="`status-${m.status || 'active'}`">
                ● {{ m.status || 'active' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-members-state">
      <span class="material-symbols-outlined empty-icon">group_off</span>
      <h4 class="empty-title">{{ t('admin.org.noMembersFound') }}</h4>
    </div>
  </div>
</template>

<style scoped>
.org-members-tab-card {
  display: flex;
  flex-direction: column;
  gap: 22px;
  padding: 28px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-title-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.tab-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.tab-sub {
  margin: 2px 0 0 0;
  font-size: 12.5px;
  color: var(--muted);
}

.members-toolbar {
  display: flex;
  gap: 14px;
  align-items: center;
  flex-wrap: wrap;
}

.search-wrap {
  position: relative;
  flex: 1;
  min-width: 240px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 18px;
  pointer-events: none;
}

.members-search-input {
  width: 100%;
  padding: 10px 36px 10px 36px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  box-sizing: border-box;
}

.members-search-input:focus {
  outline: none;
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15);
}

.clear-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  font-size: 12px;
}

.filter-wrap {
  width: 220px;
}

.members-table-container {
  border-radius: 14px;
  border: 1px solid var(--outline);
  overflow: hidden;
  background: var(--surface);
}

.members-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.members-table th {
  padding: 14px 18px;
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.members-table td {
  padding: 14px 18px;
  border-bottom: 1px solid var(--outline);
  vertical-align: middle;
}

.members-table tr:last-child td {
  border-bottom: 0;
}

.member-row:hover td {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.015));
}

.member-profile-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.member-avatar {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.12);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  font-weight: 800;
  font-size: 15px;
  flex-shrink: 0;
}

.member-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.member-names {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.user-name {
  font-size: 14px;
  color: var(--ink);
}

.user-id {
  font-size: 11px;
  color: var(--muted);
}

.contact-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12.5px;
}

.email-text {
  color: var(--ink);
}

.phone-text {
  color: var(--muted);
  font-size: 11.5px;
}

.role-badge {
  display: inline-block;
  font-size: 11.5px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 8px;
}

.role-organization_owner {
  background: rgba(107, 45, 63, 0.15);
  color: #8b3a52;
}

.role-staff {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
}

.role-customer {
  background: rgba(107, 114, 128, 0.12);
  color: #4b5563;
}

.status-badge {
  font-size: 11.5px;
  font-weight: 700;
}

.status-active {
  color: #10b981;
}

.status-suspended {
  color: #ef4444;
}

.empty-members-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px;
  border: 1.5px dashed var(--outline);
  border-radius: 16px;
}

.empty-icon {
  font-size: 40px;
  color: var(--muted);
  margin-bottom: 8px;
}

.empty-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
}

:global(html.dark) .org-members-tab-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .members-search-input {
  background: var(--surface-subtle, #262024);
  color: var(--ink, #faf7f3);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .members-table-container {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .members-table th {
  background: var(--surface-subtle, #262024);
  color: var(--muted, #a89fa4);
  border-bottom-color: var(--outline, #4d4049);
}

:global(html.dark) .members-table td {
  border-bottom-color: var(--outline, #4d4049);
}

:global(html.dark) .member-row:hover td {
  background: var(--soft, #30292e);
}

:global(html.dark) .user-name,
:global(html.dark) .email-text {
  color: var(--ink, #faf7f3);
}

:global(html.dark) .user-id,
:global(html.dark) .phone-text {
  color: var(--muted, #a89fa4);
}

:global(html.dark) .role-organization_owner {
  background: rgba(229, 142, 159, 0.2);
  color: #f8b4c4;
}

:global(html.dark) .role-staff {
  background: rgba(16, 185, 129, 0.2);
  color: #6ee7b7;
}

:global(html.dark) .role-customer {
  background: rgba(148, 163, 184, 0.2);
  color: #cbd5e1;
}

.icon-18 {
  font-size: 18px;
}
</style>
