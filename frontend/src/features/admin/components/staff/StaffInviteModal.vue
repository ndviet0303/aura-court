<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { StaffMember } from '../../composables/useAdminStaff'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'invite', member: { name: string; email: string; phone: string; role: StaffMember['role'] }): void
}>()

const { t } = useI18n()

const name = ref('')
const email = ref('')
const phone = ref('')
const role = ref<StaffMember['role']>('Receptionist')

function handleSend() {
  if (!name.value.trim() || !email.value.trim()) return
  emit('invite', {
    name: name.value.trim(),
    email: email.value.trim(),
    phone: phone.value.trim(),
    role: role.value,
  })
  name.value = ''
  email.value = ''
  phone.value = ''
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">person_add</span>
          <h3 class="modal-title">{{ t('admin.staff.inviteModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">{{ t('admin.staff.fullNameLabel') }} <span class="required">*</span>:</label>
          <input
            v-model="name"
            type="text"
            class="form-input"
            :placeholder="t('admin.staff.fullNamePlaceholder')"
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.staff.workEmailLabel') }} <span class="required">*</span>:</label>
          <input
            v-model="email"
            type="email"
            class="form-input font-mono"
            placeholder="nhanvien@auracourt.vn"
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.staff.phoneContactLabel') }}</label>
          <input
            v-model="phone"
            type="tel"
            class="form-input font-mono"
            placeholder="0901 234 567"
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.staff.assignedRoleLabel') }}</label>
          <select v-model="role" class="form-input">
            <option value="Manager">{{ t('admin.staff.roleManager') }}</option>
            <option value="Receptionist">{{ t('admin.staff.roleReceptionist') }}</option>
            <option value="Technician">{{ t('admin.staff.roleTechnician') }}</option>
            <option value="Referee">{{ t('admin.staff.roleReferee') }}</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button
          type="button"
          class="btn-primary btn-send-invite"
          :disabled="!name.trim() || !email.trim()"
          @click="handleSend"
        >
          {{ t('admin.staff.sendInviteBtn') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.btn-cancel {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 20px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
