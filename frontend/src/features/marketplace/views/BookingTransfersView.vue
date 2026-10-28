<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency, formatDateTime } from '@/i18n'
import { useBookingTransfers } from '../composables/useBookingTransfers'

// UC-9.8: my transactions, as seller or buyer.
const { t } = useI18n()
const { transfers, loading, error } = useBookingTransfers()
</script>

<template>
  <main class="resale-page">
    <RouterLink :to="{ name: 'resale-marketplace' }" class="back"
      >← {{ t('resale.back') }}</RouterLink
    >
    <header>
      <h1>{{ t('resale.transfers.title') }}</h1>
      <p class="muted">{{ t('resale.transfers.subtitle') }}</p>
    </header>
    <p v-if="loading" class="muted">{{ t('common.loading') }}</p>
    <p v-else-if="!transfers.length && !error" class="muted">{{ t('resale.transfers.empty') }}</p>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>

    <ul class="transfer-list">
      <li v-for="tr in transfers" :key="tr.id">
        <RouterLink :to="{ name: 'resale-transfer', params: { id: tr.id } }" class="transfer-row">
          <strong>
            {{
              tr.is_seller
                ? t('resale.transfers.asSeller', { name: tr.buyer.name })
                : t('resale.transfers.asBuyer', { name: tr.seller.name })
            }}
          </strong>
          <span class="status" :class="tr.status">{{
            t(`resale.transfers.status.${tr.status}`)
          }}</span>
          <span class="muted">
            {{ t('resale.itemCount', { count: tr.items.length }) }} ·
            {{ formatCurrency(tr.total) }} · {{ formatDateTime(tr.created_at) }}
          </span>
        </RouterLink>
      </li>
    </ul>
  </main>
</template>

<style scoped>
.resale-page {
  display: grid;
  gap: 14px;
  max-width: 880px;
  margin: 0 auto;
  padding: 24px 16px 48px;
  color: var(--ink);
}
.back {
  color: var(--muted);
  font-size: 13px;
}
h1 {
  margin: 0 0 4px;
  font-size: 22px;
}
.transfer-list {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.transfer-row {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  align-items: center;
  padding: 12px 14px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background: var(--surface);
  color: var(--ink);
  text-decoration: none;
}
.transfer-row:hover {
  border-color: var(--primary);
}
.status {
  padding: 2px 8px;
  border-radius: 999px;
  background: color-mix(in srgb, #d97706 14%, transparent);
  color: #d97706;
  font-size: 12px;
}
.status.completed {
  background: color-mix(in srgb, #16a34a 14%, transparent);
  color: #16a34a;
}
.status.cancelled {
  background: color-mix(in srgb, var(--muted) 14%, transparent);
  color: var(--muted);
}
.muted {
  flex-basis: 100%;
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
