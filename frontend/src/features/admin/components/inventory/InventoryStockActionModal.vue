<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { CurrencyInput, SelectDropdown, AuraButton } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { InventoryProduct } from '../../types/inventory'

const props = defineProps<{
  show: boolean
  type: 'in' | 'out' | 'adjust'
  product: InventoryProduct | null
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: { quantity: number; note?: string; unitCost?: number }): void
}>()

const { t } = useI18n()

const quantity = ref(10)
const unitCost = ref(0)
const outReason = ref('damaged')
const note = ref('')

const outReasonOptions = computed<DropdownOption[]>(() => [
  { value: 'damaged', label: t('admin.inventory.reasonDamaged'), icon: 'broken_image' },
  { value: 'expired', label: t('admin.inventory.reasonExpired'), icon: 'event_busy' },
  { value: 'internal_use', label: t('admin.inventory.reasonInternalUse'), icon: 'corporate_fare' },
  { value: 'lost', label: t('admin.inventory.reasonLost'), icon: 'search_off' },
  { value: 'other', label: t('admin.inventory.reasonOther'), icon: 'more_horiz' },
])

const projectedStock = computed(() => {
  if (!props.product) return 0
  const curr = Number(props.product.stock_quantity) || 0
  if (props.type === 'in') {
    return curr + (quantity.value || 0)
  }
  if (props.type === 'out') {
    return Math.max(0, curr - (quantity.value || 0))
  }
  return Math.max(0, quantity.value || 0)
})

const auditVariance = computed(() => {
  if (!props.product || props.type !== 'adjust') return 0
  const curr = Number(props.product.stock_quantity) || 0
  return (quantity.value || 0) - curr
})

watch(
  () => [props.show, props.type, props.product],
  () => {
    if (props.show && props.product) {
      if (props.type === 'adjust') {
        quantity.value = Number(props.product.stock_quantity) || 0
      } else {
        quantity.value = 10
      }
      unitCost.value = Number(props.product.cost) || 0
      outReason.value = 'damaged'
      note.value = ''
    }
  },
  { immediate: true }
)

function handleSubmit() {
  if (quantity.value < 0) return
  if (props.type !== 'adjust' && quantity.value <= 0) return

  let finalNote = note.value.trim()
  if (props.type === 'out' && outReason.value) {
    const reasonLabel = outReasonOptions.value.find((r) => r.value === outReason.value)?.label || outReason.value
    finalNote = finalNote ? `[${reasonLabel}] ${finalNote}` : `[${reasonLabel}]`
  }

  emit('submit', {
    quantity: quantity.value,
    note: finalNote || undefined,
    unitCost: props.type === 'in' ? unitCost.value : undefined,
  })
}
</script>

