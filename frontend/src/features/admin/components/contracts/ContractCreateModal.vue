<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
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

// Quick monthly presets
const monthlyPresets = [3000000, 4800000, 6000000, 7200000]

// Helper calculations
const daysPerWeek = computed(() => {
  if (weekday.value.includes('Thứ 2, 4, 6') || weekday.value.includes('Thứ 3, 5, 7')) return 3
  if (weekday.value.includes('Thứ 7, Chủ Nhật')) return 2
  if (weekday.value.includes('Thứ 2, 3, 4, 5, 6')) return 5
  return 1
})

const estimatedSessionsCount = computed(() => {
  const weeks = Math.round(durationMonths.value * 4.33)
  return weeks * daysPerWeek.value
})

const totalContractValue = computed(() => monthlyAmount.value * durationMonths.value)

// Formatted number inputs
function formatNumberStr(val: number): string {
  if (!val && val !== 0) return '0'
  return new Intl.NumberFormat('vi-VN').format(val)
}

function handleMonthlyInput(e: Event) {
  const input = e.target as HTMLInputElement
  const raw = input.value.replace(/\D/g, '')
  const num = raw ? parseInt(raw, 10) : 0
  monthlyAmount.value = num
  input.value = formatNumberStr(num)
}

function handleDepositInput(e: Event) {
  const input = e.target as HTMLInputElement
  const raw = input.value.replace(/\D/g, '')
  const num = raw ? parseInt(raw, 10) : 0
  depositAmount.value = num
  input.value = formatNumberStr(num)
}

function setDepositMultiplier(months: number) {
  depositAmount.value = monthlyAmount.value * months
}

