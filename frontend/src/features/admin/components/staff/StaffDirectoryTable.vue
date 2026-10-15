<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton } from '@/shared/components'
import SelectDropdown, { type DropdownOption } from '@/shared/components/forms/SelectDropdown.vue'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'
import type { StaffMember, VenueOption } from '../../composables/useAdminStaff'

withDefaults(
  defineProps<{
    staff: StaffMember[]
    venues?: VenueOption[]
    searchQuery: string
    selectedRoleFilter: string
    selectedStatusFilter?: string
    selectedVenueFilter?: string | number
    loading?: boolean
  }>(),
  {
    venues: () => [],
    selectedStatusFilter: 'all',
    selectedVenueFilter: 'all',
    loading: false,
  },
)

const emit = defineEmits<{
  (e: 'update:searchQuery', val: string): void
  (e: 'update:selectedRoleFilter', val: string): void
  (e: 'update:selectedStatusFilter', val: string): void
  (e: 'update:selectedVenueFilter', val: string | number): void
  (e: 'openInviteModal'): void
  (e: 'editMember', member: StaffMember): void
  (e: 'toggleStatus', member: StaffMember): void
  (e: 'removeMember', member: StaffMember): void
}>()

const { t } = useI18n()

const roleFilterOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.staff.allRoles'), icon: 'filter_list' },
  { value: 'organization_owner', label: t('admin.staff.roleOwner'), icon: 'shield_person' },
  { value: 'staff', label: t('admin.staff.roleStaff'), icon: 'badge' },
  { value: 'customer', label: t('admin.staff.roleCustomer'), icon: 'person' },
])

const statusFilterOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.staff.allStatuses'), icon: 'tune' },
  { value: 'active', label: t('admin.staff.statusActive'), icon: 'check_circle' },
  { value: 'suspended', label: t('admin.staff.statusSuspended'), icon: 'block' },
  { value: 'invited', label: t('admin.staff.statusInvited'), icon: 'mail' },
])

const roleLabelKeys: Record<string, string> = {
  organization_owner: 'admin.staff.roleOwner',
  staff: 'admin.staff.roleStaff',
  customer: 'admin.staff.roleCustomer',
}
</script>

