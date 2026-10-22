<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useCourtSchedule } from '../composables/useCourtSchedule'

import ScheduleDatePicker from '../components/schedule/ScheduleDatePicker.vue'
import ScheduleCourtSelector from '../components/schedule/ScheduleCourtSelector.vue'
import ScheduleSlotsGrid from '../components/schedule/ScheduleSlotsGrid.vue'
import ScheduleBookingSummary from '../components/schedule/ScheduleBookingSummary.vue'

const {
  store,
  date,
  selectedCourtId,
  selectedVenueId,
  timeFilter,
  court,
  directOwnerPayments,
  venuesList,
  filteredCourts,
  filteredSlots,
  carouselDays,
  error,
  notice,
  loading,
  checkoutLoading,
  resendingEmail,
  selectDate,
  goToToday,
  goToPrevDay,
  goToNextDay,
  selectCourt,
  selectVenue,
  toggleSlot,
  removeSlot,
  clearSelectedSlots,
  quickSelectAvailableHours,
  proceedToBooking,
  resendVerificationEmail,
} = useCourtSchedule()
</script>

<template>
  <div class="schedule-view-page">
    <!-- Breadcrumbs -->
    <nav class="schedule-breadcrumbs" aria-label="Breadcrumb">
      <RouterLink to="/" class="bc-link">{{ $t('schedule.breadcrumbHome') }}</RouterLink>
      <span class="material-symbols-outlined bc-sep">chevron_right</span>
      <RouterLink to="/courts" class="bc-link">{{ $t('schedule.breadcrumbCourts') }}</RouterLink>
      <template v-if="court">
        <span class="material-symbols-outlined bc-sep">chevron_right</span>
        <RouterLink :to="`/courts/${court.id}`" class="bc-link">
          {{ court.code }}
        </RouterLink>
      </template>
      <span class="material-symbols-outlined bc-sep">chevron_right</span>
      <span class="bc-current">{{ $t('schedule.breadcrumbSchedule') }}</span>
    </nav>

    <!-- Header Banner -->
    <header class="schedule-hdr">
      <div class="hdr-title-row">
        <h1 class="page-title">
          <span class="material-symbols-outlined hdr-icon">event_available</span>
          <span>{{ $t('schedule.schedulePageTitle') }}</span>
        </h1>
        <span class="live-pill">
          <span class="live-dot"></span>
          <span>{{ $t('schedule.liveUpdatePill') }}</span>
        </span>
      </div>
      <p class="page-sub">
        {{ $t('schedule.schedulePageSub') }}
        <strong>{{ court?.venue?.name || 'Aura Court' }}</strong
        >.
      </p>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">
      <span class="material-symbols-outlined icon-16">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- Date Carousel Picker -->
    <ScheduleDatePicker
      :days="carouselDays"
      :selected-date="date"
      @select-date="selectDate"
      @today="goToToday"
      @prev-day="goToPrevDay"
      @next-day="goToNextDay"
    />

    <!-- Court Selector Bar with Venue Filter -->
    <ScheduleCourtSelector
      :courts="filteredCourts"
      :venues="venuesList"
      :selected-court-id="selectedCourtId"
      :selected-venue-id="selectedVenueId"
      @select-court="selectCourt"
      @select-venue="selectVenue"
    />

    <!-- Main Split Layout (Slots Grid vs Sticky Summary) -->
    <div class="schedule-split-layout">
      <div class="slots-col">
        <ScheduleSlotsGrid
          :slots="filteredSlots"
          :selected-slots="store.selectedSlots"
          :time-filter="timeFilter"
          :loading="loading"
          @toggle-slot="toggleSlot"
          @update-time-filter="timeFilter = $event"
          @quick-select="quickSelectAvailableHours"
          @clear-selected="clearSelectedSlots"
        />
      </div>

      <div class="summary-col">
        <ScheduleBookingSummary
          v-if="court"
          :court="court"
          :direct-owner-payments="directOwnerPayments"
          :date="date"
          :selected-slots="store.selectedSlots"
          :loading="checkoutLoading"
          :error="error"
          :notice="notice"
          :resending-email="resendingEmail"
          @checkout="proceedToBooking"
          @remove-slot="removeSlot"
          @resend-verification="resendVerificationEmail"
          @clear-error="error = ''"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.schedule-view-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1280px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.schedule-breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--muted);
  flex-wrap: wrap;
}

.bc-link {
  color: var(--muted);
  text-decoration: none;
  transition: color 0.15s;
}

.bc-link:hover {
  color: var(--primary);
}

.bc-sep {
  font-size: 14px;
  color: var(--muted);
  opacity: 0.6;
}

.bc-current {
  color: var(--ink);
  font-weight: 700;
}

.schedule-hdr {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.hdr-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.page-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.hdr-icon {
  font-size: 26px;
  color: var(--primary);
}

.live-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--live-bg, rgba(201, 161, 90, 0.2));
  color: var(--live-text, #6b2d3f);
  border: 1px solid var(--live-border, #c9a15a);
  font-size: 11px;
  font-weight: 700;
}

.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 8px #10b981;
  animation: pulseDot 2s infinite ease-in-out;
}

@keyframes pulseDot {
  0% {
    transform: scale(0.9);
    opacity: 0.7;
  }
  50% {
    transform: scale(1.3);
    opacity: 1;
  }
  100% {
    transform: scale(0.9);
    opacity: 0.7;
  }
}

.page-sub {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted);
  line-height: 1.5;
}

.page-sub strong {
  color: var(--ink);
}

.alert-pill {
  padding: 10px 16px;
  border-radius: 10px;
  background: var(--success-bg, #e8f5e9);
  color: var(--success-text, #2e7d32);
  border: 1px solid rgba(46, 125, 50, 0.25);
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 10px;
  background: var(--error-bg, #ffebee);
  color: var(--error-text, #c62828);
  border: 1px solid rgba(198, 40, 40, 0.25);
  font-size: 13px;
  font-weight: 600;
}

.icon-16 {
  font-size: 16px;
}

.schedule-split-layout {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 24px;
  align-items: flex-start;
}

.summary-col {
  position: sticky;
  top: 90px;
}

@media (max-width: 960px) {
  .schedule-split-layout {
    grid-template-columns: 1fr;
  }
  .summary-col {
    position: static;
  }
}
</style>
