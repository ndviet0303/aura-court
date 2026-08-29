<script setup lang="ts">
import { ref, watch } from 'vue'
import type { BeneficiaryInfo } from '../../composables/useAdminPayouts'

const props = defineProps<{
  show: boolean
  beneficiary: BeneficiaryInfo
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: { bank_code: string; account_number: string; account_holder: string }): void
}>()

const bankCode = ref('')
const accountNumber = ref('')
const accountHolder = ref('')

watch(
  () => props.beneficiary,
  (b) => {
    if (b) {
      bankCode.value = b.bank_code
      accountNumber.value = b.account_number
      accountHolder.value = b.account_holder
    }
  },
  { immediate: true }
)

function handleFormSubmit() {
  if (!accountNumber.value.trim() || !accountHolder.value.trim()) return
  emit('submit', {
    bank_code: bankCode.value,
    account_number: accountNumber.value.trim(),
    account_holder: accountHolder.value.trim(),
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop aura-modal-backdrop" @click.self="emit('close')">
    <div class="modal-card aura-modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">🏦 Cập Nhật Tài Khoản Thụ Hưởng Doanh Thu</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <form class="modal-form" @submit.prevent="handleFormSubmit">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Ngân hàng thụ hưởng:</label>
            <input v-model="bankCode" type="text" class="form-input" placeholder="VCB / TCB / MB..." />
          </div>

          <div class="form-group">
            <label class="form-label">Số tài khoản ngân hàng:</label>
            <input v-model="accountNumber" type="text" class="form-input" placeholder="0123456789..." />
          </div>

          <div class="form-group">
            <label class="form-label">Tên chủ tài khoản (In hoa không dấu):</label>
            <input v-model="accountHolder" type="text" class="form-input" placeholder="CONG TY TNHH..." />
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="emit('close')">Huỷ</button>
          <button type="submit" class="btn-primary" :disabled="!accountNumber.trim() || !accountHolder.trim()">
            Lưu Tài Khoản
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.modal-form {
  display: flex;
  flex-direction: column;
}

.modal-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
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
