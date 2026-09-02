<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { InventoryProduct } from '../../types/inventory'

defineProps<{
  show: boolean
  product: InventoryProduct | null
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div v-if="show && product" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon text-red">delete_forever</span>
          <div>
            <h3 class="modal-title">{{ t('admin.inventory.deleteProductTitle') }}</h3>
            <p class="modal-sub">{{ t('admin.inventory.deleteProductSub') }}</p>
          </div>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <p class="confirm-message">
          {{ t('admin.inventory.deleteConfirmMsg', { name: resolveLocalizedText(product.name), sku: product.sku }) }}
        </p>
        <div v-if="product.stock_quantity > 0" class="stock-warning-box">
          <span class="material-symbols-outlined icon-18 text-yellow">warning</span>
          <span class="warning-text">
            {{ t('admin.inventory.deleteWithStockWarning', { stock: product.stock_quantity, unit: product.unit }) }}
          </span>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">{{ t('common.cancel') }}</AuraButton>
        <AuraButton variant="danger" :disabled="saving" @click="emit('confirm')">
          <span v-if="saving" class="material-symbols-outlined icon-16 spin-anim">progress_activity</span>
          <span>{{ saving ? t('common.processing') : t('common.delete') }}</span>
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
  max-width: 460px;
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

.text-red { color: #ef4444; }
.text-yellow { color: #f59e0b; }

.modal-title {
  margin: 0;
  font-size: 17px;
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
  gap: 14px;
}

.confirm-message {
  margin: 0;
  font-size: 14px;
  color: var(--ink);
  line-height: 1.5;
}

.stock-warning-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 10px;
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.25);
  font-size: 12.5px;
  color: var(--ink);
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
.icon-18 { font-size: 18px; }

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
