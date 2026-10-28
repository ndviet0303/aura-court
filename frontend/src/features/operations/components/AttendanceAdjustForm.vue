<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { venueClock, venueDate, venueInstant } from '@/core/utils/venueTime'
import { AuraButton, DateTimePicker, SelectDropdown } from '@/shared/components'
import type { AdjustmentForm, OperationalReservation, OperationalStatus } from '../types/operations'

/**
 * FR-108, BR-134..BR-137: the owner corrects the operational status and its times with a reason. The booked time,
 * the price and the payment never change here; the server keeps the old values in the history.
 */
const props = defineProps<{ reservation: OperationalReservation; busy?: boolean }>()
const emit = defineEmits<{ submit: [form: AdjustmentForm]; cancel: [] }>()
const { t } = useI18n()

const STATUSES: OperationalStatus[] = ['confirmed', 'in_use', 'completed', 'no_show']
const local = (value?: string | null) => (value ? `${venueDate(value)}T${venueClock(value)}` : '')

const status = ref<OperationalStatus>(props.reservation.operational_status)
const checkedInAt = ref(
  local(props.reservation.attendance?.checked_in_at ?? props.reservation.starts_at),
)
const checkedOutAt = ref(
  local(props.reservation.attendance?.checked_out_at ?? props.reservation.ends_at),
)
const reason = ref('')

const statusOptions = computed(() =>
  STATUSES.map((value) => ({ value, label: t(`operations.status.${value}`) })),
)
const needsCheckIn = computed(() => status.value === 'in_use' || status.value === 'completed')
const needsCheckOut = computed(() => status.value === 'completed')
// "YYYY-MM-DDTHH:mm" strings on one clock compare in time order.
const outBeforeIn = computed(() => needsCheckOut.value && checkedOutAt.value < checkedInAt.value)
const canSubmit = computed(
  () =>
    reason.value.trim().length > 0 &&
    (!needsCheckIn.value || !!checkedInAt.value) &&
    (!needsCheckOut.value || !!checkedOutAt.value) &&
    !outBeforeIn.value,
)

function submit() {
  if (!canSubmit.value || props.busy) return
  emit('submit', {
    code: props.reservation.check_in_code,
    status: status.value,
    reason: reason.value.trim(),
    ...(needsCheckIn.value ? { checked_in_at: venueInstant(checkedInAt.value) } : {}),
    ...(needsCheckOut.value ? { checked_out_at: venueInstant(checkedOutAt.value) } : {}),
    expected: {
      status: props.reservation.operational_status,
      checked_in_at: props.reservation.attendance?.checked_in_at ?? null,
      checked_out_at: props.reservation.attendance?.checked_out_at ?? null,
      no_show_at: props.reservation.attendance?.no_show_at ?? null,
    },
  })
}
</script>

<template>
  <form class="adjust-form" @submit.prevent="submit">
    <p class="adjust-hint">{{ t('operations.adjust.hint') }}</p>
    <div class="form-group">
      <span class="form-label">{{ t('operations.adjust.status') }} *</span>
      <SelectDropdown
        :model-value="status"
        :options="statusOptions"
        variant="bordered"
        :aria-label="t('operations.adjust.status')"
        @update:model-value="status = $event as OperationalStatus"
      />
    </div>
    <div v-if="needsCheckIn" class="form-group">
      <span class="form-label">{{ t('operations.detail.checkedIn') }} *</span>
      <DateTimePicker v-model="checkedInAt" />
    </div>
    <div v-if="needsCheckOut" class="form-group">
      <span class="form-label">{{ t('operations.detail.checkedOut') }} *</span>
      <DateTimePicker v-model="checkedOutAt" />
      <span v-if="outBeforeIn" class="form-error">{{ t('operations.adjust.outBeforeIn') }}</span>
    </div>
    <label class="form-group">
      <span class="form-label">{{ t('operations.adjust.reason') }} *</span>
      <textarea v-model="reason" rows="2" maxlength="1000" class="form-textarea" />
    </label>
    <div class="adjust-actions">
      <AuraButton variant="outline" :disabled="busy" @click="emit('cancel')">
        {{ t('common.cancel') }}
      </AuraButton>
      <AuraButton type="submit" :loading="busy" :disabled="!canSubmit">
        {{ t('operations.adjust.submit') }}
      </AuraButton>
    </div>
  </form>
</template>

<style scoped>
.adjust-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.adjust-hint {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(245, 158, 11, 0.1);
  color: #92400e;
  font-size: 12px;
}

.adjust-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.form-group :deep(.date-time-picker) {
  display: flex;
  gap: 6px;
}
</style>
