<script setup lang="ts">
import type { DenominationCount } from '../../composables/useAdminShiftReconciliation'

const props = defineProps<{
  show: boolean
  denominations: DenominationCount[]
  totalCash: number
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">💵 Kiểm Đếm Tiền Mặt Theo Mệnh Giá</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="denominations-table">
          <div class="denom-header">
            <span>Mệnh Giá</span>
            <span>Số Lượng Tờ</span>
            <span>Thành Tiền</span>
          </div>

          <div
            v-for="d in denominations"
            :key="d.value"
            class="denom-row"
          >
            <strong class="denom-val">{{ formatMoney(d.value) }}</strong>
            <input
              v-model.number="d.count"
              type="number"
              min="0"
              class="denom-input"
            />
            <strong class="denom-subtotal text-pink">{{ formatMoney(d.value * (d.count || 0)) }}</strong>
          </div>
        </div>

        <div class="total-denom-bar">
          <span>Tổng tiền mặt kiểm đếm:</span>
          <strong class="total-val">{{ formatMoney(totalCash) }}</strong>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
        <button class="btn-primary" @click="emit('save')">Xác Nhận Tiền Két</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.denominations-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
  margin-bottom: 16px;
}

.denom-header {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1.5fr;
  padding: 10px 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.denom-row {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1.5fr;
  padding: 8px 14px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.denom-row:last-child {
  border-bottom: 0;
}

.denom-input {
  width: 80px;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  text-align: center;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.total-denom-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-radius: 10px;
  background: rgba(236, 72, 153, 0.06);
  border: 1px solid rgba(236, 72, 153, 0.2);
}

.total-val {
  font-size: 18px;
  color: var(--pink-dark, #db2777);
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
