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
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'invite', email: string, role: string): void
  (e: 'updateRole', memberId: number, payload: { role: string; status?: string }): void
  (e: 'remove', memberId: number): void
}>()

const { t } = useI18n()

const emailInput = ref('')
const roleInput = ref('staff')
const searchQuery = ref('')
const roleFilter = ref('all')

// Member removal modal state
const memberToRemove = ref<OrgMember | null>(null)
const showRemoveModal = ref(false)

// Member edit role modal state
const memberToEdit = ref<OrgMember | null>(null)
const editRoleValue = ref('staff')
const showEditModal = ref(false)

const roleOptions: DropdownOption[] = [
  { value: 'organization_owner', label: 'Chủ sở hữu (Owner)', icon: 'shield_person' },
  { value: 'venue_manager', label: 'Quản lý cơ sở (Manager)', icon: 'manage_accounts' },
  { value: 'cashier', label: 'Thu ngân (Cashier)', icon: 'point_of_sale' },
  { value: 'staff', label: 'Nhân viên sân (Staff)', icon: 'badge' },
]

const filterRoleOptions: DropdownOption[] = [
  { value: 'all', label: 'Tất cả vai trò', icon: 'filter_list' },
  { value: 'organization_owner', label: 'Chủ sở hữu (Owner)', icon: 'shield_person' },
  { value: 'venue_manager', label: 'Quản lý cơ sở (Manager)', icon: 'manage_accounts' },
  { value: 'cashier', label: 'Thu ngân (Cashier)', icon: 'point_of_sale' },
  { value: 'staff', label: 'Nhân viên sân (Staff)', icon: 'badge' },
]

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

function handleInvite() {
  if (!emailInput.value.trim()) return
  emit('invite', emailInput.value.trim(), roleInput.value)
  emailInput.value = ''
}

function openEditRoleModal(m: OrgMember) {
  memberToEdit.value = m
  editRoleValue.value = m.role
  showEditModal.value = true
}

function handleConfirmEditRole() {
  if (!memberToEdit.value) return
  emit('updateRole', memberToEdit.value.id, { role: editRoleValue.value })
  showEditModal.value = false
  memberToEdit.value = null
}

function promptRemoveMember(m: OrgMember) {
  memberToRemove.value = m
  showRemoveModal.value = true
}

function handleConfirmRemove() {
  if (!memberToRemove.value) return
  emit('remove', memberToRemove.value.id)
  showRemoveModal.value = false
  memberToRemove.value = null
}

