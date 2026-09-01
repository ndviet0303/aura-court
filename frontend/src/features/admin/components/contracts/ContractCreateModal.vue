<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, SelectDropdown, DatePicker } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { CreateContractPayload } from '../../types/contract'

const props = defineProps<{
  show: boolean
  venues: any[]
  courts?: any[]
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'create', payload: CreateContractPayload): void
}>()

const { t } = useI18n()

const selectedVenueId = ref<number | undefined>(undefined)
const selectedCourtId = ref<number | undefined>(undefined)

const customerName = ref('')
const phone = ref('')
const email = ref('')
const weekday = ref('Thứ 2, 4, 6')
const timeSlot = ref('18:00 - 20:00')
const durationMonths = ref<number>(6)
const startDate = ref(new Date().toISOString().substring(0, 10))
const monthlyAmount = ref(4800000)
const depositAmount = ref(5000000)
const notes = ref('')

// Filter courts based on selected venue
const availableCourts = computed(() => {
  const allCourts = props.courts && props.courts.length > 0
    ? props.courts
    : (props.venues || []).flatMap((v) => v.courts || [])

  if (selectedVenueId.value) {
    return allCourts.filter((c) => c.venue_id === selectedVenueId.value || c.venue?.id === selectedVenueId.value)
  }
  return allCourts
})

const venueOptions = computed<DropdownOption[]>(() =>
  (props.venues || []).map((v) => ({
    value: v.id,
    label: resolveLocalizedText(v.name),
  }))
)

const courtOptions = computed<DropdownOption[]>(() =>
  availableCourts.value.map((c) => ({
    value: c.id,
    label: resolveLocalizedText(c.name),
  }))
)

const weekdayOptions = computed<DropdownOption[]>(() => [
  { value: 'Thứ 2, 4, 6', label: 'Thứ 2, 4, 6 (Mon, Wed, Fri)' },
  { value: 'Thứ 3, 5, 7', label: 'Thứ 3, 5, 7 (Tue, Thu, Sat)' },
  { value: 'Thứ 7, Chủ Nhật', label: 'Thứ 7, Chủ Nhật (Sat, Sun)' },
  { value: 'Thứ 2, 3, 4, 5, 6', label: 'Thứ 2 đến Thứ 6 (Mon - Fri)' },
  { value: 'Chủ Nhật', label: 'Chủ Nhật hàng tuần (Every Sunday)' },
])

const timeSlotOptions = computed<DropdownOption[]>(() => [
  { value: '18:00 - 20:00', label: '18:00 - 20:00 (Giờ vàng tối)' },
  { value: '17:00 - 19:00', label: '17:00 - 19:00 (Chiều muộn)' },
  { value: '19:00 - 21:00', label: '19:00 - 21:00 (Giờ vàng tối)' },
  { value: '06:00 - 08:00', label: '06:00 - 08:00 (Sáng sớm)' },
  { value: '15:00 - 17:00', label: '15:00 - 17:00 (Buổi chiều)' },
])

const durationOptions = computed<DropdownOption[]>(() => [
  { value: 3, label: `3 ${t('admin.contracts.months')}` },
  { value: 6, label: `6 ${t('admin.contracts.months')}` },
  { value: 12, label: `12 ${t('admin.contracts.months')} (1 ${t('admin.contracts.year')})` },
])

// Auto-select initial venue & court
watch(
  [() => props.venues, () => props.courts],
  ([newVenues, newCourts]) => {
    if (newVenues && newVenues.length > 0 && !selectedVenueId.value) {
      selectedVenueId.value = newVenues[0].id
    }
    if (availableCourts.value.length > 0 && !selectedCourtId.value) {
      selectedCourtId.value = availableCourts.value[0].id
    }
  },
  { immediate: true }
)

watch(selectedVenueId, () => {
  if (availableCourts.value.length > 0) {
    selectedCourtId.value = availableCourts.value[0].id
  } else {
    selectedCourtId.value = undefined
  }
})