<template>
  <div class="staff-directory-container">
    <!-- Toolbar -->
    <div class="directory-toolbar">
      <!-- Search Input -->
      <div class="search-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="header-search-input"
          :placeholder="t('admin.staff.searchStaffPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="clear-search-btn"
          @click="emit('update:searchQuery', '')"
        >
          ✕
        </button>
      </div>

      <!-- Filters & Invite Action -->
      <div class="filter-actions-group">
        <div class="dropdown-filter-wrap">
          <SelectDropdown
            :model-value="selectedRoleFilter"
            :options="roleFilterOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedRoleFilter', String($event))"
          />
        </div>

        <div class="dropdown-filter-wrap">
          <SelectDropdown
            :model-value="selectedStatusFilter"
            :options="statusFilterOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedStatusFilter', String($event))"
          />
        </div>

        <AuraButton
          type="button"
          variant="primary"
          class="btn-primary-cta"
          data-testid="invite-staff-btn"
          @click="emit('openInviteModal')"
        >
          <span class="material-symbols-outlined icon-18">person_add</span>
          <span>{{ t('admin.staff.inviteStaffBtn') }}</span>
        </AuraButton>
      </div>
    </div>

    <!-- Results count badge -->
    <div v-if="!loading" class="results-info-bar">
      <span class="results-count-text">
        {{ t('admin.staff.resultsCount', { count: staff.length }) }}
      </span>
    </div>

    <!-- Skeletons Loading State -->
    <div v-if="loading" class="staff-table-wrap">
      <div class="table-header-row">
        <span>{{ t('admin.staff.hdrMember') }}</span>
        <span>{{ t('admin.staff.hdrRole') }}</span>
        <span>{{ t('admin.staff.hdrVenue') }}</span>
        <span>{{ t('admin.staff.hdrPermissions') }}</span>
        <span>{{ t('admin.staff.hdrStatus') }}</span>
        <span class="text-right">{{ t('admin.staff.hdrActions') }}</span>
      </div>
      <div class="table-body-rows">
        <div v-for="i in 5" :key="i" class="table-grid-row skeleton-grid-row">
          <SkeletonLoader type="rect" height="42px" />
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="staff.length === 0" class="empty-staff-state">
      <span class="material-symbols-outlined empty-icon">group_off</span>
      <h3 class="empty-title">{{ t('admin.staff.noStaffFound') }}</h3>
      <p class="empty-sub">{{ t('admin.staff.noStaffFoundSub') }}</p>
      <AuraButton variant="primary" class="mt-4" @click="emit('openInviteModal')">
        <span class="material-symbols-outlined icon-18">person_add</span>
        <span>{{ t('admin.staff.inviteStaffBtn') }}</span>
      </AuraButton>
    </div>

    <!-- Staff Table Grid -->
    <div v-else class="staff-table-wrap">
      <div class="table-header-row">
        <span>{{ t('admin.staff.hdrMember') }}</span>
        <span>{{ t('admin.staff.hdrRole') }}</span>
        <span>{{ t('admin.staff.hdrVenue') }}</span>
        <span>{{ t('admin.staff.hdrPermissions') }}</span>
        <span>{{ t('admin.staff.hdrStatus') }}</span>
        <span class="text-right">{{ t('admin.staff.hdrActions') }}</span>
      </div>

      <div class="table-body-rows">
        <div v-for="member in staff" :key="member.id" class="table-grid-row">
          <!-- Member Avatar + Name & Email -->
          <div class="member-col">
            <div class="avatar-box">
              <img v-if="member.avatar" :src="member.avatar" :alt="member.name" />
              <span v-else class="initials">{{ member.name.charAt(0).toUpperCase() }}</span>
            </div>
            <div class="member-info">
              <strong class="member-name">{{ member.name }}</strong>
              <span class="member-email font-mono">{{ member.email }}</span>
              <span v-if="member.phone && member.phone !== '—'" class="member-phone font-mono">
                {{ member.phone }}
              </span>
            </div>
          </div>

          <!-- Role Badge -->
          <div class="role-col">
            <span class="role-badge" :class="`role-${member.rawRole}`">
              {{
                roleLabelKeys[member.rawRole] ? t(roleLabelKeys[member.rawRole]!) : member.rawRole
              }}
            </span>
          </div>

          <!-- Venue Column -->
          <div class="venue-col">
            <span class="venue-pill">
              <span class="material-symbols-outlined icon-14">location_on</span>
              <span>{{ resolveLocalizedText(member.venueName) }}</span>
            </span>
          </div>

          <!-- Permissions Count -->
          <div class="perms-col">
            <span class="perms-badge">
              <span class="material-symbols-outlined icon-14">verified</span>
              <span>{{
                t('admin.staff.permissionsCount', { count: member.permissionsCount })
              }}</span>
            </span>
          </div>

          <!-- Status Indicator -->
          <div class="status-col">
            <span
              class="status-indicator"
              :class="
                member.status === 'active'
                  ? 'active'
                  : member.status === 'suspended'
                    ? 'suspended'
                    : 'invited'
              "
            >
              ●
              {{
                member.status === 'active'
                  ? t('admin.staff.statusActive')
                  : member.status === 'suspended'
                    ? t('admin.staff.statusSuspended')
                    : t('admin.staff.statusInvited')
              }}
            </span>
          </div>

          <!-- Action Buttons -->
          <!-- Only staff rows are managed here; owner and customer rows have no actions. -->
          <div class="actions-col text-right">
            <template v-if="member.rawRole === 'staff'">
              <AuraButton
                size="sm"
                variant="secondary"
                data-testid="edit-staff-btn"
                :title="t('admin.staff.editStaff')"
                @click="emit('editMember', member)"
              >
                <span class="material-symbols-outlined icon-16">edit</span>
              </AuraButton>

              <AuraButton
                size="sm"
                variant="secondary"
                data-testid="toggle-status-btn"
                :title="
                  member.status === 'active'
                    ? t('admin.staff.lockStaff')
                    : t('admin.staff.unlockStaff')
                "
                @click="emit('toggleStatus', member)"
              >
                <span class="material-symbols-outlined icon-16">
                  {{ member.status === 'active' ? 'lock' : 'lock_open' }}
                </span>
              </AuraButton>

              <AuraButton
                size="sm"
                variant="danger"
                data-testid="remove-staff-btn"
                :title="t('admin.staff.removeStaff')"
                @click="emit('removeMember', member)"
              >
                <span class="material-symbols-outlined icon-16">person_remove</span>
              </AuraButton>
            </template>
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
  position: relative;
  flex: 1;
  min-width: 260px;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 14px;
  font-size: 18px;
  color: var(--muted);
  pointer-events: none;
}

