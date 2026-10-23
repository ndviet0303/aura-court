<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { venueClockRange, venueDate } from '@/core/utils/venueTime'
import { AuraButton } from '@/shared/components'
import type { VenueCustomerProfile } from '../types/customer'
import CustomerTierBadge from './CustomerTierBadge.vue'

/** UC-7.2: contact, quarter figures and booking history; UC-7.3 gifts go through the venue chat (owner only). */
defineProps<{ profile: VenueCustomerProfile; owner: boolean }>()
const emit = defineEmits<{ close: []; message: []; gift: [] }>()
const { t } = useI18n()
</script>

<template>
  <aside class="profile" :aria-label="profile.customer.name">
    <header class="profile-head">
      <div>
        <h2 class="profile-name">{{ profile.customer.name }}</h2>
        <p class="muted">
          {{ [profile.customer.phone, profile.customer.email].filter(Boolean).join(' · ') }}
        </p>
      </div>
      <button type="button" class="close" :aria-label="t('common.close')" @click="emit('close')">
        <span class="material-symbols-outlined">close</span>
      </button>
    </header>

    <dl class="figures">
      <div>
        <dt>{{ t('customers.tier') }} ({{ profile.quarter }})</dt>
        <dd><CustomerTierBadge :tier="profile.customer.tier" /></dd>
      </div>
      <div>
        <dt>{{ t('customers.usageRate') }}</dt>
        <dd>
          {{
            profile.customer.usage_rate === null
              ? t('customers.noData')
              : `${profile.customer.usage_rate}%`
          }}
        </dd>
      </div>
      <div>
        <dt>{{ t('customers.completed') }} / {{ t('customers.noShow') }}</dt>
        <dd>{{ profile.customer.completed }} / {{ profile.customer.no_show }}</dd>
      </div>
      <div>
        <dt>{{ t('customers.amount') }}</dt>
        <dd>{{ formatCurrency(profile.customer.completed_amount) }}</dd>
      </div>
    </dl>

    <div class="actions">
      <AuraButton size="sm" @click="emit('message')">{{ t('customers.message') }}</AuraButton>
      <AuraButton v-if="owner" size="sm" variant="outline" @click="emit('gift')">
        {{ t('customers.gift') }}
      </AuraButton>
    </div>

    <h3 class="section-title">{{ t('customers.history') }}</h3>
    <ul class="history">
      <li v-for="row in profile.history" :key="row.id">
        <span
          >{{ venueDate(row.starts_at) }} · {{ venueClockRange(row.starts_at, row.ends_at) }}</span
        >
        <span>{{ row.court.code }}</span>
        <span class="status">{{ t(`operations.status.${row.operational_status}`) }}</span>
      </li>
    </ul>
  </aside>
</template>

<style scoped>
.profile {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.profile-head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}

.profile-name {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
}

.close {
  border: 0;
  background: none;
  color: var(--muted);
  cursor: pointer;
}

.figures {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}

.figures dt {
  color: var(--muted);
  font-size: 12px;
}

.figures dd {
  margin: 2px 0 0;
  font-weight: 700;
}

.actions {
  display: flex;
  gap: 8px;
}

.section-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}

.history {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
}

.history li {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding-bottom: 6px;
  border-bottom: 1px solid var(--outline);
}

.status {
  font-weight: 600;
}

.muted {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
