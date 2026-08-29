<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  show: boolean
  availableNet: number
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', amount: number): void
}>()

const amount = ref<number>(1000000)

function handleFormSubmit() {
  if (amount.value <= 0) return
  emit('submit', amount.value)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop aura-modal-backdrop" @click.self="emit('close')">
    <div class="modal-card aura-modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">💸 Tạo Lệnh Rút Tiền Về Tài Khoản</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <form class="modal-form" @submit.prevent="handleFormSubmit">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Số tiền muốn rút (VND):</label>
            <input
              v-model.number="amount"
              type="number"
              min="100000"
              step="100000"
              class="form-input"
            />
          </div>
          <span class="sub-hint">Số dư khả dụng: {{ availableNet.toLocaleString('vi-VN') }} ₫</span>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="emit('close')">Huỷ</button>
          <button type="submit" class="btn-primary" :disabled="amount <= 0">
            Xác Nhận Rút Tiền
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

.sub-hint {
  font-size: 12px;
  color: var(--muted);
  margin-top: 6px;
  display: block;
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
