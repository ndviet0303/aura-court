<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { VenueBookingPolicySettings, VenueBookingPolicyVersion } from '../../types/admin'

type PolicyDraft = {
  booking: {
    hold_ttl_minutes: string
    max_days_ahead: string
    min_duration_minutes: string
    max_duration_minutes: string
    require_continuity: string
  }
  cancellation: {
    deadline_hours: string
    fee_type: string
    fee_value: string
    refund_due_hours: string
  }
  change: {
    deadline_hours: string
    fee_type: string
    fee_value: string
  }
  arrival: {
    early_minutes: string
    late_grace_minutes: string
    no_show_after_minutes: string
  }
  recurring: {
    payment_policy: string
    conflict_policy: string
    overdue_release_hours: string
  }
  transfer: {
    cutoff_hours: string
    max_consideration_percent: string
    free_transfer_allowed: string
    seller_reimbursement_required: string
  }
  ancillary_credit_policy: string
}

const props = defineProps<{
  policy: VenueBookingPolicyVersion | null
  loading: boolean
  saving: boolean
  canConfigure: boolean
}>()

const emit = defineEmits<{
  (e: 'save', settings: VenueBookingPolicySettings): void
}>()

const { t } = useI18n()
const validationError = ref('')

function emptyDraft(): PolicyDraft {
  return {
    booking: { hold_ttl_minutes: '', max_days_ahead: '', min_duration_minutes: '', max_duration_minutes: '', require_continuity: '' },
    cancellation: { deadline_hours: '', fee_type: '', fee_value: '', refund_due_hours: '' },
    change: { deadline_hours: '', fee_type: '', fee_value: '' },
    arrival: { early_minutes: '', late_grace_minutes: '', no_show_after_minutes: '' },
    recurring: { payment_policy: '', conflict_policy: '', overdue_release_hours: '' },
    transfer: { cutoff_hours: '', max_consideration_percent: '', free_transfer_allowed: '', seller_reimbursement_required: '' },
    ancillary_credit_policy: '',
  }
}

const draft = ref<PolicyDraft>(emptyDraft())

watch(
  () => props.policy,
  (policy) => {
    validationError.value = ''
    const settings = policy?.settings
    draft.value = settings ? {
      booking: {
        hold_ttl_minutes: String(settings.booking.hold_ttl_minutes),
        max_days_ahead: String(settings.booking.max_days_ahead),
        min_duration_minutes: String(settings.booking.min_duration_minutes),
        max_duration_minutes: String(settings.booking.max_duration_minutes),
        require_continuity: String(settings.booking.require_continuity),
      },
      cancellation: {
        deadline_hours: String(settings.cancellation.deadline_hours),
        fee_type: settings.cancellation.fee_type,
        fee_value: String(settings.cancellation.fee_value),
        refund_due_hours: String(settings.cancellation.refund_due_hours),
      },
      change: {
        deadline_hours: String(settings.change.deadline_hours),
        fee_type: settings.change.fee_type,
        fee_value: String(settings.change.fee_value),
      },
      arrival: {
        early_minutes: String(settings.arrival.early_minutes),
        late_grace_minutes: String(settings.arrival.late_grace_minutes),
        no_show_after_minutes: String(settings.arrival.no_show_after_minutes),
      },
      recurring: {
        payment_policy: settings.recurring.payment_policy,
        conflict_policy: settings.recurring.conflict_policy,
        overdue_release_hours: String(settings.recurring.overdue_release_hours),
      },
      transfer: {
        cutoff_hours: String(settings.transfer.cutoff_hours),
        max_consideration_percent: String(settings.transfer.max_consideration_percent),
        free_transfer_allowed: String(settings.transfer.free_transfer_allowed),
        seller_reimbursement_required: String(settings.transfer.seller_reimbursement_required),
      },
      ancillary_credit_policy: settings.ancillary_credit_policy,
    } : emptyDraft()
  },
  { immediate: true }
)

