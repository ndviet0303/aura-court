<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { CurrencyInput } from '@/shared/components'
import type { TransferCandidate } from '../types/transfer'
import type { PriceProblem } from '../utils/transfer'
import TransferBookingLine from './TransferBookingLine.vue'

// FR-139, FR-140: each upcoming booking, why it cannot be listed, and the price of each chosen one.
defineProps<{
  candidates: TransferCandidate[]
  selected: number[]
  prices: Record<number, number | null>
  problems: Record<number, PriceProblem>
  refusals: Record<number, string>
  disabled: boolean
}>()
const emit = defineEmits<{
  select: [reservationId: number, on: boolean]
  price: [reservationId: number, price: number]
}>()
const { t } = useI18n()
</script>

<template>
  <ul class="candidates">
    <li
      v-for="c in candidates"
      :key="c.reservation_id"
      class="candidate"
      :class="{ ineligible: !c.eligible, refused: refusals[c.reservation_id] }"
      :data-testid="`candidate-${c.reservation_id}`"
    >
      <label class="candidate-pick">
        <input
          type="checkbox"
          :checked="selected.includes(c.reservation_id)"
          :disabled="disabled || !c.eligible"
          :aria-label="t('resale.pickItem')"
          @change="emit('select', c.reservation_id, ($event.target as HTMLInputElement).checked)"
        />
        <TransferBookingLine
          :venue="c.venue"
          :court="c.court"
          :starts-at="c.starts_at"
          :ends-at="c.ends_at"
        />
      </label>
      <div class="candidate-side">
        <span class="muted">#{{ c.code }}</span>
        <span>{{ t('resale.paid', { amount: formatCurrency(c.paid_amount) }) }}</span>
      </div>
      <p v-if="c.refusal" class="refusal">{{ c.refusal.message }}</p>
      <label v-if="selected.includes(c.reservation_id)" class="candidate-price">
        <span>{{ t('resale.form.priceLabel') }}</span>
        <CurrencyInput
          :model-value="prices[c.reservation_id]"
          :max="c.max_price"
          :disabled="disabled"
          size="sm"
          @update:model-value="emit('price', c.reservation_id, $event)"
        />
        <span class="muted">{{
          t('resale.form.maxPrice', { amount: formatCurrency(c.max_price) })
        }}</span>
      </label>
      <p v-if="problems[c.reservation_id]" class="refusal">
        {{
          t(`resale.priceError.${problems[c.reservation_id]}`, {
            amount: formatCurrency(c.max_price),
          })
        }}
      </p>
      <p v-if="refusals[c.reservation_id]" class="refusal" role="alert">
        {{ refusals[c.reservation_id] }}
      </p>
    </li>
  </ul>
</template>

<style scoped>
.candidates {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.candidate {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}
.candidate.ineligible {
  opacity: 0.75;
}
.candidate.refused {
  border-color: #dc2626;
}
.candidate-pick {
  display: flex;
  flex: 1 1 240px;
  gap: 10px;
  align-items: center;
  min-width: 0;
}
.candidate-side {
  display: grid;
  justify-items: end;
  gap: 2px;
}
.candidate-price {
  display: flex;
  flex-basis: 100%;
  flex-wrap: wrap;
  gap: 6px 10px;
  align-items: center;
}
.muted {
  color: var(--muted);
  font-size: 12px;
}
.refusal {
  flex-basis: 100%;
  margin: 0;
  color: #dc2626;
  font-size: 12px;
}
</style>
