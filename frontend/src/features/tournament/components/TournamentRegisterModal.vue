<script setup lang="ts">
import { ref } from 'vue'
import type { TournamentItem } from '../types/tournament'

const props = defineProps<{
  show: boolean
  tournament: TournamentItem | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: { name: string; phone: string; partnerName?: string }): void
}>()

const name = ref('')
const phone = ref('')
const partnerName = ref('')

function handleSubmit() {
  if (!name.value.trim() || !phone.value.trim()) return
  emit('submit', {
    name: name.value.trim(),
    phone: phone.value.trim(),
    partnerName: partnerName.value.trim() || undefined,
  })
  name.value = ''
  phone.value = ''
  partnerName.value = ''
}
</script>

<template>
  <div v-if="show && tournament" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">📝 Đăng Ký Tham Gia Giải Đấu</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="tourney-summary-box">
          <strong>{{ tournament.name }}</strong>
          <span>{{ tournament.category }} · Lệ phí: {{ tournament.entry_fee || 'Miễn phí' }}</span>
        </div>

        <div class="reg-form">
          <div class="form-group">
            <label class="form-label">Họ và tên VĐV (Đội trưởng) <span class="required">*</span>:</label>
            <input v-model="name" type="text" class="form-input" placeholder="Vd: Nguyễn Văn A..." />
          </div>

          <div class="form-group">
            <label class="form-label">Số điện thoại liên hệ <span class="required">*</span>:</label>
            <input v-model="phone" type="tel" class="form-input" placeholder="0901 234 567" />
          </div>

          <div v-if="tournament.category?.includes('Đôi')" class="form-group">
            <label class="form-label">Họ và tên đồng đội (Partner):</label>
            <input v-model="partnerName" type="text" class="form-input" placeholder="Vd: Trần Văn B..." />
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button
          class="btn-primary"
          :disabled="!name.trim() || !phone.trim()"
          @click="handleSubmit"
        >
          Xác Nhận & Ghi Danh
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tourney-summary-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 16px;
  font-size: 13px;
}

.tourney-summary-box span {
  font-size: 11px;
  color: var(--muted);
}

.reg-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 24px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
