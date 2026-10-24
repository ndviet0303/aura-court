<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { DateTimePicker } from '@/shared/components'
import type { SetupForm } from '../../composables/useTournamentSetup'
import { LIMITS } from '../../types/tournament'

// Public: places, fee and the deadlines (BR-149: registration ≤ payment ≤ start).
// A new internal tournament instead names the confirmed bookings it runs on (UC-7.17).
const form = defineModel<SetupForm>('form', { required: true })
defineProps<{ creating: boolean }>()
const { t } = useI18n()
const dates = ['registration_deadline', 'payment_deadline', 'starts_at', 'ends_at'] as const
</script>

<template>
  <div v-if="form.type === 'public'">
    <div class="setup-row">
      <label class="form-group">
        <span class="form-label">{{ t('tournament.fields.max_entries') }} *</span>
        <input
          v-model.number="form.max_entries"
          type="number"
          class="form-input"
          :min="LIMITS.maxEntries[0]"
          :max="LIMITS.maxEntries[1]"
        />
      </label>
      <label class="form-group">
        <span class="form-label">{{ t('tournament.fields.fee') }} (VND) *</span>
        <input
          v-model.number="form.fee"
          type="number"
          class="form-input"
          :min="LIMITS.minFee"
          step="1000"
        />
      </label>
    </div>
    <div class="setup-row">
      <div v-for="key in dates" :key="key" class="form-group">
        <span class="form-label">{{ t(`tournament.fields.${key}`) }} *</span>
        <DateTimePicker v-model="form[key]" />
      </div>
    </div>
    <p class="form-hint">{{ t('tournament.setup.deadlineHint') }}</p>
  </div>

  <div v-else-if="creating">
    <label class="form-group">
      <span class="form-label">{{ t('tournament.setup.reservationCodes') }} *</span>
      <textarea v-model="form.reservation_codes" rows="3" class="form-textarea" />
      <span class="form-hint">{{ t('tournament.setup.reservationCodesHint') }}</span>
    </label>
    <label class="form-group">
      <span class="form-label">{{ t('tournament.setup.representativeEmail') }}</span>
      <input v-model="form.representative_email" type="email" class="form-input" />
      <span class="form-hint">{{ t('tournament.setup.representativeHint') }}</span>
    </label>
  </div>
  <p v-else class="admin-detail-muted">{{ t('tournament.setup.internalNoSchedule') }}</p>
</template>

<style scoped>
.setup-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0 12px;
}
</style>
