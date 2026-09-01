<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
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
              <input v-model.number="amount" type="number" step="10000" class="form-input font-mono" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.shifts.dropReasonLabel') }}</label>
              <input v-model="reason" type="text" class="form-input" :placeholder="t('admin.shifts.dropReasonPlaceholder')" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.shifts.recipientLabel') }}</label>
              <input v-model="recipient" type="text" class="form-input" :placeholder="t('admin.shifts.recipientPlaceholder')" />
            </div>

            <button
              type="button"
              class="btn-add-drop"
              :disabled="amount <= 0 || !reason.trim()"
              @click="handleAdd"
            >
              <span class="material-symbols-outlined icon-16">add</span>
              <span>{{ t('admin.shifts.recordBtn') }}</span>
            </button>
          </div>
        </div>

        <!-- Drops List -->
        <div class="drops-list-box">
          <h4 class="form-sub-title">{{ t('admin.shifts.dropsListTitle', { count: drops.length }) }}</h4>
          <div v-if="drops.length === 0" class="empty-msg">{{ t('admin.shifts.noDrops') }}</div>
          <div v-else class="drops-items">
            <div v-for="d in drops" :key="d.id" class="drop-item-row">
              <div class="drop-info">
                <strong>{{ d.reason }}</strong>
                <span class="drop-meta font-mono">{{ d.time }} · {{ t('admin.shifts.recipientMeta', { name: d.recipient || 'N/A' }) }}</span>
              </div>
              <strong class="drop-amount text-red font-mono">-{{ formatCurrency(d.amount) }}</strong>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.close') }}</button>
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
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.btn-add-drop:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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

.icon-16 {
  font-size: 16px;
}
</style>
