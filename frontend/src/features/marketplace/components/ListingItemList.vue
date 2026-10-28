<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { ListingItem } from '../types/transfer'
import TransferBookingLine from './TransferBookingLine.vue'

// FR-141: every booking of a listing with its price and state; available ones can be picked.
defineProps<{
  items: ListingItem[]
  selected: number[]
  selectable: boolean
  refusals: Record<number, string>
}>()
const emit = defineEmits<{ toggle: [reservationId: number] }>()
const { t } = useI18n()
</script>

<template>
  <ul class="item-list">
    <li
      v-for="item in items"
      :key="item.reservation_id"
      class="item"
      :class="`state-${item.state}`"
      :data-testid="`listing-item-${item.reservation_id}`"
    >
      <label class="item-pick">
        <input
          v-if="selectable"
          type="checkbox"
          :checked="selected.includes(item.reservation_id)"
          :disabled="item.state !== 'available'"
          :aria-label="t('resale.pickItem')"
          @change="emit('toggle', item.reservation_id)"
        />
        <TransferBookingLine
          :venue="item.venue"
          :court="item.court"
          :starts-at="item.starts_at"
          :ends-at="item.ends_at"
        />
      </label>
      <div class="item-side">
        <span class="state">{{ t(`resale.state.${item.state}`) }}</span>
        <strong>{{ formatCurrency(item.price) }}</strong>
        <span class="muted">{{
          t('resale.paid', { amount: formatCurrency(item.paid_amount) })
        }}</span>
      </div>
      <p v-if="refusals[item.reservation_id]" class="refusal" role="alert">
        {{ refusals[item.reservation_id] }}
      </p>
    </li>
  </ul>
</template>

<style scoped>
.item-list {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.item {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background: var(--surface);
}
.item-pick {
  display: flex;
  flex: 1 1 240px;
  gap: 10px;
  align-items: center;
  min-width: 0;
}
.item-side {
  display: grid;
  justify-items: end;
  gap: 2px;
  font-size: 13px;
}
.state {
  padding: 2px 8px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--muted) 14%, transparent);
  color: var(--muted);
  font-size: 12px;
}
.state-available .state {
  background: color-mix(in srgb, #16a34a 14%, transparent);
  color: #16a34a;
}
.state-held .state {
  background: color-mix(in srgb, #d97706 14%, transparent);
  color: #d97706;
}
.state-transferred .state {
  background: color-mix(in srgb, var(--primary) 14%, transparent);
  color: var(--primary);
}
.state-withdrawn,
.state-expired {
  opacity: 0.7;
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
