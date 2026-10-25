<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { VenueCreditEntry } from '@/core/types/venueCredit'
import { useVenueCredits } from '../composables/useVenueCredits'
import VenueCreditCashOutPanel from './VenueCreditCashOutPanel.vue'

const { t, d } = useI18n()
const { accounts, loading, error, load } = useVenueCredits()
const cashOutOrgId = ref<number | null>(null)

/** A reserve/release moves credit between spendable and held, so the spendable change is what the row shows. */
const signed = (entry: VenueCreditEntry) =>
  entry.balance_delta === 0
    ? formatCurrency(entry.amount)
    : `${entry.balance_delta > 0 ? '+' : '−'}${formatCurrency(Math.abs(entry.balance_delta))}`
</script>

<template>
  <div class="profile-tab-card" data-testid="profile-venue-credit">
    <div class="title-with-icon">
      <span class="material-symbols-outlined title-icon">account_balance_wallet</span>
      <div>
        <h2 class="section-heading">{{ t('profile.credit.title') }}</h2>
        <p class="section-sub">{{ t('profile.credit.subtitle') }}</p>
      </div>
    </div>

    <p v-if="loading" class="section-sub">{{ t('profile.credit.loading') }}</p>
    <div v-else-if="error" class="error-row" role="alert">
      <span>{{ error }}</span>
      <AuraButton variant="secondary" size="sm" @click="load">
        {{ t('profile.credit.retry') }}
      </AuraButton>
    </div>
    <p v-else-if="!accounts?.length" class="section-sub" data-testid="venue-credit-empty">
      {{ t('profile.credit.empty') }}
    </p>

    <article
      v-for="account in accounts ?? []"
      :key="account.organization.id"
      class="credit-account"
      data-testid="venue-credit-account"
    >
      <header class="account-head">
        <div>
          <strong class="org-name">{{ account.organization.name }}</strong>
          <span v-if="account.reserved > 0" class="reserved">
            {{ t('profile.credit.reserved', { amount: formatCurrency(account.reserved) }) }}
          </span>
        </div>
        <div class="balance-col">
          <strong class="balance font-mono" data-testid="venue-credit-balance">
            {{ formatCurrency(account.balance) }}
          </strong>
          <AuraButton
            v-if="cashOutOrgId !== account.organization.id"
            variant="secondary"
            size="sm"
            icon="payments"
            :disabled="account.balance < 1"
            data-testid="venue-credit-cash-out-btn"
            @click="cashOutOrgId = account.organization.id"
          >
            {{ t('profile.credit.cashOutBtn') }}
          </AuraButton>
        </div>
      </header>

      <VenueCreditCashOutPanel
        v-if="cashOutOrgId === account.organization.id"
        :account="account"
        @close="cashOutOrgId = null"
      />

      <ul class="entries">
        <li v-for="entry in account.entries" :key="entry.id" class="entry">
          <div>
            <span class="entry-type">{{ t(`profile.credit.types.${entry.type}`) }}</span>
            <span class="entry-meta">
              {{ entry.created_at ? d(new Date(entry.created_at), 'datetime') : '' }}
              <template v-if="entry.reference"> · {{ entry.reference }}</template>
            </span>
          </div>
          <div class="entry-amount">
            <strong class="font-mono" :class="{ plus: entry.balance_delta > 0 }">
              {{ signed(entry) }}
            </strong>
            <span class="entry-meta">
              {{
                t('profile.credit.balanceAfter', { amount: formatCurrency(entry.balance_after) })
              }}
            </span>
          </div>
        </li>
      </ul>
    </article>
  </div>
</template>

<style scoped>
.profile-tab-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px;
  border-radius: 24px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface, #ffffff);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.title-with-icon {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.title-icon {
  font-size: 26px;
  color: var(--primary, #6b2d3f);
}

.section-heading {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.section-sub {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.error-row {
  display: flex;
  gap: 12px;
  align-items: center;
  color: #c62828;
  font-size: 13px;
}

.credit-account {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border-radius: 16px;
  border: 1px solid var(--outline);
}

.account-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.org-name {
  display: block;
  font-size: 15px;
  color: var(--ink);
}

.reserved,
.entry-meta {
  display: block;
  font-size: 12px;
  color: var(--muted);
}

.balance-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}

.balance {
  font-size: 22px;
  color: var(--primary, #6b2d3f);
}

.entries {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}

.entry {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
  border-top: 1px solid var(--outline);
  font-size: 13px;
}

.entry-type {
  color: var(--ink);
  font-weight: 600;
}

.entry-amount {
  text-align: right;
}

.plus {
  color: #20733c;
}
</style>
