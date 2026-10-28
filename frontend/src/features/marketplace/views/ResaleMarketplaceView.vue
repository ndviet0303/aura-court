<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import TransferListingCard from '../components/TransferListingCard.vue'
import { useTransferListings } from '../composables/useTransferListings'

// Module 08.2 (UC-9.3, UC-9.4): the shared booking transfer market; customers settle and pay each other.
const { t } = useI18n()
const { q, items, loading, error, hasMore, loadMore } = useTransferListings()
</script>

<template>
  <main class="resale-page">
    <header class="resale-head">
      <div>
        <p class="resale-eyebrow">{{ t('resale.eyebrow') }}</p>
        <h1>{{ t('resale.title') }}</h1>
        <p class="muted">{{ t('resale.subtitle') }}</p>
      </div>
      <div class="head-actions">
        <AuraButton :to="{ name: 'resale-transfers' }" variant="outline" size="sm">
          {{ t('resale.myTransfers') }}
        </AuraButton>
        <AuraButton :to="{ name: 'resale-new' }" size="sm">{{ t('resale.newListing') }}</AuraButton>
      </div>
    </header>

    <input
      v-model="q"
      type="search"
      class="form-input"
      maxlength="100"
      :placeholder="t('resale.searchPlaceholder')"
      :aria-label="t('resale.searchPlaceholder')"
      data-testid="resale-search"
    />
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>

    <div class="listing-grid">
      <TransferListingCard v-for="listing in items" :key="listing.id" :listing="listing" />
    </div>
    <p v-if="!loading && !error && !items.length" class="muted">{{ t('resale.empty') }}</p>
    <AuraButton v-if="hasMore" variant="outline" :loading="loading" @click="loadMore">
      {{ t('resale.loadMore') }}
    </AuraButton>
  </main>
</template>

<style scoped>
.resale-page {
  display: grid;
  gap: 16px;
  max-width: 1120px;
  margin: 0 auto;
  padding: 24px 16px 48px;
  color: var(--ink);
}
.resale-head {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-end;
  justify-content: space-between;
}
.resale-head h1 {
  margin: 4px 0;
  font-size: 24px;
}
.resale-eyebrow {
  margin: 0;
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
}
.head-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.listing-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr));
  gap: 12px;
}
.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
