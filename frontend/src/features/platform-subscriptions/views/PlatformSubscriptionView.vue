<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useVenuePackages } from '../composables/useVenuePackages'
import type { VenuePackageOverview } from '../types'
import VenuePackageCard from '../components/VenuePackageCard.vue'
import PackagePaymentDialog from '../components/PackagePaymentDialog.vue'
import TerminatePackageDialog from '../components/TerminatePackageDialog.vue'
import PackagePriceTable from '@/features/platform-fees/components/PackagePriceTable.vue'

const { t } = useI18n()
const {
  venues,
  terms,
  loading,
  error,
  payment,
  busy,
  notice,
  actionError,
  load,
  requestPayment,
  openPayment,
  closePayment,
  terminate,
} = useVenuePackages()

const terminating = ref<VenuePackageOverview | null>(null)

async function confirmTerminate() {
  const venueId = terminating.value?.venue_id
  terminating.value = null
  if (venueId !== undefined) await terminate(venueId)
}
</script>

<template>
  <div class="packages-view">
    <header class="page-head">
      <div class="title-with-icon">
        <span class="material-symbols-outlined header-icon" aria-hidden="true">
          workspace_premium
        </span>
        <div>
          <h1 class="page-title">{{ t('admin.packages.title') }}</h1>
          <p class="subtitle">{{ t('admin.packages.subtitle') }}</p>
        </div>
      </div>
      <AuraButton
        variant="secondary"
        icon="refresh"
        :loading="loading"
        data-testid="packages-refresh"
        @click="load"
      >
        {{ t('common.refresh') }}
      </AuraButton>
    </header>

    <p v-if="notice" class="notice" role="status">{{ notice }}</p>
    <p v-if="actionError || error" class="error" role="alert">{{ actionError || error }}</p>

    <p v-if="loading && !venues.length" class="muted">{{ t('common.loading') }}</p>
    <p v-else-if="!venues.length && !error" class="muted" data-testid="packages-empty">
      {{ t('admin.packages.empty') }}
    </p>

    <PackagePriceTable v-if="terms.length" :terms="terms" />

    <VenuePackageCard
      v-for="venue in venues"
      :key="venue.venue_id"
      :venue="venue"
      :terms="terms"
      :busy="busy"
      @request="(termDays, amount) => requestPayment(venue.venue_id, termDays, amount)"
      @open-payment="openPayment"
      @terminate="terminating = venue"
    />

    <PackagePaymentDialog
      :request="payment"
      :busy="busy"
      :error="actionError"
      @refresh="openPayment"
      @close="closePayment"
    />
    <TerminatePackageDialog
      :venue="terminating"
      :busy="busy"
      @close="terminating = null"
      @confirm="confirmTerminate"
    />
  </div>
</template>

<style scoped>
.packages-view {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1080px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.page-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--primary);
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  margin: 0;
}

.subtitle {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.notice,
.error,
.muted {
  margin: 0;
}

.error {
  color: var(--error-text);
}
</style>
