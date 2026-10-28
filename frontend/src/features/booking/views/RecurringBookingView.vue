<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { formatCurrency } from '@/i18n'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { useRecurringBooking } from '../composables/useRecurringBooking'
import { validBookerPhone } from '../utils/reservation'
import RecurringScheduleForm from '../components/recurring/RecurringScheduleForm.vue'
import RecurringOccurrenceList from '../components/recurring/RecurringOccurrenceList.vue'
import RecurringApplyDialog from '../components/recurring/RecurringApplyDialog.vue'

// Module 08: a recurring booking is a set of ordinary bookings, each checked, priced and paid on its own.
const { t } = useI18n()
const auth = useAuthStore()
const phoneMissing = computed(() => !validBookerPhone(auth.user?.phone))

const {
  busy,
  today,
  courts,
  courtId,
  mode,
  month,
  weekdays,
  fromMonth,
  toMonth,
  days,
  dates,
  start,
  end,
  plan,
  monthRangeInvalid,
  rows,
  open,
  ready,
  total,
  checking,
  submitting,
  error,
  commonTimeValid,
  confirmingApply,
  requestApply,
  applyTime,
  check,
  setTime,
  setVoucher,
  setCode,
  setSelected,
  submit,
  load,
} = useRecurringBooking()

const unchecked = computed(() => open.value.filter((row) => !row.check).length)
const bookedCount = computed(() => rows.value.length - open.value.length)
const picked = computed(() => open.value.filter((row) => row.selected).length)

onMounted(load)
</script>

<template>
  <main class="recurring-page">
    <header class="recurring-head">
      <h1>{{ t('recurring.title') }}</h1>
      <p>{{ t('recurring.subtitle') }}</p>
    </header>

    <RecurringScheduleForm
      v-model:court-id="courtId"
      v-model:mode="mode"
      v-model:month="month"
      v-model:weekdays="weekdays"
      v-model:from-month="fromMonth"
      v-model:to-month="toMonth"
      v-model:days="days"
      v-model:dates="dates"
      v-model:start="start"
      v-model:end="end"
      :courts="courts"
      :today="today"
      :skipped="plan.skipped"
      :count="plan.dates.length"
      :month-range-invalid="monthRangeInvalid"
      :time-valid="commonTimeValid"
      :busy="busy"
      @apply-time="requestApply"
    />
    <RecurringApplyDialog
      v-if="confirmingApply"
      :time="`${start} – ${end}`"
      :count="open.length"
      :custom="open.filter((row) => row.custom).length"
      @confirm="applyTime"
      @close="confirmingApply = false"
    />

    <p v-if="error" class="recurring-error" role="alert">{{ error }}</p>

    <section v-if="rows.length" class="recurring-list">
      <div class="list-head">
        <h2>{{ t('recurring.occurrences', { count: rows.length }) }}</h2>
        <AuraButton
          variant="secondary"
          :loading="checking"
          :disabled="!courtId || unchecked === 0 || submitting"
          data-testid="recurring-check"
          @click="check()"
        >
          {{ unchecked ? t('recurring.check', { count: unchecked }) : t('recurring.allChecked') }}
        </AuraButton>
      </div>
      <RecurringOccurrenceList
        :rows="rows"
        :disabled="submitting"
        @time="setTime"
        @voucher="setVoucher"
        @code="setCode"
        @select="setSelected"
      />
    </section>

    <footer v-if="rows.length" class="recurring-submit">
      <div v-if="auth.user" class="booker">
        <span>{{ t('reservations.agreement.booker') }}</span>
        <strong>{{ auth.user.name }}</strong>
        <span
          >{{ auth.user.phone || t('reservations.agreement.noPhone') }} ·
          {{ auth.user.email }}</span
        >
        <RouterLink v-if="phoneMissing" :to="{ name: 'profile' }">
          {{ t('reservations.agreement.updatePhone') }}
        </RouterLink>
      </div>
      <p class="submit-note">{{ t('recurring.submitNote') }}</p>
      <p v-if="bookedCount" class="submit-note" data-testid="recurring-booked-count">
        {{ t('recurring.bookedCount', { count: bookedCount }) }}
      </p>
      <div class="submit-row">
        <span>
          {{ t('recurring.total', { count: ready.length }) }}
          <strong data-testid="recurring-total">{{ formatCurrency(total) }}</strong>
        </span>
        <AuraButton
          :loading="submitting"
          :disabled="ready.length === 0 || phoneMissing || checking"
          data-testid="recurring-submit"
          @click="submit"
        >
          {{
            ready.length
              ? t('recurring.submit', { count: ready.length })
              : t(picked ? 'recurring.noneReady' : 'recurring.noneSelected')
          }}
        </AuraButton>
      </div>
    </footer>
  </main>
</template>

<style scoped>
.recurring-page {
  display: grid;
  gap: 16px;
  max-width: 960px;
  margin: 0 auto;
  padding: 24px 16px 48px;
}
.recurring-head h1 {
  margin: 0;
  color: var(--ink);
  font-size: 24px;
}
.recurring-head p,
.submit-note {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 13px;
}
.recurring-error {
  margin: 0;
  color: #dc2626;
  font-size: 13px;
}
.recurring-list {
  display: grid;
  gap: 10px;
}
.list-head,
.submit-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  justify-content: space-between;
}
.list-head h2 {
  margin: 0;
  color: var(--ink);
  font-size: 16px;
}
.recurring-submit {
  display: grid;
  gap: 10px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}
.booker {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 10px;
  align-items: baseline;
}
.booker > span:first-child {
  color: var(--muted);
}
</style>