function getRoleLabel(role: string) {
  switch (role) {
    case 'organization_owner':
      return t('admin.org.roleOwner')
    case 'venue_manager':
      return t('admin.org.roleManager')
    case 'cashier':
      return t('admin.org.roleCashier')
    case 'staff':
      return t('admin.org.roleStaff')
    case 'customer':
      return t('admin.org.roleCustomer')
    default:
      return role
  }
}
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
    </div>

    <!-- Quick Invite Form -->
    <div class="invite-box">
      <div class="invite-inputs">
        <input
          v-model="emailInput"
          type="email"
          :placeholder="t('admin.org.inviteEmailPlaceholder')"
          class="form-input font-mono email-input"
          @keydown.enter.prevent="handleInvite"
        />
        <div class="role-select-wrap">
          <SelectDropdown
            v-model="roleInput"
            :options="roleOptions"
            variant="bordered"
          />
        </div>
      </div>

      <AuraButton
        type="button"
        variant="primary"
        :disabled="!emailInput.trim() || saving"
        @click="handleInvite"
      >
        <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': saving }">
          {{ saving ? 'sync' : 'person_add' }}
        </span>
        <span>{{ t('admin.org.sendInviteBtn') }}</span>
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
        <button
          v-if="searchQuery"
          type="button"
          class="clear-btn"
          @click="searchQuery = ''"
        >
          ✕
        </button>
      </div>

      <div class="filter-wrap">
        <SelectDropdown
          v-model="roleFilter"
          :options="filterRoleOptions"
          variant="bordered"
        />
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
            <th class="text-right">{{ t('admin.org.hdrActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in filteredMembers" :key="m.id" class="member-row">
            <!-- Member Info -->
            <td>
              <div class="member-profile-cell">
                <div class="member-avatar">
                  <img
                    v-if="m.user?.avatar_url"
                    :src="m.user.avatar_url"
                    :alt="m.user.name"
                  />
                  <span v-else class="avatar-initial">
                    {{ (m.user?.name || m.user?.email || 'M').charAt(0).toUpperCase() }}
                  </span>
                </div>
                <div class="member-names">
                  <strong class="user-name">{{ m.user?.name || t('admin.staff.hdrMember') }}</strong>
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

            <!-- Actions -->
            <td class="text-right">
              <div class="actions-cell">
                <AuraButton
                  size="sm"
                  variant="secondary"
                  :title="t('admin.org.editRoleBtn')"
                  @click="openEditRoleModal(m)"
                >
                  <span class="material-symbols-outlined icon-16">manage_accounts</span>
                </AuraButton>

                <AuraButton
                  size="sm"
                  variant="danger"
                  :disabled="m.role === 'organization_owner'"
                  :title="t('admin.org.removeMemberBtn')"
                  @click="promptRemoveMember(m)"
                >
                  <span class="material-symbols-outlined icon-16">delete</span>
                </AuraButton>
              </div>
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

    <!-- Edit Role Modal -->
    <div v-if="showEditModal" class="modal-backdrop" @click.self="showEditModal = false">
      <div class="modal-content">
        <h3 class="modal-title">{{ t('admin.org.editRoleBtn') }}</h3>
        <p class="modal-sub">
          {{ memberToEdit?.user?.name }} ({{ memberToEdit?.user?.email }})
        </p>

        <div class="modal-form">
          <label class="form-label">{{ t('admin.org.hdrRole') }}</label>
          <SelectDropdown
            v-model="editRoleValue"
            :options="roleOptions"
            variant="bordered"
          />
        </div>

        <div class="modal-actions">
          <AuraButton variant="secondary" @click="showEditModal = false">
            {{ t('admin.org.cancelBtn') }}
          </AuraButton>
          <AuraButton variant="primary" :disabled="saving" @click="handleConfirmEditRole">
            {{ t('admin.org.confirmBtn') }}
          </AuraButton>
        </div>
      </div>
    </div>

    <!-- Confirm Remove Modal -->
    <div v-if="showRemoveModal" class="modal-backdrop" @click.self="showRemoveModal = false">
      <div class="modal-content">
        <h3 class="modal-title">{{ t('admin.org.confirmRemoveMemberTitle') }}</h3>
        <p class="modal-sub">
          {{ t('admin.org.confirmRemoveMemberMsg', { email: memberToRemove?.user?.email || '' }) }}
        </p>

        <div class="modal-actions">
          <AuraButton variant="secondary" @click="showRemoveModal = false">
            {{ t('admin.org.cancelBtn') }}
          </AuraButton>
          <AuraButton variant="danger" :disabled="saving" @click="handleConfirmRemove">
            {{ t('admin.org.removeMemberBtn') }}
          </AuraButton>
        </div>
      </div>
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

.invite-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 16px 20px;
  border-radius: 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  flex-wrap: wrap;
}

.invite-inputs {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 280px;
  flex-wrap: wrap;
}

.email-input {
  flex: 1;
  min-width: 200px;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
}

.email-input:focus {
  outline: none;
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15);
}

.role-select-wrap {
  width: 220px;
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

.role-venue_manager {
  background: rgba(37, 99, 235, 0.12);
  color: #2563eb;
}

.role-cashier {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
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

.text-right {
  text-align: right;
}

.actions-cell {
  display: inline-flex;
  gap: 8px;
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

/* Modal styles */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-content {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 24px;
  max-width: 440px;
  width: 100%;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.modal-sub {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted);
  line-height: 1.5;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 8px;
}

.icon-16 { font-size: 16px; }
.icon-18 { font-size: 18px; }
</style>
