<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import SelectDropdown, { type DropdownOption } from '@/shared/components/forms/SelectDropdown.vue'
import type { StaffMember } from '../../composables/useAdminStaff'

const props = withDefaults(
  defineProps<{
    show: boolean
    member: StaffMember | null
    saving?: boolean
  }>(),
  {
    saving: false,
  },
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', memberId: number, payload: { role: string; status: string }): void
}>()

const { t } = useI18n()

const selectedRole = ref<string>('Receptionist')
const selectedStatus = ref<string>('active')

const roleOptions = computed<DropdownOption[]>(() => [
  { value: 'Owner', label: t('admin.staff.roleOwner'), icon: 'shield_person' },
  { value: 'Manager', label: t('admin.staff.roleManager'), icon: 'manage_accounts' },
  { value: 'Receptionist', label: t('admin.staff.roleReceptionist'), icon: 'point_of_sale' },
  { value: 'Technician', label: t('admin.staff.roleTechnician'), icon: 'build' },
  { value: 'Referee', label: t('admin.staff.roleReferee'), icon: 'sports' },
  { value: 'Staff', label: t('admin.staff.roleStaff'), icon: 'badge' },
])

const statusOptions = computed<DropdownOption[]>(() => [
  { value: 'active', label: t('admin.staff.statusActive'), icon: 'check_circle' },
  { value: 'suspended', label: t('admin.staff.statusSuspended'), icon: 'block' },
])

watch(
  () => props.member,
  (val) => {
    if (val) {
      selectedRole.value = val.role || 'Receptionist'
      selectedStatus.value = val.status || 'active'
    }
  },
  { immediate: true },
)

function handleSave() {
  if (!props.member) return
  emit('save', props.member.id, {
    role: selectedRole.value,
    status: selectedStatus.value,
  })
}
</script>

<template>
  <div v-if="show && member" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">manage_accounts</span>
          <div>
            <h3 class="modal-title">{{ t('admin.staff.editModalTitle') }}</h3>
            <p class="modal-sub">{{ t('admin.staff.editModalSub') }}</p>
          </div>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="member-target-card">
          <strong class="target-name">{{ member.name }}</strong>
          <span class="target-email font-mono">{{ member.email }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.staff.assignedRoleLabel') }}</label>
          <SelectDropdown
            v-model="selectedRole"
            :options="roleOptions"
            variant="bordered"
            data-testid="edit-role-select"
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.staff.hdrStatus') }}</label>
          <SelectDropdown
            v-model="selectedStatus"
            :options="statusOptions"
            variant="bordered"
            data-testid="edit-status-select"
          />
        </div>
      </div>
      <div class="modal-footer">
        <AuraButton variant="secondary" :disabled="saving" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          variant="primary"
          data-testid="confirm-edit-role-btn"
          :disabled="saving"
          @click="handleSave"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': saving }">
            {{ saving ? 'sync' : 'save' }}
          </span>
          <span>{{ t('admin.staff.confirmUpdateBtn') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
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

.modal-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  max-width: 460px;
  width: 100%;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline);
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

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.modal-sub {
  margin: 2px 0 0 0;
  font-size: 12px;
  color: var(--muted);
}

.modal-close-btn {
  background: transparent;
  border: 0;
  font-size: 16px;
  color: var(--muted);
  cursor: pointer;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.member-target-card {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-radius: 12px;
  border: 1px solid var(--outline);
}

.target-name {
  font-size: 14px;
  color: var(--ink);
}

.target-email {
  font-size: 12px;
  color: var(--muted);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.icon-18 {
  font-size: 18px;
}

:global(html.dark) .modal-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .modal-header,
:global(html.dark) .modal-footer {
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .modal-footer,
:global(html.dark) .member-target-card {
  background: var(--surface-subtle, #262024);
  border-color: var(--outline, #4d4049);
}
</style>
