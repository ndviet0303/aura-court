<script setup lang="ts">
import { computed, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { useBookingStore } from '../stores/bookingStore'

// "Dùng credit" (venue-credit spec): shown only when the customer holds credit at the court's
// organization. The credit pays up to the amount payable; the rest is paid by the owner's QR.
// A null payable is a quote still pending: the choice is kept and checkout waits for the quote.
const props = defineProps<{ organizationId: number; payable: number | null }>()

const { t } = useI18n()
const store = useBookingStore()
const balance = computed(() => store.venueCreditBalanceAt(props.organizationId))
const max = computed(() => Math.min(balance.value, props.payable ?? 0))
const using = computed({
  get: () => store.venueCreditAmount > 0,
  set: (on: boolean) => (store.venueCreditAmount = on ? max.value : 0),
})

watch(
  () => props.organizationId,
  () => {
    store.venueCreditAmount = 0
    store.loadVenueCredits()
  },
  { immediate: true },
)
// A new price or a smaller balance never lets the credit exceed what is payable.
watch(max, (limit) => {
  if (props.payable !== null && store.venueCreditAmount > limit) store.venueCreditAmount = limit
})
// Only an open checkout form spends credit; leaving it drops the choice.
onUnmounted(store.clearVenueCredit)

function setAmount(event: Event) {
  const value = Math.floor(Number((event.target as HTMLInputElement).value) || 0)
  store.venueCreditAmount = Math.min(Math.max(value, 1), max.value)
}
</script>

<template>
  <section v-if="max > 0" class="credit-picker" data-testid="venue-credit-picker">
    <label class="credit-toggle">
      <input v-model="using" type="checkbox" data-testid="venue-credit-use" />
      <span>{{ t('reservations.credit.use', { balance: formatCurrency(balance) }) }}</span>
    </label>
    <template v-if="using">
      <label class="credit-amount">
        <span>{{ t('reservations.credit.amount', { max: formatCurrency(max) }) }}</span>
        <input
          :value="store.venueCreditAmount"
          type="number"
          min="1"
          :max="max"
          step="1000"
          data-testid="venue-credit-amount"
          @change="setAmount"
        />
      </label>
      <dl class="credit-split">
        <div>
          <dt>{{ t('reservations.credit.paid') }}</dt>
          <dd data-testid="venue-credit-paid">-{{ formatCurrency(store.venueCreditAmount) }}</dd>
        </div>
        <div>
          <dt>{{ t('reservations.credit.rest') }}</dt>
          <dd data-testid="venue-credit-rest">
            {{ formatCurrency((payable ?? 0) - store.venueCreditAmount) }}
          </dd>
        </div>
      </dl>
    </template>
  </section>
</template>

<style scoped>
.credit-picker {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 8px;
  border-top: 1px dashed var(--outline);
}
.credit-toggle,
.credit-amount {
  display: flex;
  align-items: center;
  gap: 8px;
}
.credit-amount {
  justify-content: space-between;
  color: var(--muted);
}
.credit-amount input {
  width: 140px;
  padding: 6px 8px;
  border: 1px solid var(--outline);
  border-radius: 8px;
  background: var(--surface);
  color: var(--ink);
  font-family: var(--font-mono, monospace);
  text-align: right;
}
.credit-split {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0;
}
.credit-split div {
  display: flex;
  justify-content: space-between;
}
.credit-split dt {
  color: var(--muted);
}
.credit-split dd {
  margin: 0;
  font-family: var(--font-mono, monospace);
  font-weight: 700;
}
</style>
