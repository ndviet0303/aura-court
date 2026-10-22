<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { usePlatformFeeSummary } from '../composables/usePlatformFeeSummary'

const { t } = useI18n()
const {
  month,
  collected,
  expiring,
  collectedLoading,
  collectedError,
  expiringLoading,
  expiringError,
} = usePlatformFeeSummary()
const [year, monthNumber] = month.from.split('-').map(Number)
</script>

<template>
  <section class="fee-summary" :aria-label="t('admin.platformFees.summary.title')">
    <article class="summary-card" data-testid="platform-fee-summary-collected">
      <span class="summary-label">
        {{ t('admin.platformFees.summary.collected', { month: monthNumber, year }) }}
      </span>
      <p v-if="collectedError" class="error" role="alert">{{ collectedError }}</p>
      <strong v-else class="summary-value" :aria-busy="collectedLoading">
        {{ collected === null ? '…' : formatCurrency(collected) }}
      </strong>
      <RouterLink
        class="summary-link"
        :to="{ path: '/admin/platform-fees', query: { tab: 'report' } }"
      >
        {{ t('admin.platformFees.summary.viewReport') }}
      </RouterLink>
    </article>

    <article class="summary-card" data-testid="platform-fee-summary-expiring">
      <span class="summary-label">{{ t('admin.platformFees.summary.expiring') }}</span>
      <p v-if="expiringError" class="error" role="alert">{{ expiringError }}</p>
      <strong v-else class="summary-value" :aria-busy="expiringLoading">
        {{ expiring === null ? '…' : t('admin.platformFees.summary.venues', { count: expiring }) }}
      </strong>
      <RouterLink
        class="summary-link"
        :to="{ path: '/admin/platform-fees', query: { tab: 'venues' } }"
      >
        {{ t('admin.platformFees.summary.viewVenues') }}
      </RouterLink>
    </article>
  </section>
</template>

<style scoped>
.fee-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.summary-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 18px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--card-bg);
}

.summary-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--muted);
}

.summary-value {
  font-size: 24px;
  font-weight: 800;
}

.summary-link {
  font-size: 13px;
  font-weight: 700;
  color: var(--primary);
}

.error {
  margin: 0;
  font-size: 13px;
  color: var(--error-text);
}
</style>
