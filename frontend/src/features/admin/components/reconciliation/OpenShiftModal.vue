<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, CurrencyInput } from '@/shared/components'

defineProps<{
  show: boolean
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', openingCash: number): void
}>()

const { t } = useI18n()

const openingCash = ref<number>(1000000)

const presets = [
  { label: '0 ₫', value: 0 },
  { label: '500.000 ₫', value: 500000 },
  { label: '1.000.000 ₫', value: 1000000 },
  { label: '2.000.000 ₫', value: 2000000 },
]

function handleSubmit() {
  if (openingCash.value < 0) return
  emit('submit', openingCash.value)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">play_circle</span>
          <h3 class="modal-title">{{ t('admin.shifts.openShiftModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <p class="modal-intro">{{ t('admin.shifts.openShiftDesc') }}</p>

        <div class="form-group">
          <label class="form-label">{{ t('admin.shifts.startingFloatLabel') }}</label>
          <CurrencyInput v-model="openingCash" size="md" placeholder="0" />
        </div>

        <!-- Presets -->
        <div class="presets-row">
          <button
            v-for="p in presets"
            :key="p.value"
            type="button"
            class="preset-btn"
            :class="{ active: openingCash === p.value }"
            @click="openingCash = p.value"
          >
            {{ p.label }}
          </button>
        </div>

        <div class="info-callout">
          <span class="material-symbols-outlined icon-16">info</span>
          <span
            >Số tiền này sẽ được dùng làm căn cứ đối chiếu số dư tiền mặt trong két khi đóng
            ca.</span
          >
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" :disabled="saving" @click="emit('close')">
          {{ t('common.close') }}
        </AuraButton>
        <AuraButton variant="primary" :disabled="saving || openingCash < 0" @click="handleSubmit">
          <span class="material-symbols-outlined icon-16" :class="{ 'spin-icon': saving }">
            check
          </span>
          <span>{{ t('admin.shifts.confirmOpenShift') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-card {
  width: 100%;
  max-width: 480px;
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  font-size: 16px;
  padding: 4px;
}

.modal-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.modal-intro {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  line-height: 1.4;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 15px;
  font-weight: 700;
  outline: none;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
}

.presets-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.preset-btn {
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.preset-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.preset-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.info-callout {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(59, 130, 246, 0.08);
  color: #3b82f6;
  font-size: 12px;
  line-height: 1.4;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--outline);
}

.icon-16 {
  font-size: 16px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

:global(html.dark) .modal-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}
</style>