<template>
  <div v-if="show && product" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <div class="title-with-icon">
          <span
            class="material-symbols-outlined card-title-icon"
            :class="type === 'in' ? 'icon-green' : type === 'out' ? 'icon-orange' : 'icon-blue'"
          >
            {{ type === 'in' ? 'move_to_inbox' : type === 'out' ? 'unarchive' : 'tune' }}
          </span>
          <div>
            <h3 class="modal-title">
              {{ type === 'in' ? t('admin.inventory.stockInTitle') : type === 'out' ? t('admin.inventory.stockOutTitle') : t('admin.inventory.adjustStockTitle') }}
            </h3>
            <p class="modal-sub">
              {{ type === 'in' ? t('admin.inventory.stockInSub') : type === 'out' ? t('admin.inventory.stockOutSub') : t('admin.inventory.adjustStockSub') }}
            </p>
          </div>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- Target Product Summary -->
        <div class="target-product-summary">
          <div class="p-summary-left">
            <strong class="p-name">{{ resolveLocalizedText(product.name) }}</strong>
            <span class="p-meta font-mono">
              SKU: {{ product.sku }} · {{ t('admin.inventory.currentStockLabel') }}: <strong>{{ product.stock_quantity }} {{ product.unit }}</strong>
            </span>
          </div>

          <div class="p-summary-right">
            <span class="proj-label">{{ t('admin.inventory.projectedStock') }}</span>
            <strong
              class="proj-val font-mono"
              :class="projectedStock === 0 ? 'text-red' : projectedStock <= product.min_stock ? 'text-yellow' : 'text-green'"
            >
              {{ projectedStock }} {{ product.unit }}
            </strong>
          </div>
        </div>

        <!-- Inputs Form -->
        <form class="stock-action-form" @submit.prevent="handleSubmit">
          <!-- Quantity Input -->
          <div class="form-group">
            <label class="form-label flex-between">
              <span>{{ type === 'adjust' ? t('admin.inventory.qtyAfterAuditLabel') : t('admin.inventory.transactionQtyLabel') }} *</span>
              <span v-if="type === 'adjust'" class="variance-preview font-mono" :class="auditVariance >= 0 ? 'text-green' : 'text-red'">
                {{ auditVariance >= 0 ? `+${auditVariance}` : auditVariance }} {{ product.unit }} ({{ t('admin.inventory.varianceLabel') }})
              </span>
            </label>
            <div class="qty-input-wrap">
              <input
                v-model.number="quantity"
                type="number"
                :min="type === 'adjust' ? 0 : 1"
                class="form-input font-mono"
                required
              />
              <span class="qty-unit font-mono">{{ product.unit }}</span>
            </div>
          </div>

          <!-- Unit Cost (Stock In only) -->
          <div v-if="type === 'in'" class="form-group">
            <label class="form-label">{{ t('admin.inventory.unitImportCostLabel') }}</label>
            <CurrencyInput v-model="unitCost" />
          </div>

          <!-- Reason (Stock Out only) -->
          <div v-if="type === 'out'" class="form-group">
            <label class="form-label">{{ t('admin.inventory.outReasonLabel') }}</label>
            <SelectDropdown
              :model-value="outReason"
              :options="outReasonOptions"
              variant="bordered"
              @update:model-value="outReason = String($event)"
            />
          </div>

          <!-- Notes / Reference -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.inventory.docNoteLabel') }}</label>
            <input
              v-model="note"
              type="text"
              class="form-input"
              :placeholder="type === 'in' ? t('admin.inventory.noteInPlaceholder') : type === 'out' ? t('admin.inventory.noteOutPlaceholder') : t('admin.inventory.noteAdjustPlaceholder')"
            />
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">{{ t('common.cancel') }}</AuraButton>
        <AuraButton
          :variant="type === 'in' ? 'primary' : type === 'out' ? 'danger' : 'secondary'"
          :disabled="quantity < 0 || (type !== 'adjust' && quantity <= 0) || saving"
          @click="handleSubmit"
        >
          <span v-if="saving" class="material-symbols-outlined icon-16 spin-anim">progress_activity</span>
          <span>{{ saving ? t('common.saving') : t('admin.inventory.confirmActionBtn') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 520px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-title-icon {
  font-size: 28px;
}

.icon-green { color: #10b981; }
.icon-orange { color: #f59e0b; }
.icon-blue { color: #3b82f6; }

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.modal-sub {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: var(--muted);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
}

.modal-close-btn:hover {
  color: var(--ink);
}

.modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.target-product-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-radius: 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
}

.p-summary-left {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.p-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.p-meta {
  font-size: 12px;
  color: var(--muted);
}

.p-summary-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.proj-label {
  font-size: 10.5px;
  color: var(--muted);
  text-transform: uppercase;
  font-weight: 700;
}

.proj-val {
  font-size: 16px;
  font-weight: 800;
}

.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }

.stock-action-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
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

.flex-between {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.variance-preview {
  font-size: 11px;
  font-weight: 700;
}

.qty-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.qty-unit {
  position: absolute;
  right: 14px;
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
  pointer-events: none;
}

.form-input {
  width: 100%;
  height: 42px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  outline: none;
  box-sizing: border-box;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
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
  background: var(--surface-secondary, rgba(0, 0, 0, 0.015));
}

.icon-16 { font-size: 16px; }

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
