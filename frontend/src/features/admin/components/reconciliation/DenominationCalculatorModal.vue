<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { DenominationCount } from '../../composables/useAdminShiftReconciliation'

defineProps<{
  show: boolean
  denominations: DenominationCount[]
  totalCash: number
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save'): void
  (e: 'increment', val: number, delta: number): void
  (e: 'reset'): void
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
            <span class="text-right">{{ t('admin.shifts.hdrSubtotal') }}</span>
          </div>

          <div v-for="d in denominations" :key="d.value" class="denom-row">
            <strong class="denom-val font-mono">{{ formatCurrency(d.value) }}</strong>

            <div class="count-input-group">
              <input v-model.number="d.count" type="number" min="0" class="denom-input font-mono" />
              <div class="quick-increment-btns">
                <button type="button" class="btn-increment" @click="emit('increment', d.value, 1)">
                  +1
                </button>
                <button type="button" class="btn-increment" @click="emit('increment', d.value, 5)">
                  +5
                </button>
                <button type="button" class="btn-increment" @click="emit('increment', d.value, 10)">
                  +10
                </button>
                <button
                  v-if="d.count > 0"
                  type="button"
                  class="btn-increment btn-reset-row"
                  @click="d.count = 0"
                >
                  0
                </button>
              </div>
            </div>

            <strong class="denom-subtotal text-pink font-mono text-right">
              {{ formatCurrency(d.value * (d.count || 0)) }}
            </strong>
          </div>
        </div>

        <div class="total-denom-bar">
          <div class="total-left">
            <span>{{ t('admin.shifts.totalDenomLabel') }}</span>
            <button type="button" class="btn-reset-all" @click="emit('reset')">
              <span class="material-symbols-outlined icon-14">restart_alt</span>
              <span>Đặt lại</span>
            </button>
          </div>
          <strong class="total-val font-mono">{{ formatCurrency(totalCash) }}</strong>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" @click="emit('close')">
          {{ t('common.close') }}
        </AuraButton>
        <AuraButton variant="primary" @click="emit('save')">
          <span class="material-symbols-outlined icon-16">check</span>
          <span>{{ t('admin.shifts.confirmDrawerCash') }}</span>
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
  max-width: 580px;
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
  gap: 14px;
}

.denominations-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.denom-header {
  display: grid;
  grid-template-columns: 1.4fr 2.2fr 1.6fr;
  padding: 10px 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.denom-row {
  display: grid;
  grid-template-columns: 1.4fr 2.2fr 1.6fr;
  padding: 8px 14px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.denom-row:last-child {
  border-bottom: 0;
}

.count-input-group {
  display: flex;
  align-items: center;
  gap: 6px;
}

.denom-input {
  width: 56px;
  padding: 4px 6px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  text-align: center;
  outline: none;
}

.quick-increment-btns {
  display: flex;
  gap: 3px;
}

.btn-increment {
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 10.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.1s;
}

.btn-increment:hover {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.btn-reset-row {
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.text-right {
  text-align: right;
}

.total-denom-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 18px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.08);
  border: 1px solid rgba(219, 39, 119, 0.2);
}

.total-left {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

.btn-reset-all {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 3px 8px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  font-size: 11px;
  cursor: pointer;
}

.btn-reset-all:hover {
  color: #ef4444;
  border-color: #ef4444;
}

.total-val {
  font-size: 18px;
  color: var(--pink-dark, #db2777);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--outline);
}

.icon-14 {
  font-size: 14px;
}
.icon-16 {
  font-size: 16px;
}

:global(html.dark) .modal-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .denom-header {
  background: rgba(255, 255, 255, 0.03);
}
</style>