function handleCreate() {
  if (!selectedCourtId.value || !customerName.value.trim() || !phone.value.trim()) return

  const start = new Date(startDate.value)
  const end = new Date(start)
  end.setMonth(end.getMonth() + durationMonths.value)

  emit('create', {
    court_id: selectedCourtId.value,
    customer_name: customerName.value.trim(),
    phone: phone.value.trim(),
    email: email.value.trim() || undefined,
    weekday: weekday.value,
    time_slot: timeSlot.value,
    starts_at: start.toISOString(),
    ends_at: end.toISOString(),
    monthly_amount: monthlyAmount.value,
    deposit_amount: depositAmount.value,
    notes: notes.value.trim() || undefined,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">post_add</span>
          <h3 class="modal-title">{{ t('admin.contracts.createContractTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <form @submit.prevent="handleCreate">
        <div class="modal-body">
          <div class="create-form-grid">
            <!-- Venue Selection -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.selectVenueLabel') }} *</label>
              <SelectDropdown
                :model-value="selectedVenueId"
                :options="venueOptions"
                variant="bordered"
                :aria-label="t('admin.contracts.selectVenueLabel')"
                @update:model-value="(val) => (selectedVenueId = Number(val))"
              />
            </div>

            <!-- Court Selection -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.selectCourtLabel') }} *</label>
              <SelectDropdown
                :model-value="selectedCourtId"
                :options="courtOptions"
                variant="bordered"
                :aria-label="t('admin.contracts.selectCourtLabel')"
                @update:model-value="(val) => (selectedCourtId = Number(val))"
              />
            </div>

            <!-- Customer Name -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.customerNameLabel') }} *</label>
              <input
                v-model="customerName"
                type="text"
                class="form-input"
                :placeholder="t('admin.contracts.customerNamePlaceholder')"
                required
              />
            </div>

            <!-- Phone Number -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.phoneNumberLabel') }} *</label>
              <input
                v-model="phone"
                type="tel"
                class="form-input font-mono"
                placeholder="0901 234 567"
                required
              />
            </div>

            <!-- Recurring Weekday -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.weekdayScheduleLabel') }}</label>
              <SelectDropdown
                :model-value="weekday"
                :options="weekdayOptions"
                variant="bordered"
                :aria-label="t('admin.contracts.weekdayScheduleLabel')"
                @update:model-value="(val) => (weekday = String(val))"
              />
            </div>

            <!-- Fixed Time Slot -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.fixedTimeSlotLabel') }}</label>
              <SelectDropdown
                :model-value="timeSlot"
                :options="timeSlotOptions"
                variant="bordered"
                :aria-label="t('admin.contracts.fixedTimeSlotLabel')"
                @update:model-value="(val) => (timeSlot = String(val))"
              />
            </div>

            <!-- Start Date with shared DatePicker -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.startDateLabel') }}</label>
              <DatePicker v-model="startDate" />
            </div>

            <!-- Contract Duration -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.contractDurationLabel') }}</label>
              <SelectDropdown
                :model-value="durationMonths"
                :options="durationOptions"
                variant="bordered"
                :aria-label="t('admin.contracts.contractDurationLabel')"
                @update:model-value="(val) => (durationMonths = Number(val))"
              />
            </div>

            <!-- Monthly Value -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.monthlyContractValueLabel') }} *</label>
              <input
                v-model.number="monthlyAmount"
                type="number"
                step="100000"
                min="100000"
                class="form-input font-mono"
                required
              />
            </div>

            <!-- Deposit Amount -->
            <div class="form-group">
              <label class="form-label">{{ t('admin.contracts.depositAmountLabel') }}</label>
              <input
                v-model.number="depositAmount"
                type="number"
                step="100000"
                min="0"
                class="form-input font-mono"
              />
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <AuraButton variant="outline" type="button" @click="emit('close')">
            {{ t('common.cancel') }}
          </AuraButton>
          <AuraButton
            variant="primary"
            type="submit"
            class="btn-primary-pink"
            :disabled="saving || !selectedCourtId || !customerName.trim() || !phone.trim()"
          >
            <span v-if="saving" class="material-symbols-outlined icon-18 spin-icon">progress_activity</span>
            <span v-else class="material-symbols-outlined icon-18">verified</span>
            <span>{{ saving ? t('common.loading') : t('admin.contracts.signAndActivateBtn') }}</span>
          </AuraButton>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.18);
  overflow: hidden;
}

.modal-lg {
  max-width: 780px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 24px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  background: none;
  border: none;
  font-size: 18px;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
}

.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
}

.create-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group :deep(.custom-dropdown),
.form-group :deep(.custom-dropdown-trigger),
.form-group :deep(.date-picker),
.form-group :deep(.date-picker-trigger) {
  width: 100%;
}

.form-group :deep(.custom-dropdown-trigger),
.form-group :deep(.date-picker-trigger) {
  min-height: 42px;
  box-sizing: border-box;
}

.form-input {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  transition: border-color 0.15s;
  width: 100%;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.01));
}

.btn-primary-pink {
  background: var(--pink-dark, #db2777) !important;
  color: #fff !important;
}

.icon-18 {
  font-size: 18px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 650px) {
  .create-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
