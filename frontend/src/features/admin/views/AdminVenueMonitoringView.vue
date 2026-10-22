<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import PlatformFeePager from '@/features/platform-fees/components/PlatformFeePager.vue'
import { useVenueMonitoring } from '../composables/useVenueMonitoring'
import { useSuspendVenue } from '../composables/useSuspendVenue'
import MonitoringVenueFilters from '../components/monitoring/MonitoringVenueFilters.vue'
import MonitoredVenuesTable from '../components/monitoring/MonitoredVenuesTable.vue'
import SuspendVenueDialog from '../components/suspension/SuspendVenueDialog.vue'

// Task 5.10: read-only list of every organization's venues for the platform admin (D13).
const { t } = useI18n()
const { filters, setFilter, organizations, venues, page, lastPage, loading, error, load } =
  useVenueMonitoring()
const notice = ref('')
const suspension = useSuspendVenue(async (message) => {
  notice.value = message
  await load(page.value)
})
</script>

<template>
  <div class="admin-detail-page">
    <header class="admin-detail-header">
      <div>
        <h1 class="admin-detail-title">{{ t('admin.monitoring.title') }}</h1>
        <p class="admin-detail-muted">{{ t('admin.monitoring.subtitle') }}</p>
      </div>
      <AuraButton variant="outline" size="sm" :loading="loading" @click="load(page)">
        {{ t('common.refresh') }}
      </AuraButton>
    </header>

    <section class="admin-detail-card">
      <MonitoringVenueFilters
        :filters="filters"
        :organizations="organizations"
        @change="setFilter"
      />
    </section>

    <p v-if="notice" class="admin-detail-notice" role="status">{{ notice }}</p>
    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>

    <section class="admin-detail-card">
      <p v-if="loading && !venues.length" class="admin-detail-muted">{{ t('common.loading') }}</p>
      <p v-else-if="!venues.length && !error" class="admin-detail-muted">
        {{ t('admin.monitoring.empty') }}
      </p>
      <MonitoredVenuesTable
        v-else-if="venues.length"
        :venues="venues"
        @suspend="(venue) => suspension.open(venue.id)"
      />
    </section>

    <PlatformFeePager
      :page="page"
      :last-page="lastPage"
      :loading="loading"
      :label="t('admin.monitoring.title')"
      @go="load"
    />
    <SuspendVenueDialog :state="suspension" />
  </div>
</template>
