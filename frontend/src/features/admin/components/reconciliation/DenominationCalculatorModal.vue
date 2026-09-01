<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { DenominationCount } from '../../composables/useAdminShiftReconciliation'

defineProps<{
  show: boolean
  denominations: DenominationCount[]
  totalCash: number
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">calculate</span>
          <h3 class="modal-title">{{ t('admin.shifts.denomModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="denominations-table">
          <div class="denom-header">
            <span>{{ t('admin.shifts.hdrDenom') }}</span>
            <span>{{ t('admin.shifts.hdrCount') }}</span>
            <span>{{ t('admin.shifts.hdrSubtotal') }}</span>
          </div>

          <div
            v-for="d in denominations"
            :key="d.value"
            class="denom-row"
          >
            <strong class="denom-val font-mono">{{ formatCurrency(d.value) }}</strong>
            <input
              v-model.number="d.count"
              type="number"
              min="0"
              class="denom-input font-mono"
            />
            <strong class="denom-subtotal text-pink font-mono">{{ formatCurrency(d.value * (d.count || 0)) }}</strong>
          </div>
        </div>

        <div class="total-denom-bar">
          <span>{{ t('admin.shifts.totalDenomLabel') }}</span>
          <strong class="total-val font-mono">{{ formatCurrency(totalCash) }}</strong>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.close') }}</button>
        <button type="button" class="btn-primary" @click="emit('save')">{{ t('admin.shifts.confirmDrawerCash') }}</button>
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
