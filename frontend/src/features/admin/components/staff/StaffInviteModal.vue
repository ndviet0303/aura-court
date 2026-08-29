<script setup lang="ts">
import { ref } from 'vue'
import type { StaffMember } from '../../composables/useAdminStaff'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'invite', member: { name: string; email: string; phone: string; role: StaffMember['role'] }): void
}>()

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
        <h3 class="modal-title">✉️ Thêm Nhân Viên Mới & Phân Quyền</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Họ và tên nhân sự <span class="required">*</span>:</label>
          <input
            v-model="name"
            type="text"
            class="form-input"
            placeholder="Vd: Nguyễn Văn A..."
          />
        </div>

        <div class="form-group">
          <label class="form-label">Email công việc <span class="required">*</span>:</label>
          <input
            v-model="email"
            type="email"
            class="form-input"
            placeholder="nhanvien@auracourt.vn"
          />
        </div>

        <div class="form-group">
          <label class="form-label">Số điện thoại liên hệ:</label>
          <input
            v-model="phone"
            type="tel"
            class="form-input"
            placeholder="0901 234 567"
          />
        </div>

        <div class="form-group">
          <label class="form-label">Vai trò & Vị trí đảm nhiệm:</label>
          <select v-model="role" class="form-input">
            <option value="Manager">Quản lý cơ sở (Manager)</option>
            <option value="Receptionist">Thu ngân / Lễ tân (Receptionist)</option>
            <option value="Technician">Kỹ thuật viên sân (Technician)</option>
            <option value="Referee">Trọng tài giải đấu (Referee)</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button
          class="btn-primary btn-send-invite"
          :disabled="!name.trim() || !email.trim()"
          @click="handleSend"
        >
          Gửi Thư Mời
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
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
