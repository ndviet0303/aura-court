<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'add', payload: { name: string; price: number; quantity: number }): void
}>()

const { t } = useI18n()

const form = ref({
  name: '',
  price: 50000,
  quantity: 1,
})

function handleSubmit() {
  if (!form.value.name.trim()) return
  emit('add', {
    name: form.value.name.trim(),
    price: form.value.price || 0,
    quantity: form.value.quantity || 1,
  })
  form.value = { name: '', price: 50000, quantity: 1 }
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
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">
          {{ t('admin.pos.cancel') }}
        </button>
        <button type="button" class="btn-primary" @click="handleSubmit">
          {{ t('admin.pos.addToCart') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 440px;
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px solid var(--outline, #e2e8f0);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  overflow: hidden;
  color: var(--ink, #0f172a);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.modal-close-btn {
  background: transparent;
  border: 0;
  color: var(--muted, #64748b);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
}

.modal-body {
  padding: 20px;
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
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #64748b);
}

.form-input {
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline, #e2e8f0);
  background: var(--surface, #ffffff);
  color: var(--ink, #0f172a);
  font-size: 13px;
  outline: none;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid var(--outline, #e2e8f0);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.btn-cancel {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid var(--outline, #e2e8f0);
  background: transparent;
  color: var(--ink, #0f172a);
  font-size: 13px;
  cursor: pointer;
}

.btn-primary {
  padding: 8px 18px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}
</style>
