<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
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

const { t } = useI18n()

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
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">
            {{ type === 'in' ? 'add_box' : type === 'out' ? 'indeterminate_check_box' : 'tune' }}
          </span>
          <h3 class="modal-title">
            {{ type === 'in' ? t('admin.inventory.stockInTitle') : type === 'out' ? t('admin.inventory.stockOutTitle') : t('admin.inventory.adjustStockTitle') }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="target-product-summary">
          <strong class="p-name">{{ resolveLocalizedText(product.name) }}</strong>
          <span class="p-meta font-mono">
            {{ t('admin.inventory.targetCurrentStock', { sku: product.sku, stock: product.stock_quantity, unit: product.unit }) }}
          </span>
        </div>

        <div class="form-group">
          <label class="form-label">
            {{ type === 'adjust' ? t('admin.inventory.qtyAfterAuditLabel') : t('admin.inventory.transactionQtyLabel') }}
          </label>
          <input v-model.number="quantity" type="number" min="1" class="form-input font-mono" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.inventory.docNoteLabel') }}</label>
          <input v-model="note" type="text" class="form-input" :placeholder="t('admin.inventory.docNotePlaceholder')" />
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="button" class="btn-primary" :disabled="quantity <= 0" @click="handleSubmit">
          {{ t('admin.inventory.confirmActionBtn') }}
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
