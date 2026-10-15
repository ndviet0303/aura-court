<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import SelectDropdown, { type DropdownOption } from '@/shared/components/forms/SelectDropdown.vue'
import type { StaffMember, VenueOption } from '../../composables/useAdminStaff'

const props = withDefaults(
  defineProps<{
    show: boolean
    saving?: boolean
    venues?: VenueOption[]
  }>(),
  {
    saving: false,
    venues: () => [],
  },
)

const emit = defineEmits<{
  (e: 'close'): void
  (
    e: 'invite',
    member: {
      name: string
      email: string
      phone: string
      role: StaffMember['role']
      venueIds: number[]
    },
  ): void
}>()

const { t } = useI18n()

const name = ref('')
const email = ref('')
const phone = ref('')
const role = ref<StaffMember['role']>('Receptionist')
const venueId = ref<number | string>('all')

const roleOptions = computed<DropdownOption[]>(() => [
  { value: 'Manager', label: t('admin.staff.roleManager'), icon: 'manage_accounts' },
  { value: 'Receptionist', label: t('admin.staff.roleReceptionist'), icon: 'point_of_sale' },
  { value: 'Technician', label: t('admin.staff.roleTechnician'), icon: 'build' },
  { value: 'Referee', label: t('admin.staff.roleReferee'), icon: 'sports' },
  { value: 'Staff', label: t('admin.staff.roleStaff'), icon: 'badge' },
])

const venueOptions = computed<DropdownOption[]>(() => {
  const list: DropdownOption[] = [
    { value: 'all', label: t('admin.staff.allVenuesOption'), icon: 'domain' },
  ]
  props.venues.forEach((v) => {
    list.push({ value: v.id, label: v.name, icon: 'location_on' })
  })
  return list
})

function handleSend() {
  if (!name.value.trim() || !email.value.trim()) return
  emit('invite', {
    name: name.value.trim(),
    email: email.value.trim(),
    phone: phone.value.trim(),
    role: role.value,
    // Staff reach only the venues they are given, so "all" is sent as the explicit list.
    venueIds: venueId.value === 'all' ? props.venues.map((v) => v.id) : [Number(venueId.value)],
  })
}

watch(
  () => props.show,
  (val) => {
    if (val) {
      name.value = ''
      email.value = ''
      phone.value = ''
      role.value = 'Receptionist'
      venueId.value = 'all'
    }
  },
)
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">person_add</span>
          <h3 class="modal-title">{{ t('admin.staff.inviteModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label"
            >{{ t('admin.staff.fullNameLabel') }} <span class="required">*</span></label
          >
          <input
            v-model="name"
            type="text"
            class="form-input"
            data-testid="invite-name-input"
            :placeholder="t('admin.staff.fullNamePlaceholder')"
          />
        </div>
        <div class="form-group">
          <label class="form-label"
            >{{ t('admin.staff.workEmailLabel') }} <span class="required">*</span></label
          >
          <input
            v-model="email"
            type="email"
            class="form-input font-mono"
            data-testid="invite-email-input"
            placeholder="nhanvien@auracourt.vn"
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.staff.phoneContactLabel') }}</label>
          <input
            v-model="phone"
            type="tel"
            class="form-input font-mono"
            data-testid="invite-phone-input"
            placeholder="0901 234 567"
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.staff.assignedRoleLabel') }}</label>
          <SelectDropdown
            v-model="role"
            :options="roleOptions"
            variant="bordered"
            data-testid="invite-role-select"
          />
        </div>

        <div v-if="venues && venues.length > 0" class="form-group">
          <label class="form-label">{{ t('admin.staff.assignedVenueLabel') }}</label>
          <SelectDropdown
            v-model="venueId"
            :options="venueOptions"
            variant="bordered"
            data-testid="invite-venue-select"
          />
        </div>
      </div>
      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="secondary" :disabled="saving" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          variant="primary"
          class="btn-send-invite"
          data-testid="send-invite-btn"
          :disabled="!name.trim() || !email.trim() || saving"
          @click="handleSend"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': saving }">
            {{ saving ? 'sync' : 'send' }}
          </span>
          <span>{{ t('admin.staff.sendInviteBtn') }}</span>
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
  max-width: 480px;
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
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 16.5px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  background: transparent;
  border: 0;
  font-size: 16px;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  line-height: 1;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
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

.required {
  color: #ef4444;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
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

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
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

:global(html.dark) .modal-footer {
  background: var(--surface-subtle, #262024);
}

:global(html.dark) .form-input {
  background: var(--surface-subtle, #262024);
  color: var(--ink, #faf7f3);
  border-color: var(--outline, #4d4049);
}
</style>