function save() {
  validationError.value = ''
  const values = [
    draft.value.booking.hold_ttl_minutes,
    draft.value.booking.max_days_ahead,
    draft.value.booking.min_duration_minutes,
    draft.value.booking.max_duration_minutes,
    draft.value.booking.require_continuity,
    draft.value.cancellation.deadline_hours,
    draft.value.cancellation.fee_type,
    draft.value.cancellation.fee_value,
    draft.value.cancellation.refund_due_hours,
    draft.value.change.deadline_hours,
    draft.value.change.fee_type,
    draft.value.change.fee_value,
    draft.value.arrival.early_minutes,
    draft.value.arrival.late_grace_minutes,
    draft.value.arrival.no_show_after_minutes,
    draft.value.recurring.payment_policy,
    draft.value.recurring.conflict_policy,
    draft.value.recurring.overdue_release_hours,
    draft.value.transfer.cutoff_hours,
    draft.value.transfer.max_consideration_percent,
    draft.value.transfer.free_transfer_allowed,
    draft.value.transfer.seller_reimbursement_required,
    draft.value.ancillary_credit_policy,
  ]

  if (values.some((value) => value === '')) {
    validationError.value = t('admin.venues.bookingPolicyRequired')
    return
  }

  const number = (value: string) => Number(value)
  const settings: VenueBookingPolicySettings = {
    booking: {
      hold_ttl_minutes: number(draft.value.booking.hold_ttl_minutes),
      max_days_ahead: number(draft.value.booking.max_days_ahead),
      min_duration_minutes: number(draft.value.booking.min_duration_minutes),
      max_duration_minutes: number(draft.value.booking.max_duration_minutes),
      require_continuity: draft.value.booking.require_continuity === 'true',
    },
    cancellation: {
      deadline_hours: number(draft.value.cancellation.deadline_hours),
      fee_type: draft.value.cancellation.fee_type as VenueBookingPolicySettings['cancellation']['fee_type'],
      fee_value: number(draft.value.cancellation.fee_value),
      refund_due_hours: number(draft.value.cancellation.refund_due_hours),
    },
    change: {
      deadline_hours: number(draft.value.change.deadline_hours),
      fee_type: draft.value.change.fee_type as VenueBookingPolicySettings['change']['fee_type'],
      fee_value: number(draft.value.change.fee_value),
    },
    arrival: {
      early_minutes: number(draft.value.arrival.early_minutes),
      late_grace_minutes: number(draft.value.arrival.late_grace_minutes),
      no_show_after_minutes: number(draft.value.arrival.no_show_after_minutes),
    },
    recurring: {
      payment_policy: draft.value.recurring.payment_policy as VenueBookingPolicySettings['recurring']['payment_policy'],
      conflict_policy: draft.value.recurring.conflict_policy as VenueBookingPolicySettings['recurring']['conflict_policy'],
      overdue_release_hours: number(draft.value.recurring.overdue_release_hours),
    },
    transfer: {
      cutoff_hours: number(draft.value.transfer.cutoff_hours),
      max_consideration_percent: number(draft.value.transfer.max_consideration_percent),
      free_transfer_allowed: draft.value.transfer.free_transfer_allowed === 'true',
      seller_reimbursement_required: draft.value.transfer.seller_reimbursement_required === 'true',
    },
    ancillary_credit_policy: draft.value.ancillary_credit_policy as VenueBookingPolicySettings['ancillary_credit_policy'],
  }

  if (settings.booking.min_duration_minutes > settings.booking.max_duration_minutes) {
    validationError.value = t('admin.venues.bookingPolicyDurationInvalid')
    return
  }
  if (
    (settings.cancellation.fee_type === 'percentage' && settings.cancellation.fee_value > 100)
    || (settings.change.fee_type === 'percentage' && settings.change.fee_value > 100)
    || (settings.cancellation.fee_type === 'none' && settings.cancellation.fee_value !== 0)
    || (settings.change.fee_type === 'none' && settings.change.fee_value !== 0)
  ) {
    validationError.value = t('admin.venues.bookingPolicyFeeInvalid')
    return
  }

  emit('save', settings)
}
</script>

