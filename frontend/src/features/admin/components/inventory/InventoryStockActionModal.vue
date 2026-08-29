<script setup lang="ts">
import { ref } from 'vue'
import type { InventoryProduct } from '../../types/inventory'

const props = defineProps<{
  show: boolean
  type: 'in' | 'out' | 'adjust'
  product: InventoryProduct | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', quantity: number, note: string): void
}>()

const quantity = ref(10)
const note = ref('')

function handleSubmit() {
  if (quantity.value <= 0) return
  emit('submit', quantity.value, note.value.trim())
}
</script>

<template>
  <div v-if="show && product" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">
          {{ type === 'in' ? '📥 Nhập Hàng Vào Kho' : type === 'out' ? '📤 Xuất Kho / Hủy Hàng' : '📋 Kiểm Kê Điều Chỉnh' }}
        </h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="target-product-summary">
          <strong class="p-name">{{ product.name }}</strong>
          <span class="p-meta">SKU: {{ product.sku }} · Tồn hiện tại: <strong>{{ product.stock_quantity }} {{ product.unit }}</strong></span>
        </div>

        <div class="form-group">
          <label class="form-label">
            {{ type === 'adjust' ? 'Số lượng tồn kho thực tế sau kiểm kê:' : 'Số lượng giao dịch:' }}
          </label>
          <input v-model.number="quantity" type="number" min="1" class="form-input font-mono" />
        </div>

        <div class="form-group">
          <label class="form-label">Lý do / Ghi chú chứng từ:</label>
          <input v-model="note" type="text" class="form-input" placeholder="Vd: Nhập từ nhà cung cấp, hàng hỏng..." />
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" :disabled="quantity <= 0" @click="handleSubmit">
          Xác Nhận Thực Hiện
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.target-product-summary {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 16px;
}

.p-name { font-size: 15px; }
.p-meta { font-size: 12px; color: var(--muted); }

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