// Auto-select initial venue & court
watch(
  [() => props.venues, () => props.courts],
  ([newVenues]) => {
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
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="title-with-icon">
          <div class="header-icon-badge">
            <span class="material-symbols-outlined card-title-icon">post_add</span>
          </div>
          <div class="title-text-group">
            <h3 class="modal-title">{{ t('admin.contracts.createContractTitle') }}</h3>
            <span class="modal-subtitle-badge">{{ t('admin.contracts.createModalBadge') }}</span>
          </div>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined icon-18">close</span>
        </button>
      </div>

      <!-- Modal Body Form -->
      <form class="create-form" @submit.prevent="handleCreate">
        <div class="modal-body">
          <!-- Section 1: Venue & Court -->
          <div class="form-section-card">
            <div class="section-title">
              <span class="material-symbols-outlined sec-icon">storefront</span>
              <span>{{ t('admin.contracts.sectionVenueCourt') }}</span>
            </div>
            <div class="grid-2-col">
              <div class="form-group">
                <label class="form-label">{{ t('admin.contracts.selectVenueLabel') }} <span class="required-star">*</span></label>
                <SelectDropdown
                  :model-value="selectedVenueId"
                  :options="venueOptions"
                  variant="bordered"
                  :aria-label="t('admin.contracts.selectVenueLabel')"
                  @update:model-value="(val) => (selectedVenueId = Number(val))"
                />
              </div>

              <div class="form-group">
                <label class="form-label">{{ t('admin.contracts.selectCourtLabel') }} <span class="required-star">*</span></label>
                <SelectDropdown
                  :model-value="selectedCourtId"
                  :options="courtOptions"
                  variant="bordered"
                  :aria-label="t('admin.contracts.selectCourtLabel')"
                  @update:model-value="(val) => (selectedCourtId = Number(val))"
                />
              </div>
            </div>
          </div>

          <!-- Section 2: Customer & Schedule -->
          <div class="form-section-card">
            <div class="section-title">
              <span class="material-symbols-outlined sec-icon">calendar_month</span>
              <span>{{ t('admin.contracts.sectionCustomerSchedule') }}</span>
            </div>
            <div class="grid-2-col">
              <!-- Customer Name -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.contracts.customerNameLabel') }} <span class="required-star">*</span></label>
                <div class="input-with-icon">
                  <span class="material-symbols-outlined input-lead-icon">person</span>
                  <input
                    v-model="customerName"
                    type="text"
                    class="form-input"
                    :placeholder="t('admin.contracts.customerNamePlaceholder')"
                    required
                  />
                </div>
              </div>

              <!-- Phone Number -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.contracts.phoneNumberLabel') }} <span class="required-star">*</span></label>
                <div class="input-with-icon">
                  <span class="material-symbols-outlined input-lead-icon">call</span>
                  <input
                    v-model="phone"
                    type="tel"
                    class="form-input font-mono"
                    placeholder="0901 234 567"
                    required
                  />
                </div>
              </div>

              <!-- Weekday Schedule -->
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

              <!-- Start Date -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.contracts.startDateLabel') }}</label>
                <DatePicker v-model="startDate" />
              </div>

              <!-- Duration -->
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
            </div>
          </div>

          <!-- Section 3: Financial Terms -->
          <div class="form-section-card">
            <div class="section-title">
              <span class="material-symbols-outlined sec-icon">payments</span>
              <span>{{ t('admin.contracts.sectionFinancialTerms') }}</span>
            </div>
            <div class="grid-2-col">
              <!-- Monthly Value Input -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.contracts.monthlyContractValueLabel') }} <span class="required-star">*</span></label>
                <div class="input-with-icon currency-wrap">
                  <span class="material-symbols-outlined input-lead-icon">paid</span>
                  <input
                    :value="formatNumberStr(monthlyAmount)"
                    type="text"
                    class="form-input font-mono text-bold"
                    required
                    @input="handleMonthlyInput"
                  />
                  <span class="input-unit-tag">₫/tháng</span>
                </div>
                <!-- Preset chips -->
                <div class="preset-chips-row">
                  <button
                    v-for="p in monthlyPresets"
                    :key="p"
                    type="button"
                    class="preset-chip"
                    :class="{ active: monthlyAmount === p }"
                    @click="monthlyAmount = p"
                  >
                    {{ formatCurrency(p) }}
                  </button>
                </div>
              </div>

              <!-- Security Deposit Input -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.contracts.depositAmountLabel') }}</label>
                <div class="input-with-icon currency-wrap">
                  <span class="material-symbols-outlined input-lead-icon">lock</span>
                  <input
                    :value="formatNumberStr(depositAmount)"
                    type="text"
                    class="form-input font-mono text-bold"
                    @input="handleDepositInput"
                  />
                  <span class="input-unit-tag">₫</span>
                </div>
                <!-- Deposit presets -->
                <div class="preset-chips-row">
                  <button
                    type="button"
                    class="preset-chip"
                    :class="{ active: depositAmount === 0 }"
                    @click="depositAmount = 0"
                  >
                    {{ t('admin.contracts.presetDepositNone') }}
                  </button>
                  <button
                    type="button"
                    class="preset-chip"
                    :class="{ active: depositAmount === monthlyAmount }"
                    @click="setDepositMultiplier(1)"
                  >
                    1 tháng
                  </button>
                  <button
                    type="button"
                    class="preset-chip"
                    :class="{ active: depositAmount === monthlyAmount * 2 }"
                    @click="setDepositMultiplier(2)"
                  >
                    2 tháng
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 4: Summary Card -->
          <div class="summary-estimate-card">
            <div class="summary-header">
              <span class="material-symbols-outlined text-pink icon-18">receipt_long</span>
              <span class="summary-title">{{ t('admin.contracts.sectionSummary') }}</span>
            </div>
            <div class="summary-metrics-grid">
              <div class="metric-box">
                <span class="m-label">{{ t('admin.contracts.summarySchedule') }}</span>
                <strong class="m-val">{{ weekday }}</strong>
                <span class="m-sub font-mono">{{ timeSlot }}</span>
              </div>
              <div class="metric-box">
                <span class="m-label">{{ t('admin.contracts.summaryDuration') }}</span>
                <strong class="m-val">{{ durationMonths }} {{ t('admin.contracts.months') }}</strong>
                <span class="m-sub text-pink">{{ t('admin.contracts.summaryEstimatedSessions', { count: estimatedSessionsCount }) }}</span>
              </div>
              <div class="metric-box">
                <span class="m-label">{{ t('admin.contracts.summaryTotalValue') }}</span>
                <strong class="m-val font-mono text-pink">{{ formatCurrency(totalContractValue) }}</strong>
                <span class="m-sub font-mono">{{ formatCurrency(monthlyAmount) }} / tháng</span>
              </div>
              <div class="metric-box">
                <span class="m-label">{{ t('admin.contracts.summaryDeposit') }}</span>
                <strong class="m-val font-mono">{{ formatCurrency(depositAmount) }}</strong>
                <span class="m-sub">{{ depositAmount > 0 ? 'Đã bao gồm cọc' : 'Không yêu cầu cọc' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <AuraButton variant="outline" type="button" @click="emit('close')">
            {{ t('common.cancel') }}
          </AuraButton>
          <AuraButton
            variant="primary"
            type="submit"
            class="btn-submit-action"
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
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99999;
  padding: 20px;
}

.modal-card {
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  width: 100%;
  max-width: 820px;
  max-height: 86vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
  overflow: hidden;
  animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
  from {
    opacity: 0;
    transform: scale(0.97) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline);
  background: var(--surface);
  flex-shrink: 0;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 14px;
}

.header-icon-badge {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.12);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-title-icon {
  font-size: 24px;
}

.title-text-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.01em;
}

.modal-subtitle-badge {
  font-size: 11px;
  color: var(--muted);
  font-weight: 500;
}

.modal-close-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: transparent;
  border: 1px solid transparent;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}

.modal-close-btn:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  border-color: var(--outline);
  color: var(--ink);
}