<template>
  <section class="booking-policy-form">
    <div class="policy-heading">
      <div>
        <h4>{{ t('admin.venues.bookingPolicyTitle') }}</h4>
        <p>{{ t('admin.venues.bookingPolicyDescription') }}</p>
      </div>
      <span class="policy-version">{{ policy ? t('admin.venues.bookingPolicyVersion', { version: policy.version }) : t('admin.venues.bookingPolicyNotConfigured') }}</span>
    </div>

    <p v-if="!canConfigure" class="policy-note">{{ t('admin.venues.bookingPolicySaveVenueFirst') }}</p>
    <div v-else-if="loading" class="policy-note">{{ t('admin.venues.loadingBookingPolicy') }}</div>
    <form v-else class="policy-fields" @submit.prevent="save">
      <fieldset>
        <legend>{{ t('admin.venues.bookingPolicyBooking') }}</legend>
        <label>{{ t('admin.venues.bookingPolicyHoldTtl') }}<input v-model="draft.booking.hold_ttl_minutes" type="number" min="1" max="1440" required /></label>
        <label>{{ t('admin.venues.bookingPolicyMaxAhead') }}<input v-model="draft.booking.max_days_ahead" type="number" min="1" max="365" required /></label>
        <label>{{ t('admin.venues.bookingPolicyMinDuration') }}<input v-model="draft.booking.min_duration_minutes" type="number" min="15" max="1440" required /></label>
        <label>{{ t('admin.venues.bookingPolicyMaxDuration') }}<input v-model="draft.booking.max_duration_minutes" type="number" min="15" max="1440" required /></label>
        <label>{{ t('admin.venues.bookingPolicyContinuity') }}<select v-model="draft.booking.require_continuity" required><option value="">—</option><option value="true">{{ t('admin.venues.bookingPolicyYes') }}</option><option value="false">{{ t('admin.venues.bookingPolicyNo') }}</option></select></label>
      </fieldset>
      <fieldset>
        <legend>{{ t('admin.venues.bookingPolicyCancellation') }}</legend>
        <label>{{ t('admin.venues.bookingPolicyCancelDeadline') }}<input v-model="draft.cancellation.deadline_hours" type="number" min="0" max="8760" required /></label>
        <label>{{ t('admin.venues.bookingPolicyCancelFeeType') }}<select v-model="draft.cancellation.fee_type" required><option value="">—</option><option value="none">{{ t('admin.venues.bookingPolicyFeeNone') }}</option><option value="fixed">{{ t('admin.venues.bookingPolicyFeeFixed') }}</option><option value="percentage">{{ t('admin.venues.bookingPolicyFeePercent') }}</option></select></label>
        <label>{{ t('admin.venues.bookingPolicyCancelFeeValue') }}<input v-model="draft.cancellation.fee_value" type="number" min="0" required /></label>
        <label>{{ t('admin.venues.bookingPolicyRefundDue') }}<input v-model="draft.cancellation.refund_due_hours" type="number" min="1" max="720" required /></label>
      </fieldset>
      <fieldset>
        <legend>{{ t('admin.venues.bookingPolicyChange') }}</legend>
        <label>{{ t('admin.venues.bookingPolicyChangeDeadline') }}<input v-model="draft.change.deadline_hours" type="number" min="0" max="8760" required /></label>
        <label>{{ t('admin.venues.bookingPolicyChangeFeeType') }}<select v-model="draft.change.fee_type" required><option value="">—</option><option value="none">{{ t('admin.venues.bookingPolicyFeeNone') }}</option><option value="fixed">{{ t('admin.venues.bookingPolicyFeeFixed') }}</option><option value="percentage">{{ t('admin.venues.bookingPolicyFeePercent') }}</option></select></label>
        <label>{{ t('admin.venues.bookingPolicyChangeFeeValue') }}<input v-model="draft.change.fee_value" type="number" min="0" required /></label>
      </fieldset>
      <fieldset>
        <legend>{{ t('admin.venues.bookingPolicyArrival') }}</legend>
        <label>{{ t('admin.venues.bookingPolicyEarlyArrival') }}<input v-model="draft.arrival.early_minutes" type="number" min="0" max="1440" required /></label>
        <label>{{ t('admin.venues.bookingPolicyLateGrace') }}<input v-model="draft.arrival.late_grace_minutes" type="number" min="0" max="1440" required /></label>
        <label>{{ t('admin.venues.bookingPolicyNoShowAfter') }}<input v-model="draft.arrival.no_show_after_minutes" type="number" min="1" max="1440" required /></label>
      </fieldset>
      <fieldset>
        <legend>{{ t('admin.venues.bookingPolicyRecurring') }}</legend>
        <label>{{ t('admin.venues.bookingPolicyRecurringPayment') }}<select v-model="draft.recurring.payment_policy" required><option value="">—</option><option value="full_series">{{ t('admin.venues.bookingPolicyFullSeries') }}</option><option value="per_period">{{ t('admin.venues.bookingPolicyPerPeriod') }}</option></select></label>
        <label>{{ t('admin.venues.bookingPolicyRecurringConflict') }}<select v-model="draft.recurring.conflict_policy" required><option value="">—</option><option value="all_or_nothing">{{ t('admin.venues.bookingPolicyAllOrNothing') }}</option><option value="partial">{{ t('admin.venues.bookingPolicyPartial') }}</option></select></label>
        <label>{{ t('admin.venues.bookingPolicyOverdueRelease') }}<input v-model="draft.recurring.overdue_release_hours" type="number" min="1" max="720" required /></label>
      </fieldset>
      <fieldset>
        <legend>{{ t('admin.venues.bookingPolicyTransfer') }}</legend>
        <label>{{ t('admin.venues.bookingPolicyTransferCutoff') }}<input v-model="draft.transfer.cutoff_hours" type="number" min="0" max="8760" required /></label>
        <label>{{ t('admin.venues.bookingPolicyMaxConsideration') }}<input v-model="draft.transfer.max_consideration_percent" type="number" min="0" max="100" required /></label>
        <label>{{ t('admin.venues.bookingPolicyFreeTransfer') }}<select v-model="draft.transfer.free_transfer_allowed" required><option value="">—</option><option value="true">{{ t('admin.venues.bookingPolicyYes') }}</option><option value="false">{{ t('admin.venues.bookingPolicyNo') }}</option></select></label>
        <label>{{ t('admin.venues.bookingPolicySellerRefund') }}<select v-model="draft.transfer.seller_reimbursement_required" required><option value="">—</option><option value="true">{{ t('admin.venues.bookingPolicyYes') }}</option><option value="false">{{ t('admin.venues.bookingPolicyNo') }}</option></select></label>
      </fieldset>
      <fieldset>
        <legend>{{ t('admin.venues.bookingPolicyAncillary') }}</legend>
        <label>{{ t('admin.venues.bookingPolicyAncillaryHandling') }}<select v-model="draft.ancillary_credit_policy" required><option value="">—</option><option value="retain">{{ t('admin.venues.bookingPolicyRetain') }}</option><option value="refund">{{ t('admin.venues.bookingPolicyRefund') }}</option><option value="prorate">{{ t('admin.venues.bookingPolicyProrate') }}</option></select></label>
      </fieldset>
      <p v-if="validationError" class="policy-error">{{ validationError }}</p>
      <AuraButton type="submit" variant="secondary" :loading="saving">
        {{ t('admin.venues.saveBookingPolicy') }}
      </AuraButton>
    </form>
  </section>
</template>

<style scoped>
.booking-policy-form { border: 1px solid var(--outline); border-radius: 12px; padding: 16px; }
.policy-heading { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.policy-heading h4 { margin: 0; color: var(--ink); font-size: 14px; }
.policy-heading p, .policy-note { margin: 6px 0 0; color: var(--muted); font-size: 12px; }
.policy-version { color: var(--muted); font-size: 11px; white-space: nowrap; }
.policy-fields { display: grid; gap: 12px; margin-top: 14px; }
fieldset { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; min-width: 0; margin: 0; padding: 12px; border: 1px solid var(--outline); border-radius: 8px; }
legend { padding: 0 5px; color: var(--ink); font-size: 12px; font-weight: 700; }
label { display: grid; gap: 5px; color: var(--muted); font-size: 11px; }
input, select { min-width: 0; padding: 7px 9px; border: 1px solid var(--outline); border-radius: 7px; background: var(--surface); color: var(--ink); font: inherit; }
.policy-error { margin: 0; color: #dc2626; font-size: 12px; }
</style>
