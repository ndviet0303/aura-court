<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
import type { RecurringContract } from '../../composables/useAdminContracts'

defineProps<{
  show: boolean
  contract: RecurringContract | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div v-if="show && contract" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">description</span>
          <h3 class="modal-title">
            {{ t('admin.contracts.contractDetailTitle', { code: contract.contract_code }) }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="contract-meta-grid">
          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.contracts.customerClubLabel') }}</span>
            <strong>{{ contract.customer_name }} ({{ contract.phone }})</strong>
          </div>
          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.contracts.fixedCourtLabel') }}</span>
            <strong>{{ resolveLocalizedText(contract.court_name) }} - {{ resolveLocalizedText(contract.venue_name) }}</strong>
          </div>
          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.contracts.scheduleAndSlotLabel') }}</span>
            <strong>{{ contract.weekday }} · {{ contract.time_slot }}</strong>
          </div>
          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.contracts.contractTermLabel') }}</span>
            <strong class="font-mono">{{ contract.start_date }} - {{ contract.end_date }}</strong>
          </div>
        </div>

        <div class="cycles-history-box">
          <h4 class="cycles-title">{{ t('admin.contracts.invoiceCyclesTitle') }}</h4>
          <div class="cycles-table">
            <div class="cycles-header">
              <span>{{ t('admin.contracts.hdrInvoiceCycle') }}</span>
              <span>{{ t('admin.contracts.hdrDueDate') }}</span>
              <span>{{ t('admin.contracts.hdrAmount') }}</span>
              <span>{{ t('admin.contracts.hdrCycleStatus') }}</span>
              <span>{{ t('admin.contracts.hdrReceiptCode') }}</span>
            </div>
            <div
              v-for="cycle in contract.payment_cycles"
              :key="cycle.id"
              class="cycle-row"
            >
              <strong>{{ cycle.cycle_name }}</strong>
              <span class="font-mono">{{ cycle.due_date }}</span>
              <strong class="text-pink font-mono">{{ formatCurrency(cycle.amount) }}</strong>
              <span
                class="cycle-status"
                :class="cycle.status === 'paid' ? 'paid' : 'overdue'"
              >
                ● {{ cycle.status === 'paid' ? t('admin.contracts.statusPaid') : t('admin.contracts.statusUnpaid') }}
              </span>
              <span class="font-mono">{{ cycle.invoice_code }}</span>
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

.contract-meta-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 20px;
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.meta-lbl {
  font-size: 11px;
  color: var(--muted);
}

.cycles-history-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cycles-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
}

.cycles-table {
  display: flex;
  flex-direction: column;
  border-radius: 10px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.cycles-header {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1.2fr 1.2fr 1.5fr;
  padding: 10px 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
}

.cycle-row {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1.2fr 1.2fr 1.5fr;
  padding: 10px 14px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
}

.cycle-row:last-child {
  border-bottom: 0;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.cycle-status.paid { color: #10b981; font-weight: 700; }
.cycle-status.overdue { color: #ef4444; font-weight: 700; }

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>
