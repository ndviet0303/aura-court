<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { TransferListing } from '../types/transfer'
import TransferBookingLine from './TransferBookingLine.vue'

// UC-9.3: a listing on the market with its first open booking, how many are open and the lowest price.
const props = defineProps<{ listing: TransferListing }>()
const { t } = useI18n()

const open = computed(() => props.listing.items.filter((item) => item.state === 'available'))
const first = computed(() => open.value[0] ?? props.listing.items[0])
const lowest = computed(() => Math.min(...open.value.map((item) => item.price)))
</script>

<template>
  <RouterLink
    :to="{ name: 'resale-listing', params: { id: listing.id } }"
    class="listing-card"
    :data-testid="`listing-${listing.id}`"
  >
    <TransferBookingLine
      v-if="first"
      :venue="first.venue"
      :court="first.court"
      :starts-at="first.starts_at"
      :ends-at="first.ends_at"
    />
    <p v-if="listing.note" class="listing-note">{{ listing.note }}</p>
    <div class="listing-meta">
      <span>{{ t('resale.seller') }}: {{ listing.seller.name }}</span>
      <span class="chip">{{ t('resale.availableCount', { count: open.length }) }}</span>
      <strong v-if="open.length">{{
        t('resale.priceFrom', { amount: formatCurrency(lowest) })
      }}</strong>
      <span v-if="listing.is_mine" class="chip mine">{{ t('resale.mine') }}</span>
    </div>
  </RouterLink>
</template>

<style scoped>
.listing-card {
  display: grid;
  gap: 10px;
  padding: 14px;
  border: 1px solid var(--outline);
  border-radius: 14px;
  background: var(--surface);
  color: var(--ink);
  text-decoration: none;
}
.listing-card:hover,
.listing-card:focus-visible {
  border-color: var(--primary);
}
.listing-note {
  display: -webkit-box;
  margin: 0;
  overflow: hidden;
  color: var(--muted);
  font-size: 13px;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow-wrap: anywhere;
}
.listing-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 10px;
  align-items: center;
  font-size: 13px;
}
.chip {
  padding: 2px 8px;
  border-radius: 999px;
  background: color-mix(in srgb, #16a34a 14%, transparent);
  color: #16a34a;
  font-size: 12px;
}
.chip.mine {
  background: color-mix(in srgb, var(--primary) 14%, transparent);
  color: var(--primary);
}
</style>
