<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton, CurrencyInput } from '@/shared/components'
import type { ShiftCashDrop } from '../../composables/useAdminShiftReconciliation'

defineProps<{
  show: boolean
  drops: ShiftCashDrop[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'addDrop', payload: { amount: number; reason: string; recipient?: string }): void
}>()

const { t } = useI18n()

const amount = ref<number>(200000)
const reason = ref('')
const recipient = ref('')

function handleAdd() {
  if (!amount.value || amount.value <= 0 || !reason.value.trim()) return
  emit('addDrop', {
    amount: amount.value,
    reason: reason.value.trim(),
    recipient: recipient.value.trim() || undefined,
  })
  reason.value = ''
  recipient.value = ''
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">payments</span>
          <h3 class="modal-title">{{ t('admin.shifts.cashDropsModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- New Drop Form -->
        <div class="drop-form-box">
          <h4 class="form-sub-title">{{ t('admin.shifts.recordNewDrop') }}</h4>
          <div class="drop-inputs-grid">
            <div class="form-group">
              <label class="form-label">{{ t('admin.shifts.dropAmountLabel') }}</label>
              <CurrencyInput
                v-model="amount"
                size="md"
                placeholder="0"
              />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.shifts.dropReasonLabel') }}</label>
              <input
                v-model="reason"
                type="text"
                class="form-input"
                :placeholder="t('admin.shifts.dropReasonPlaceholder')"
              />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.shifts.recipientLabel') }}</label>
              <input
                v-model="recipient"
                type="text"
                class="form-input"
                :placeholder="t('admin.shifts.recipientPlaceholder')"
              />
            </div>

            <div class="form-group action-group">
              <AuraButton
                variant="primary"
                :disabled="!amount || amount <= 0 || !reason.trim()"
                @click="handleAdd"
              >
                <span class="material-symbols-outlined icon-16">add</span>
                <span>{{ t('admin.shifts.recordBtn') }}</span>
              </AuraButton>
            </div>
          </div>
        </div>

        <!-- Drops List -->
        <div class="drops-list-box">
          <h4 class="form-sub-title">{{ t('admin.shifts.dropsListTitle', { count: drops.length }) }}</h4>
          <div v-if="drops.length === 0" class="empty-msg">
            <span class="material-symbols-outlined icon-16">info</span>
            <span>{{ t('admin.shifts.noDrops') }}</span>
          </div>
          <div v-else class="drops-items">
            <div v-for="d in drops" :key="d.id" class="drop-item-row">
              <div class="drop-info">
                <strong>{{ d.reason }}</strong>
                <span class="drop-meta font-mono">
                  {{ d.time }} · {{ t('admin.shifts.recipientMeta', { name: d.recipient || 'N/A' }) }}
                </span>
              </div>
              <strong class="drop-amount text-red font-mono">-{{ formatCurrency(d.amount) }}</strong>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" @click="emit('close')">
          {{ t('common.close') }}
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
  max-width: 680px;
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  max-height: 90vh;
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
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.drop-form-box {
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.form-sub-title {
  margin: 0 0 12px;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.drop-inputs-grid {
  display: grid;
  grid-template-columns: 1fr 1.4fr 1fr auto;
  gap: 10px;
  align-items: end;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-label {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--muted);
}

.form-input {
  width: 100%;
  padding: 9px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
}

.action-group {
  justify-content: flex-end;
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
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.drop-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
  color: var(--ink);
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
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--muted);
  font-style: italic;
  padding: 12px 0;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding: 14px 20px;
  border-top: 1px solid var(--outline);
}

.icon-16 {
  font-size: 16px;
}

:global(html.dark) .modal-card,
:global(html.dark) .drop-item-row {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .drop-form-box {
  background: rgba(255, 255, 255, 0.02);
}

@media (max-width: 640px) {
  .drop-inputs-grid {
    grid-template-columns: 1fr;
  }
}
</style>
