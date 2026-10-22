<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, DatePicker, SelectDropdown } from '@/shared/components'
import AttendanceCommandBar from '../components/AttendanceCommandBar.vue'
import BookingDetailPanel from '../components/BookingDetailPanel.vue'
import InUseTable from '../components/InUseTable.vue'
import OperationsDayGrid from '../components/OperationsDayGrid.vue'
import { useAttendanceDesk } from '../composables/useAttendanceDesk'
import { useOperationsBoard } from '../composables/useOperationsBoard'

// Module 06: the owner's and assigned staff's on-site screen for one venue and day.
const { t } = useI18n()
const { venues, venueId, date, day, inUse, loading, error, organizationId, load } =
  useOperationsBoard()
const desk = useAttendanceDesk(organizationId, () => load(true))
const { owner, selected, payment, history, busy, notice, failure } = desk

const venueOptions = computed(() =>
  venues.value.map((v) => ({ value: v.id, label: resolveLocalizedText(v.name) })),
)
</script>

<template>
  <div class="operations-page">
    <header class="operations-header">
      <div>
        <h1 class="operations-title">{{ t('operations.title') }}</h1>
        <p class="muted">{{ t('operations.subtitle') }}</p>
      </div>
      <div class="operations-filters">
        <SelectDropdown
          :model-value="venueId"
          :options="venueOptions"
          :placeholder="t('operations.chooseVenue')"
          variant="bordered"
          :aria-label="t('operations.chooseVenue')"
          @update:model-value="venueId = Number($event)"
        />
        <DatePicker v-model="date" />
        <AuraButton variant="outline" size="sm" :loading="loading" @click="load()">
          {{ t('common.refresh') }}
        </AuraButton>
      </div>
    </header>

    <p v-if="notice" class="operations-notice" role="status">{{ notice }}</p>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <p v-if="failure && !selected" class="form-error" role="alert">{{ failure }}</p>
    <p v-if="!venues.length && !loading && !error" class="muted">{{ t('operations.noVenues') }}</p>

    <template v-if="venueId">
      <section class="operations-card">
        <AttendanceCommandBar :busy="busy" @run="desk.run" />
      </section>

      <section class="operations-card">
        <h2 class="section-title">{{ t('operations.inUse.title') }}</h2>
        <InUseTable :reservations="inUse" @select="desk.open" />
      </section>

      <section class="operations-card">
        <h2 class="section-title">{{ t('operations.grid.title') }}</h2>
        <p v-if="loading && !day" class="muted">{{ t('common.loading') }}</p>
        <OperationsDayGrid
          v-else-if="day"
          :day="day"
          :selected-code="selected?.code"
          @select="desk.open"
        />
      </section>
    </template>

    <BookingDetailPanel
      v-if="selected"
      :reservation="selected"
      :payment="payment"
      :history="history"
      :owner="owner"
      :busy="busy"
      :error="failure"
      @close="desk.close"
      @command="(command) => desk.run(command, selected!.code)"
      @adjust="desk.adjust"
    />
  </div>
</template>

<style scoped>
.operations-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  /* auto margins stop the flex stretch; without a width the 24-hour grid widens the page */
  width: 100%;
  max-width: 1280px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.operations-header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.operations-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.operations-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.muted {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 13px;
}

.operations-card {
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.section-title {
  margin: 0 0 12px;
  font-size: 16px;
  font-weight: 700;
}

.operations-notice {
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.12);
  color: #047857;
  font-size: 13px;
  font-weight: 600;
}
</style>