.header-search-input {
  width: 100%;
  padding: 10px 38px 10px 38px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  outline: none;
  box-sizing: border-box;
  transition:
    border-color 0.15s,
    box-shadow 0.15s;
}

.header-search-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

.clear-search-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  font-size: 12px;
}

.filter-actions-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.dropdown-filter-wrap {
  width: 200px;
}

.results-info-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 4px;
}

.results-count-text {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--muted);
}

.staff-table-wrap {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.table-header-row {
  display: grid;
  grid-template-columns: 2.2fr 1.3fr 1.6fr 1.1fr 1fr 120px;
  padding: 14px 20px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.025));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.table-body-rows {
  display: flex;
  flex-direction: column;
}

.table-grid-row {
  display: grid;
  grid-template-columns: 2.2fr 1.3fr 1.6fr 1.1fr 1fr 120px;
  padding: 14px 20px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13.5px;
  transition: background 0.12s;
}

.table-grid-row:last-child {
  border-bottom: 0;
}

.table-grid-row:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.015));
}

.skeleton-grid-row {
  padding: 12px 20px;
}

.member-col {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar-box {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  overflow: hidden;
  background: rgba(236, 72, 153, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 16px;
  color: var(--pink-dark, #db2777);
  flex-shrink: 0;
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
  min-width: 0;
}

.member-name {
  color: var(--ink);
  font-size: 14px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.member-email {
  font-size: 12px;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.member-phone {
  font-size: 11px;
  color: var(--muted);
}

.role-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 11.5px;
  font-weight: 700;
}

.role-organization_owner {
  background: rgba(139, 92, 246, 0.15);
  color: #7c3aed;
}

.role-staff {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
}

.role-customer {
  background: rgba(107, 114, 128, 0.12);
  color: #4b5563;
}

.venue-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--ink);
  font-weight: 500;
}

.perms-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  padding: 3px 8px;
  border-radius: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  font-weight: 600;
  color: var(--ink);
}

.status-indicator {
  font-size: 12.5px;
  font-weight: 700;
}

.status-indicator.active {
  color: #10b981;
}
.status-indicator.suspended {
  color: #ef4444;
}
.status-indicator.invited {
  color: #f59e0b;
}

.actions-col {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}

.text-right {
  text-align: right;
}

.icon-14 {
  font-size: 14px;
}
.icon-16 {
  font-size: 16px;
}
.icon-18 {
  font-size: 18px;
}

.empty-staff-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  border-radius: 18px;
  border: 1.5px dashed var(--outline);
  background: var(--surface);
  text-align: center;
}

.empty-icon {
  font-size: 48px;
  color: var(--muted);
  margin-bottom: 10px;
}

.empty-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.empty-sub {
  margin: 4px 0 0 0;
  font-size: 13px;
  color: var(--muted);
}

.mt-4 {
  margin-top: 16px;
}

:global(html.dark) .staff-table-wrap,
:global(html.dark) .empty-staff-state {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .header-search-input {
  background: var(--surface-subtle, #262024);
  color: var(--ink, #faf7f3);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .table-header-row {
  background: var(--surface-subtle, #262024);
  color: var(--muted, #a89fa4);
  border-bottom-color: var(--outline, #4d4049);
}

:global(html.dark) .table-grid-row {
  border-bottom-color: var(--outline, #4d4049);
}

:global(html.dark) .table-grid-row:hover {
  background: var(--soft, #30292e);
}

:global(html.dark) .member-name {
  color: var(--ink, #faf7f3);
}

:global(html.dark) .perms-badge {
  background: var(--surface-subtle, #262024);
  color: var(--ink, #faf7f3);
}

:global(html.dark) .role-organization_owner {
  background: rgba(139, 92, 246, 0.25);
  color: #c4b5fd;
}

:global(html.dark) .role-staff {
  background: rgba(16, 185, 129, 0.25);
  color: #6ee7b7;
}

:global(html.dark) .role-customer {
  background: rgba(148, 163, 184, 0.25);
  color: #cbd5e1;
}

@media (max-width: 1024px) {
  .table-header-row,
  .table-grid-row {
    grid-template-columns: 2fr 1.2fr 1.2fr 1fr 100px;
  }
  .perms-col {
    display: none;
  }
}
</style>
