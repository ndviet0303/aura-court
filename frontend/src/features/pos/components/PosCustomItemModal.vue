<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'add', payload: { name: string; price: number; quantity: number; vat_rate: number }): void
}>()

const { t } = useI18n()

const form = ref({
  name: '',
  price: 50000,
  quantity: 1,
  vat_rate: 0.10,
})

function handleSubmit() {
  if (!form.value.name.trim()) return
  emit('add', {
    name: form.value.name.trim(),
    price: form.value.price || 0,
    quantity: form.value.quantity || 1,
    vat_rate: form.value.vat_rate,
  })
  form.value = { name: '', price: 50000, quantity: 1, vat_rate: 0.10 }
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined modal-icon">add_circle</span>
          <h3 class="modal-title">{{ t('admin.pos.customItemTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">{{ t('admin.pos.customItemName') }}</label>
          <input
            v-model="form.name"
            type="text"
            class="form-input"
            :placeholder="t('admin.pos.customItemPlaceholder')"
            autofocus
          />
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('admin.pos.customItemPrice') }}</label>
          <input
            v-model.number="form.price"
            type="number"
            step="10000"
            class="form-input"
          />
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('admin.pos.customItemQty') }}</label>
          <input
            v-model.number="form.quantity"
            type="number"
            min="1"
            class="form-input"
          />
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('admin.pos.vatRateLabel') }}</label>
          <div class="vat-pill-group">
            <button
              type="button"
              class="vat-pill-btn"
              :class="{ active: form.vat_rate === 0 }"
              @click="form.vat_rate = 0"
            >
              0%
            </button>
            <button
              type="button"
              class="vat-pill-btn"
              :class="{ active: form.vat_rate === 0.08 }"
              @click="form.vat_rate = 0.08"
            >
              8% (F&B)
            </button>
            <button
              type="button"
              class="vat-pill-btn"
              :class="{ active: form.vat_rate === 0.10 }"
              @click="form.vat_rate = 0.10"
            >
              10% (Chuẩn)
            </button>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">
          {{ t('admin.pos.cancel') }}
        </button>
        <button type="button" class="btn-primary" :disabled="!form.name.trim()" @click="handleSubmit">
          <span class="material-symbols-outlined icon-18">add_shopping_cart</span>
          <span>{{ t('admin.pos.addToCart') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 440px;
  background: var(--surface, #1e293b);
  border-radius: 16px;
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.modal-icon {
  font-size: 22px;
  color: var(--primary, #10b981);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #ffffff);
}

.modal-close-btn {
  background: transparent;
  border: none;
  color: var(--muted, #94a3b8);
  cursor: pointer;
  padding: 4px;
}

.modal-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink, #ffffff);
}

.form-input {
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  color: var(--ink, #ffffff);
  font-size: 13px;
  outline: none;
}

.form-input:focus {
  border-color: var(--primary, #10b981);
}

.vat-pill-group {
  display: flex;
  align-items: center;
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 8px;
  padding: 2px;
  gap: 2px;
}

.vat-pill-btn {
  flex: 1;
  border: none;
  background: transparent;
  color: var(--muted, #94a3b8);
  font-size: 11px;
  font-weight: 700;
  padding: 6px 8px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
  text-align: center;
}

.vat-pill-btn:hover {
  color: var(--ink, #ffffff);
}

.vat-pill-btn.active {
  background: var(--primary, #10b981);
  color: #ffffff;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 18px;
  border-top: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
}

.btn-cancel {
  padding: 8px 14px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 8px;
  color: var(--ink, #ffffff);
  font-weight: 600;
  cursor: pointer;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: var(--primary, #10b981);
  border: none;
  border-radius: 8px;
  color: #ffffff;
  font-weight: 700;
  cursor: pointer;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.icon-18 {
  font-size: 18px;
}
</style>
