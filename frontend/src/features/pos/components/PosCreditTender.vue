<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { PosVenueCredit } from '../composables/usePosVenueCredit'

// The credit tender's amount and, when credit does not cover the order, how the rest is paid.
const props = defineProps<{ credit: PosVenueCredit; total: number; hideQr?: boolean }>()

const { t } = useI18n()
const rests = () => (props.hideQr ? (['cash'] as const) : (['cash', 'qr'] as const))
</script>

<template>
  <div class="credit-tender" data-testid="pos-credit-tender">
    <div class="credit-row">
      <span>{{ t('admin.pos.creditBalance') }}</span>
      <strong data-testid="pos-credit-balance">{{ formatCurrency(credit.balance) }}</strong>
    </div>
    <label class="credit-row">
      <span>{{ t('admin.pos.creditAmount') }}</span>
      <input
        :value="credit.part"
        type="number"
        min="1"
        :max="Math.min(credit.balance, total)"
        step="1000"
        class="credit-input"
        data-testid="pos-credit-amount"
        @change="credit.setAmount(Number(($event.target as HTMLInputElement).value))"
      />
    </label>
    <div v-if="credit.part < total" class="credit-row" data-testid="pos-credit-rest">
      <span>{{ t('admin.pos.creditRest', { amount: formatCurrency(total - credit.part) }) }}</span>
      <div class="rest-pills">
        <button
          v-for="method in rests()"
          :key="method"
          type="button"
          class="rest-pill"
          :class="{ active: credit.rest === method }"
          :data-testid="`pos-credit-rest-${method}`"
          @click="credit.setRest(method)"
        >
          {{ t(method === 'cash' ? 'admin.pos.payCash' : 'admin.pos.payQr') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.credit-tender {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  font-size: 13px;
  color: var(--ink, #fff);
}
.credit-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.credit-input {
  width: 130px;
  padding: 6px 8px;
  border-radius: 8px;
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  background: transparent;
  color: inherit;
  text-align: right;
  font-family: var(--font-mono, monospace);
}
.rest-pills {
  display: flex;
  gap: 6px;
}
.rest-pill {
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  background: transparent;
  color: inherit;
  cursor: pointer;
}
.rest-pill.active {
  border-color: var(--primary, #6b2d3f);
  background: var(--primary, #6b2d3f);
  color: #fff;
}
</style>
