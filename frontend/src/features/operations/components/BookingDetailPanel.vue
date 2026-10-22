<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { venueClockRange, venueDate, venueDateTime } from '@/core/utils/venueTime'
import { AuraButton } from '@/shared/components'
import type {
  AdjustmentForm,
  AttendanceAdjustment,
  AttendanceCommand,
  AttendancePayment,
  OperationalReservation,
} from '../types/operations'
import AttendanceAdjustForm from './AttendanceAdjustForm.vue'

/**
 * FR-102: what the operator needs to serve one booking, its payment result (BR-122) and its recorded check-in,
 * check-out or no-show. No-show asks the operator to confirm the on-site check first (D13); only the owner adjusts
 * and sees the adjustment history (FR-108, FR-109).
 */
const props = defineProps<{
  reservation: OperationalReservation
  payment: AttendancePayment | null
  history: AttendanceAdjustment[]
  owner: boolean
  busy?: boolean
  error?: string
}>()
const emit = defineEmits<{
  close: []
  command: [command: AttendanceCommand]
  adjust: [form: AdjustmentForm]
}>()
const { t } = useI18n()

const step = ref<'view' | 'no-show' | 'adjust'>('view')
watch(
  () => props.reservation,
  () => (step.value = 'view'),
)

const closeOnEscape = (event: KeyboardEvent) => event.key === 'Escape' && emit('close')
onMounted(() => window.addEventListener('keydown', closeOnEscape))
onBeforeUnmount(() => window.removeEventListener('keydown', closeOnEscape))

const status = computed(() => props.reservation.operational_status)
// BR-133: a no-show is recorded only once the booked time is over.
const canNoShow = computed(
  () => status.value === 'confirmed' && Date.now() >= Date.parse(props.reservation.ends_at),
)
const records = computed(() => {
  const a = props.reservation.attendance
  return [
    { key: 'checkedIn', at: a?.checked_in_at, by: a?.checked_in_by },
    { key: 'checkedOut', at: a?.checked_out_at, by: a?.checked_out_by },
    { key: 'noShow', at: a?.no_show_at, by: a?.no_show_by },
  ].filter((record) => record.at)
})
</script>

<template>
  <Teleport to="body">
    <div class="modal-backdrop" @click.self="emit('close')">
      <div
        class="modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="booking-detail-title"
      >
        <div class="modal-header">
          <h3 id="booking-detail-title" class="modal-title">
            {{ reservation.code }} · {{ t(`operations.status.${status}`) }}
          </h3>
          <button
            type="button"
            class="modal-close-btn"
            :aria-label="t('common.close')"
            @click="emit('close')"
          >
            ✕
          </button>
        </div>

        <div class="modal-body detail">
          <p v-if="error" class="form-error" role="alert">{{ error }}</p>
          <dl class="facts">
            <dt>{{ t('operations.detail.place') }}</dt>
            <dd>{{ reservation.venue.name }} · {{ reservation.court.code }}</dd>
            <dt>{{ t('operations.detail.booked') }}</dt>
            <dd>
              {{ venueDate(reservation.starts_at) }}
              {{ venueClockRange(reservation.starts_at, reservation.ends_at) }}
            </dd>
            <dt>{{ t('operations.detail.customer') }}</dt>
            <dd>
              {{ reservation.customer_name ?? '—' }} · {{ reservation.customer_phone ?? '—' }}
            </dd>
            <dt>{{ t('operations.detail.amount') }}</dt>
            <dd>{{ formatCurrency(reservation.total_amount) }}</dd>
            <dt>{{ t('operations.detail.payment') }}</dt>
            <dd>{{ t(`operations.payment.${payment?.status ?? 'none'}`) }}</dd>
            <template v-for="record in records" :key="record.key">
              <dt>{{ t(`operations.detail.${record.key}`) }}</dt>
              <dd>{{ venueDateTime(record.at) }} · {{ record.by ?? '—' }}</dd>
            </template>
          </dl>

          <div v-if="step === 'no-show'" class="confirm">
            <p>{{ t('operations.noShow.confirm') }}</p>
            <div class="actions">
              <AuraButton variant="outline" :disabled="busy" @click="step = 'view'">
                {{ t('common.cancel') }}
              </AuraButton>
              <AuraButton variant="danger" :loading="busy" @click="emit('command', 'no-show')">
                {{ t('operations.action.no-show') }}
              </AuraButton>
            </div>
          </div>

          <AttendanceAdjustForm
            v-else-if="step === 'adjust'"
            :reservation="reservation"
            :busy="busy"
            @cancel="step = 'view'"
            @submit="emit('adjust', $event)"
          />

          <div v-else class="actions">
            <AuraButton
              v-if="status === 'confirmed'"
              :loading="busy"
              @click="emit('command', 'check-in')"
            >
              {{ t('operations.action.check-in') }}
            </AuraButton>
            <AuraButton
              v-if="status === 'in_use'"
              :loading="busy"
              @click="emit('command', 'check-out')"
            >
              {{ t('operations.action.check-out') }}
            </AuraButton>
            <AuraButton v-if="canNoShow" variant="outline" @click="step = 'no-show'">
              {{ t('operations.action.no-show') }}
            </AuraButton>
            <AuraButton v-if="owner" variant="outline" @click="step = 'adjust'">
              {{ t('operations.adjust.open') }}
            </AuraButton>
          </div>

          <section v-if="owner && history.length" class="history">
            <h4>{{ t('operations.adjust.history') }}</h4>
            <ul>
              <li v-for="item in history" :key="item.id">
                <strong>
                  {{ t(`operations.status.${item.before.status}`) }} →
                  {{ t(`operations.status.${item.after.status}`) }}
                </strong>
                <span class="muted">
                  {{ venueDateTime(item.created_at) }} · {{ item.adjusted_by ?? '—' }}
                </span>
                <span>{{ item.reason }}</span>
              </li>
            </ul>
          </section>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.detail {
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-y: auto;
}

.facts {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 6px 14px;
  margin: 0;
  font-size: 13px;
}

.facts dt {
  color: var(--muted);
}

.facts dd {
  margin: 0;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.confirm {
  padding: 12px;
  border-radius: 10px;
  background: rgba(239, 68, 68, 0.08);
  font-size: 13px;
}

.confirm p {
  margin: 0 0 10px;
}

.history h4 {
  margin: 0 0 8px;
  font-size: 14px;
}

.history ul {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
}

.history li {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--outline);
}

.muted {
  color: var(--muted);
  font-size: 12px;
}
</style>
