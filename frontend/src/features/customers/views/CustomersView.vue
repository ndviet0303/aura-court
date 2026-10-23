<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'
import { useOwnerCheck } from '@/features/admin/composables/useLifecycleCommands'
import { useStartChat } from '@/features/chat/composables/useStartChat'
import CustomerProfilePanel from '../components/CustomerProfilePanel.vue'
import CustomerTable from '../components/CustomerTable.vue'
import { useVenueCustomers } from '../composables/useVenueCustomers'

// Module 07 (UC-7.1–UC-7.3): the venue's customers by quarter, a profile, and a chat or gift from it.
const { t } = useI18n()
const c = useVenueCustomers()
const { quarters, venues, venueId, quarter, search, page, lastPage, customers, profile } = c
const isOwnerOf = useOwnerCheck()
const startChat = useStartChat()
const chatError = ref('')

const venueOptions = computed(() =>
  venues.value.map((v) => ({ value: v.id, label: resolveLocalizedText(v.name) })),
)
const quarterOptions = quarters.map((q) => ({ value: q, label: q.replace('-', ' · ') }))

async function chat(gift: boolean) {
  if (!venueId.value || !profile.value) return
  const payload = {
    type: 'customer_venue' as const,
    venue_id: venueId.value,
    user_id: profile.value.customer.id,
  }
  chatError.value = (await startChat(payload, gift)) ?? ''
}
</script>

<template>
  <div class="customers-page">
    <header class="customers-header">
      <div>
        <h1 class="customers-title">{{ t('customers.title') }}</h1>
        <p class="muted">{{ t('customers.subtitle') }}</p>
      </div>
      <div class="customers-filters">
        <SelectDropdown
          :model-value="venueId"
          :options="venueOptions"
          :placeholder="t('operations.chooseVenue')"
          variant="bordered"
          :aria-label="t('operations.chooseVenue')"
          @update:model-value="venueId = Number($event)"
        />
        <SelectDropdown
          :model-value="quarter"
          :options="quarterOptions"
          variant="bordered"
          :aria-label="t('customers.quarter')"
          @update:model-value="quarter = String($event)"
        />
        <form class="search" @submit.prevent="c.find()">
          <input
            v-model="search"
            type="search"
            class="search-input"
            :placeholder="t('customers.search')"
            :aria-label="t('customers.search')"
          />
          <AuraButton type="submit" variant="outline" size="sm" :loading="c.loading.value">
            {{ t('common.search') }}
          </AuraButton>
        </form>
      </div>
    </header>

    <p v-if="c.error.value" class="form-error" role="alert">{{ c.error.value }}</p>
    <p v-if="chatError" class="form-error" role="alert">{{ chatError }}</p>
    <p v-if="!venues.length && !c.loading.value && !c.error.value" class="muted">
      {{ t('operations.noVenues') }}
    </p>

    <div v-if="venueId" class="customers-body">
      <section class="customers-card">
        <CustomerTable :customers="customers" @select="c.open" />
        <nav v-if="lastPage > 1" class="pager" :aria-label="t('customers.pages')">
          <AuraButton size="sm" variant="outline" :disabled="page <= 1" @click="page--"
            >‹</AuraButton
          >
          <span>{{ page }} / {{ lastPage }}</span>
          <AuraButton size="sm" variant="outline" :disabled="page >= lastPage" @click="page++">
            ›
          </AuraButton>
        </nav>
      </section>
      <CustomerProfilePanel
        v-if="profile"
        :profile="profile"
        :owner="isOwnerOf(c.organizationId.value)"
        @close="c.close"
        @message="chat(false)"
        @gift="chat(true)"
      />
    </div>
  </div>
</template>

<style scoped>
.customers-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 1280px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.customers-header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.customers-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.customers-filters,
.search,
.pager {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.search-input {
  min-width: 200px;
  padding: 8px 12px;
  border: 1px solid var(--outline);
  border-radius: 10px;
  background: var(--surface);
  color: var(--ink);
}

.customers-body {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 16px;
}

@media (min-width: 1024px) {
  .customers-body:has(aside) {
    grid-template-columns: minmax(0, 1fr) 340px;
  }
}

.customers-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.pager {
  justify-content: flex-end;
}

.muted {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
