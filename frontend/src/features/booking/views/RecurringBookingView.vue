<script setup lang="ts">
import { onMounted } from 'vue'
import { useRecurringBooking } from '../composables/useRecurringBooking'

import RecurringHero from '../components/recurring/RecurringHero.vue'
import RecurringWizardCard from '../components/recurring/RecurringWizardCard.vue'
import RecurringSeriesManager from '../components/recurring/RecurringSeriesManager.vue'
import RecurringInvoicesList from '../components/recurring/RecurringInvoicesList.vue'

const {
  courts,
  seriesList,
  allInvoices,
  activeTab,
  loading,
  submitting,
  notice,
  error,
  selectedCourtId,
  selectedCourt,
  frequency,
  selectedWeekdays,
  startTime,
  endTime,
  startDate,
  endDate,
  weekdayButtons,
  sessionDurationHours,
  hourlyRate,
  previewSessions,
  estimatedSessionsCount,
  estimatedTotalAmount,
  toggleWeekday,
  loadData,
  submitSeries,
} = useRecurringBooking()

onMounted(() => {
  loadData()
})
</script>

<template>
  <main class="recurring-booking-view-page">
    <!-- Header Hero Banner & Tab Switcher -->
    <RecurringHero
      :active-tab="activeTab"
      :series-count="seriesList.length"
      :invoices-count="allInvoices.length"
      :loading="loading"
      @update:active-tab="activeTab = $event"
      @refresh="loadData"
    />

    <!-- Feedback Alerts -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill-success">
        <span class="material-symbols-outlined alert-icon">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="alert-pill-error">
        <span class="material-symbols-outlined alert-icon">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Tab 1: Create New Series & Quick Overview -->
    <section v-if="activeTab === 'create'" class="tab-pane">
      <div class="recurring-content-grid">
        <RecurringWizardCard
          :courts="courts"
          :selected-court-id="selectedCourtId"
          :selected-court="selectedCourt"
          :frequency="frequency"
          :selected-weekdays="selectedWeekdays"
          :start-time="startTime"
          :end-time="endTime"
          :start-date="startDate"
          :end-date="endDate"
          :weekday-buttons="weekdayButtons"
          :session-duration-hours="sessionDurationHours"
          :hourly-rate="hourlyRate"
          :preview-sessions="previewSessions"
          :estimated-sessions-count="estimatedSessionsCount"
          :estimated-total-amount="estimatedTotalAmount"
          :submitting="submitting"
          @update:selected-court-id="selectedCourtId = $event"
          @update:frequency="frequency = $event"
          @update:start-time="startTime = $event"
          @update:end-time="endTime = $event"
          @update:start-date="startDate = $event"
          @update:end-date="endDate = $event"
          @toggle-weekday="toggleWeekday"
          @submit="submitSeries"
        />

        <RecurringSeriesManager
          :series-list="seriesList"
          :loading="loading"
          @create-new="activeTab = 'create'"
        />
      </div>
    </section>

    <!-- Tab 2: Full Contracts & Series List -->
    <section v-else-if="activeTab === 'series'" class="tab-pane">
      <RecurringSeriesManager
        :series-list="seriesList"
        :loading="loading"
        @create-new="activeTab = 'create'"
      />
    </section>

    <!-- Tab 3: Invoices & Billing Cycles Overview -->
    <section v-else-if="activeTab === 'invoices'" class="tab-pane">
      <RecurringInvoicesList
        :invoices="allInvoices"
        :loading="loading"
      />
    </section>
  </main>
</template>

<style scoped>
.recurring-booking-view-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.alert-pill-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid #10b981;
  color: #10b981;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 10px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid #ef4444;
  color: #ef4444;
  font-size: 13px;
  font-weight: 600;
}

.alert-icon {
  font-size: 18px;
}

.tab-pane {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.recurring-content-grid {
  display: grid;
  grid-template-columns: 1fr 1.15fr;
  gap: 24px;
  align-items: start;
}

@media (max-width: 1024px) {
  .recurring-content-grid {
    grid-template-columns: 1fr;
  }
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
