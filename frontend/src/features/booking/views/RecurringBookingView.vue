<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useRecurringBooking } from '../composables/useRecurringBooking'

import RecurringHero from '../components/recurring/RecurringHero.vue'
import RecurringWizardCard from '../components/recurring/RecurringWizardCard.vue'
import RecurringSeriesManager from '../components/recurring/RecurringSeriesManager.vue'
import RecurringInvoicesList from '../components/recurring/RecurringInvoicesList.vue'

const { t } = useI18n()

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
  previewLoading,
  estimatedSessionsCount,
  estimatedTotalAmount,
  partialConflict,
  dismissPartialConflict,
  toggleWeekday,
  loadData,
  showNotice,
  submitSeries,
} = useRecurringBooking()

async function handleSubmit(acceptPartial = false) {
  const outcome = await submitSeries(acceptPartial)
  switch (outcome.kind) {
    case 'confirmed':
      error.value = ''
      showNotice(t('recurring.accept.confirmedNotice'))
      break
    case 'pending':
      error.value = ''
      showNotice(t('recurring.accept.pendingNotice'))
      break
    case 'partial':
      // The partial-conflict panel renders from partialConflict; clear any stale error.
      error.value = ''
      break
    case 'drift':
      notice.value = ''
      error.value = t('recurring.accept.driftNotice')
      break
    case 'conflict':
    case 'error':
      error.value = outcome.message
      break
    case 'invalid':
      error.value = t(`recurring.accept.need.${outcome.reason}`)
      break
  }
}

const HCM = 'Asia/Ho_Chi_Minh'
function formatSession(startsAt: string, endsAt: string): string {
  const start = new Date(startsAt)
  const end = new Date(endsAt)
  const date = start.toLocaleDateString('vi-VN', { timeZone: HCM, day: '2-digit', month: '2-digit' })
  const timeOpts: Intl.DateTimeFormatOptions = { timeZone: HCM, hour: '2-digit', minute: '2-digit', hour12: false }
  return `${date} · ${start.toLocaleTimeString('vi-VN', timeOpts)} - ${end.toLocaleTimeString('vi-VN', timeOpts)}`
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

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
      <!-- Partial-conflict negotiation: keep the available sessions or change the schedule -->
      <div v-if="partialConflict" class="partial-conflict-card" data-testid="recurring-partial-conflict">
        <div class="partial-head">
          <span class="material-symbols-outlined">event_busy</span>
          <div>
            <h3>{{ t('recurring.accept.partialTitle') }}</h3>
            <p>{{ t('recurring.accept.partialHint') }}</p>
          </div>
        </div>
        <ul class="partial-excluded">
          <li v-for="ex in partialConflict.excluded" :key="ex.index">
            <span class="ex-when">{{ formatSession(ex.starts_at, ex.ends_at) }}</span>
            <span class="ex-reason">{{ ex.conflict_reason || t('recurring.accept.conflictGeneric') }}</span>
          </li>
        </ul>
        <div class="partial-summary">
          <span>{{ t('recurring.accept.heldCount', { count: partialConflict.held_count }) }}</span>
          <strong>{{ t('recurring.accept.revisedTotal') }}: {{ formatMoney(partialConflict.revised_total) }}</strong>
        </div>
        <div class="partial-actions">
          <AuraButton variant="outline" size="md" @click="dismissPartialConflict">
            {{ t('recurring.accept.reReview') }}
          </AuraButton>
          <AuraButton
            variant="primary"
            size="md"
            icon="playlist_add_check"
            :loading="submitting"
            :disabled="partialConflict.held_count === 0"
            data-testid="recurring-accept-remaining"
            @click="handleSubmit(true)"
          >
            {{ t('recurring.accept.acceptRemaining', { count: partialConflict.held_count }) }}
          </AuraButton>
        </div>
      </div>

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
          :submit-disabled="previewLoading || estimatedSessionsCount === 0 || !!partialConflict"
          @update:selected-court-id="selectedCourtId = $event"
          @update:frequency="frequency = $event"
          @update:start-time="startTime = $event"
          @update:end-time="endTime = $event"
          @update:start-date="startDate = $event"
          @update:end-date="endDate = $event"
          @toggle-weekday="toggleWeekday"
          @submit="handleSubmit(false)"
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
.partial-conflict-card {
  margin-bottom: 20px;
  padding: 18px 20px;
  border: 1px solid rgba(239, 108, 0, 0.4);
  border-radius: 16px;
  background: rgba(239, 108, 0, 0.06);
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.partial-head {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.partial-head > .material-symbols-outlined {
  color: #b45309;
}
.partial-head h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: var(--ink);
}
.partial-head p {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}
.partial-excluded {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.partial-excluded li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--surface, #fff);
  font-size: 12px;
}
.partial-excluded .ex-reason {
  color: #b45309;
  font-weight: 700;
  white-space: nowrap;
}
.partial-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  color: var(--ink);
}
.partial-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

</style>
