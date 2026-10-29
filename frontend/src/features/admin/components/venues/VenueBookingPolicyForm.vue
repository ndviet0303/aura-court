<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { VenueBookingPolicySettings, VenueBookingPolicyVersion } from '../../types/admin'

type PolicyDraft = {
  booking: {
    max_days_ahead: string
    min_duration_minutes: string
    max_duration_minutes: string
  }
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
    booking: {
      max_days_ahead: '',
      min_duration_minutes: '',
      max_duration_minutes: '',
    },
  }
}

const draft = ref<PolicyDraft>(emptyDraft())

watch(
  () => props.policy,
  (policy) => {
    validationError.value = ''
    const settings = policy?.settings
    draft.value = settings
      ? {
          booking: {
            max_days_ahead: String(settings.booking.max_days_ahead),
            min_duration_minutes: String(settings.booking.min_duration_minutes),
            max_duration_minutes: String(settings.booking.max_duration_minutes),
          },
        }
      : emptyDraft()
  },
  { immediate: true },
)

function save() {
  validationError.value = ''
  const values = [
    draft.value.booking.max_days_ahead,
    draft.value.booking.min_duration_minutes,
    draft.value.booking.max_duration_minutes,
  ]

  if (values.some((value) => value === '')) {
    validationError.value = t('admin.venues.bookingPolicyRequired')
    return
  }

  const number = (value: string) => Number(value)
  const settings: VenueBookingPolicySettings = {
    booking: {
      max_days_ahead: number(draft.value.booking.max_days_ahead),
      min_duration_minutes: number(draft.value.booking.min_duration_minutes),
      max_duration_minutes: number(draft.value.booking.max_duration_minutes),
    },
  }

  if (settings.booking.min_duration_minutes > settings.booking.max_duration_minutes) {
    validationError.value = t('admin.venues.bookingPolicyDurationInvalid')
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
      <span class="policy-version">{{
        policy
          ? t('admin.venues.bookingPolicyVersion', { version: policy.version })
          : t('admin.venues.bookingPolicyNotConfigured')
      }}</span>
    </div>

    <p v-if="!canConfigure" class="policy-note">
      {{ t('admin.venues.bookingPolicySaveVenueFirst') }}
    </p>
    <div v-else-if="loading" class="policy-note">{{ t('admin.venues.loadingBookingPolicy') }}</div>
    <form v-else class="policy-fields" @submit.prevent="save">
      <fieldset>
        <legend>{{ t('admin.venues.bookingPolicyBooking') }}</legend>
        <label
          >{{ t('admin.venues.bookingPolicyMaxAhead')
          }}<input v-model="draft.booking.max_days_ahead" type="number" min="1" max="365" required
        /></label>
        <label
          >{{ t('admin.venues.bookingPolicyMinDuration')
          }}<input
            v-model="draft.booking.min_duration_minutes"
            type="number"
            min="15"
            max="1440"
            required
        /></label>
        <label
          >{{ t('admin.venues.bookingPolicyMaxDuration')
          }}<input
            v-model="draft.booking.max_duration_minutes"
            type="number"
            min="15"
            max="1440"
            required
        /></label>
      </fieldset>
      <p v-if="validationError" class="policy-error">{{ validationError }}</p>
      <AuraButton type="submit" variant="secondary" :loading="saving">
        {{ t('admin.venues.saveBookingPolicy') }}
      </AuraButton>
    </form>
  </section>
</template>

<style scoped>
.booking-policy-form {
  border: 1px solid var(--outline);
  border-radius: 12px;
  padding: 16px;
}
.policy-heading {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}
.policy-heading h4 {
  margin: 0;
  color: var(--ink);
  font-size: 14px;
}
.policy-heading p,
.policy-note {
  margin: 6px 0 0;
  color: var(--muted);
  font-size: 12px;
}
.policy-version {
  color: var(--muted);
  font-size: 11px;
  white-space: nowrap;
}
.policy-fields {
  display: grid;
  gap: 12px;
  margin-top: 14px;
}
fieldset {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
  gap: 10px;
  min-width: 0;
  margin: 0;
  padding: 12px;
  border: 1px solid var(--outline);
  border-radius: 8px;
}
legend {
  padding: 0 5px;
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
}
label {
  display: grid;
  gap: 5px;
  color: var(--muted);
  font-size: 11px;
}
input {
  min-width: 0;
  padding: 7px 9px;
  border: 1px solid var(--outline);
  border-radius: 7px;
  background: var(--surface);
  color: var(--ink);
  font: inherit;
}
.policy-error {
  margin: 0;
  color: #dc2626;
  font-size: 12px;
}
</style>
