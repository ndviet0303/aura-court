<script setup lang="ts">
import { ref } from 'vue'
import type { ShiftCashDrop } from '../../composables/useAdminShiftReconciliation'

defineProps<{
  show: boolean
  drops: ShiftCashDrop[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'addDrop', payload: { amount: number; reason: string; recipient?: string }): void
}>()

const amount = ref(200000)
const reason = ref('')
const recipient = ref('')

function handleAdd() {
  if (amount.value <= 0 || !reason.value.trim()) return
  emit('addDrop', {
    amount: amount.value,
    reason: reason.value.trim(),
    recipient: recipient.value.trim() || undefined,
  })
  reason.value = ''
  recipient.value = ''
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">💸 Rút Tiền Két / Chi Phí Tiền Mặt Đột Xuất</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- New Drop Form -->
        <div class="drop-form-box">
          <h4 class="form-sub-title">Ghi Nhận Khoản Rút Mới</h4>
          <div class="drop-inputs-grid">
            <div class="form-group">
              <label class="form-label">Số tiền rút (VND):</label>
              <input v-model.number="amount" type="number" step="10000" class="form-input" />
            </div>

            <div class="form-group">
              <label class="form-label">Lý do chi tiền:</label>
              <input v-model="reason" type="text" class="form-input" placeholder="Vd: Mua đá lạnh, phụ tùng..." />
            </div>

            <div class="form-group">
              <label class="form-label">Người nhận / Cửa hàng:</label>
              <input v-model="recipient" type="text" class="form-input" placeholder="Vd: Tạp hoá Minh Tâm..." />
            </div>

            <button
              class="btn-add-drop"
              :disabled="amount <= 0 || !reason.trim()"
              @click="handleAdd"
            >
              + Ghi Nhận
            </button>
          </div>
        </div>

        <!-- Drops List -->
        <div class="drops-list-box">
          <h4 class="form-sub-title">Danh Sách Khoản Rút Trong Ca ({{ drops.length }})</h4>
          <div v-if="drops.length === 0" class="empty-msg">Chưa có khoản rút tiền két nào trong ca này.</div>
          <div v-else class="drops-items">
            <div v-for="d in drops" :key="d.id" class="drop-item-row">
              <div class="drop-info">
                <strong>{{ d.reason }}</strong>
                <span class="drop-meta">{{ d.time }} · Người nhận: {{ d.recipient || 'N/A' }}</span>
              </div>
              <strong class="drop-amount text-red">-{{ formatMoney(d.amount) }}</strong>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.drop-form-box {
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 20px;
}

.form-sub-title {
  margin: 0 0 12px;
  font-size: 13px;
  font-weight: 700;
}

.drop-inputs-grid {
  display: grid;
  grid-template-columns: 1fr 1.5fr 1fr auto;
  gap: 12px;
  align-items: end;
}

.btn-add-drop {
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.drops-items {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.drop-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.drop-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.drop-meta {
  font-size: 11px;
  color: var(--muted);
}

.drop-amount {
  font-size: 14px;
}

.text-red {
  color: #ef4444;
}

.empty-msg {
  font-size: 12px;
  color: var(--muted);
  font-style: italic;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>