.create-form {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-section-card {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  border-radius: 14px;
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 800;
  color: var(--ink);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.sec-icon {
  font-size: 18px;
  color: var(--pink-dark, #db2777);
}

.grid-2-col {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 4px;
}

.required-star {
  color: var(--pink-dark, #db2777);
  font-weight: bold;
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

.input-with-icon {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.input-lead-icon {
  position: absolute;
  left: 12px;
  font-size: 18px;
  color: var(--muted);
  pointer-events: none;
}

.form-input {
  width: 100%;
  height: 42px;
  padding: 0 14px 0 38px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  transition: all 0.15s;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

.text-bold {
  font-weight: 700;
}

.currency-wrap .form-input {
  padding-right: 70px;
}

.input-unit-tag {
  position: absolute;
  right: 12px;
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  pointer-events: none;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  padding: 2px 6px;
  border-radius: 4px;
}

.preset-chips-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 4px;
}

.preset-chip {
  padding: 4px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.12s ease;
}

.preset-chip:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.preset-chip.active {
  background: rgba(219, 39, 119, 0.1);
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
  font-weight: 700;
}

.summary-estimate-card {
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(219, 39, 119, 0.06) 0%, rgba(219, 39, 119, 0.02) 100%);
  border: 1px solid rgba(219, 39, 119, 0.25);
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.summary-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.summary-title {
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--ink);
}

.summary-metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.metric-box {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.m-label {
  font-size: 10px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
}

.m-val {
  font-size: 13px;
  font-weight: 800;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.m-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface);
  flex-shrink: 0;
}

.btn-submit-action {
  background: linear-gradient(135deg, #db2777 0%, #be185d 100%) !important;
  color: #fff !important;
  box-shadow: 0 4px 14px rgba(219, 39, 119, 0.35);
  font-weight: 700;
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

@media (max-width: 700px) {
  .grid-2-col {
    grid-template-columns: 1fr;
  }
  .summary-metrics-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
