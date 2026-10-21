<script setup lang="ts">
import { computed } from 'vue'
import {
  AuraButton,
  DatePicker,
  SelectDropdown,
  TimePicker,
  type DropdownOption,
} from '@/shared/components'
import { formatCurrency } from '@/i18n'
import type { Court } from '@/features/court/types/court'
import type { RecurringFrequency, WeekdayOption } from '../../types/recurring'

const props = defineProps<{
  courts: Court[]
  selectedCourtId: number
  selectedCourt: Court | null
  frequency: RecurringFrequency
  selectedWeekdays: number[]
  startTime: string
  endTime: string
  startDate: string
  endDate: string
  weekdayButtons: WeekdayOption[]
  sessionDurationHours: number
  hourlyRate: number | null
  previewSessions: Array<{
    date: string
    weekdayName: string
    time: string
    conflict: boolean
    conflictReason: string | null
  }>
  estimatedSessionsCount: number
  estimatedTotalAmount: number | null
  submitting?: boolean
  submitDisabled?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:selectedCourtId', id: number): void
  (e: 'update:frequency', freq: RecurringFrequency): void
  (e: 'update:startTime', val: string): void
  (e: 'update:endTime', val: string): void
  (e: 'update:startDate', val: string): void
  (e: 'update:endDate', val: string): void
  (e: 'toggleWeekday', dayId: number): void
  (e: 'submit'): void
}>()

const courtOptions = computed<DropdownOption[]>(() =>
  props.courts.map((c) => ({
    value: c.id,
    label: c.name,
    sublabel: c.venue ? `— ${c.venue.name}` : undefined,
    icon: 'sports_tennis',
  })),
)

function formatMoney(amount: number | null): string {
  return amount === null ? '—' : formatCurrency(amount)
}
</script>

<template>
  <div class="recurring-wizard-card">
    <div class="wizard-header">
      <div class="header-icon-box">
        <span class="material-symbols-outlined icon-heading">event_repeat</span>
      </div>
      <div>
        <h3 class="wizard-title">Tạo Lịch Định Kỳ Mới</h3>
        <p class="wizard-sub">
          Giữ sân cố định theo tuần hoặc tháng, tự động tạo hóa đơn và lịch chơi.
        </p>
      </div>
    </div>

    <form class="wizard-form" @submit.prevent="emit('submit')">
      <!-- Step 1: Court Selection -->
      <div class="form-section">
        <div class="section-title-row">
          <span class="section-badge">1</span>
          <span class="section-label">Chọn Sân Thể Thao</span>
        </div>

        <div class="form-group">
          <label class="form-label">Sân và Cơ Sở:</label>
          <SelectDropdown
            :model-value="selectedCourtId"
            :options="courtOptions"
            variant="bordered"
            placeholder="Chọn sân thể thao..."
            aria-label="Chọn Sân Thể Thao"
            @update:model-value="emit('update:selectedCourtId', Number($event))"
          />
        </div>

        <!-- Court Preview Mini Card -->
        <div v-if="selectedCourt" class="court-preview-pill">
          <span class="material-symbols-outlined court-icon">sports_tennis</span>
          <div class="court-info">
            <span class="court-name">{{ selectedCourt.name }}</span>
            <span v-if="selectedCourt.venue" class="court-venue">
              <span class="material-symbols-outlined loc-icon">location_on</span>
              {{ selectedCourt.venue.name }}
            </span>
          </div>
          <div v-if="hourlyRate" class="court-rate font-mono">{{ formatMoney(hourlyRate) }}/h</div>
        </div>
      </div>

      <!-- Step 2: Frequency & Weekdays -->
      <div class="form-section">
        <div class="section-title-row">
          <span class="section-badge">2</span>
          <span class="section-label">Tần Suất & Ngày Trong Tuần</span>
        </div>

        <div class="form-group">
          <label class="form-label">Tần suất chu kỳ:</label>
          <div class="frequency-tabs">
            <button
              type="button"
              class="freq-btn"
              :class="{ active: frequency === 'weekly' }"
              @click="emit('update:frequency', 'weekly')"
            >
              Hàng tuần
            </button>
            <button
              type="button"
              class="freq-btn"
              :class="{ active: frequency === 'biweekly' }"
              @click="emit('update:frequency', 'biweekly')"
            >
              2 tuần / lần
            </button>
            <button
              type="button"
              class="freq-btn"
              :class="{ active: frequency === 'monthly' }"
              @click="emit('update:frequency', 'monthly')"
            >
              Hàng tháng
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Các ngày chơi trong tuần:</label>
          <div class="weekdays-row">
            <button
              v-for="btn in weekdayButtons"
              :key="btn.id"
              type="button"
              class="weekday-circle-btn"
              :class="{ active: selectedWeekdays.includes(btn.id) }"
              :title="btn.fullName"
              @click="emit('toggleWeekday', btn.id)"
            >
              {{ btn.label }}
            </button>
          </div>
          <span class="hint-text">
            Đã chọn:
            {{
              selectedWeekdays
                .map((id) => weekdayButtons.find((w) => w.id === id)?.name)
                .join(', ') || 'Chưa chọn'
            }}
          </span>
        </div>
      </div>

      <!-- Step 3: Time & Horizon -->
      <div class="form-section">
        <div class="section-title-row">
          <span class="section-badge">3</span>
          <span class="section-label">Khung Giờ & Thời Gian Áp Dụng</span>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Giờ bắt đầu:</label>
            <TimePicker
              :model-value="startTime"
              placeholder="00:00"
              @update:model-value="emit('update:startTime', $event)"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Giờ kết thúc:</label>
            <TimePicker
              :model-value="endTime"
              placeholder="00:00"
              @update:model-value="emit('update:endTime', $event)"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Ngày bắt đầu:</label>
            <DatePicker
              :model-value="startDate"
              placeholder="Chọn ngày bắt đầu"
              @update:model-value="emit('update:startDate', $event)"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Ngày kết thúc:</label>
            <DatePicker
              :model-value="endDate"
              :min="startDate"
              placeholder="Chọn ngày kết thúc"
              @update:model-value="emit('update:endDate', $event)"
            />
          </div>
        </div>

        <div class="duration-badge">
          <span class="material-symbols-outlined dur-icon">schedule</span>
          <span
            >Thời lượng mỗi buổi: <strong>{{ sessionDurationHours }} giờ</strong> ({{ startTime }} -
            {{ endTime }})</span
          >
        </div>
      </div>

      <!-- Step 4: Live Estimate Summary Card -->
      <div class="estimate-summary-box">
        <div class="estimate-header">
          <span class="material-symbols-outlined est-icon">calculate</span>
          <span class="estimate-title">Dự Toán Hợp Đồng Định Kỳ</span>
        </div>

        <div class="estimate-grid">
          <div class="estimate-item">
            <span class="est-label">Tổng số buổi dự kiến</span>
            <span class="est-value font-mono">{{ estimatedSessionsCount }} buổi</span>
          </div>

          <div class="estimate-item">
            <span class="est-label">Thời lượng mỗi buổi</span>
            <span class="est-value font-mono">{{ sessionDurationHours }}h</span>
          </div>

          <div class="estimate-item">
            <span class="est-label">Giá thuê từ</span>
            <span class="est-value font-mono">{{ formatMoney(hourlyRate) }}</span>
          </div>

          <div class="estimate-item total-item">
            <span class="est-label">Tổng giá trị dự tính</span>
            <span class="est-value-total font-mono">{{ formatMoney(estimatedTotalAmount) }}</span>
          </div>
        </div>

        <!-- Preview Sessions Chips -->
        <div v-if="previewSessions.length > 0" class="preview-sessions-wrap">
          <span class="preview-label">Xem trước các buổi chơi đầu tiên:</span>
          <div class="sessions-chips-row">
            <span
              v-for="(s, idx) in previewSessions.slice(0, 4)"
              :key="idx"
              class="session-chip"
              :class="{ 'session-chip--conflict': s.conflict }"
              :title="s.conflictReason || undefined"
            >
              <span v-if="s.conflict" class="material-symbols-outlined chip-warn-icon"
                >warning</span
              >
              {{ s.weekdayName }} {{ s.date.slice(5) }} ({{ s.time }})
            </span>
            <span v-if="previewSessions.length > 4" class="more-chip">
              +{{ previewSessions.length - 4 }} buổi khác
            </span>
          </div>
        </div>
      </div>

      <!-- Submit Action -->
      <AuraButton
        type="submit"
        variant="primary"
        size="lg"
        icon="event_available"
        :loading="submitting"
        :disabled="submitDisabled"
        class="submit-action-btn"
      >
        <span>Đặt Lịch Cố Định Ngay</span>
      </AuraButton>
    </form>
  </div>
</template>

<style scoped>
.recurring-wizard-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.wizard-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--outline);
}

.header-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-heading {
  font-size: 24px;
  color: var(--pink-dark, #db2777);
}

.wizard-title {
  margin: 0 0 2px;
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
}

.wizard-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.wizard-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}

.section-label {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: var(--pink-dark, #db2777);
}

.court-preview-pill {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.court-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.court-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.court-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.court-venue {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--muted);
}

.loc-icon {
  font-size: 13px;
}

.court-rate {
  font-size: 13px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.frequency-tabs {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.freq-btn {
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.freq-btn:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
}

.freq-btn.active {
  background: var(--pink-dark, #db2777);
  border-color: var(--pink-dark, #db2777);
  color: #fff;
}

.weekdays-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.weekday-circle-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}

.weekday-circle-btn:hover {
  border-color: var(--pink-dark, #db2777);
}

.weekday-circle-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 2px 8px rgba(219, 39, 119, 0.3);
}

.hint-text {
  font-size: 12px;
  color: var(--muted);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.form-group :deep(.date-picker),
.form-group :deep(.time-picker),
.form-group :deep(.custom-dropdown) {
  width: 100%;
  min-width: 0;
}

.form-group :deep(.date-picker-trigger) {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  font-weight: 500;
  box-sizing: border-box;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  min-height: 42px;
}

.form-group :deep(.date-picker-trigger:hover),
.form-group :deep(.date-picker-trigger:focus-visible) {
  border-color: var(--pink-dark, #db2777);
}

.form-group :deep(.date-picker-popover) {
  z-index: 100;
  left: 0;
  right: auto;
}

.form-group :deep(.time-picker-trigger) {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  font-weight: 500;
  box-sizing: border-box;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  min-height: 42px;
}

.form-group :deep(.time-picker-trigger:hover),
.form-group :deep(.time-picker-trigger:focus-visible) {
  border-color: var(--pink-dark, #db2777);
}

.form-group :deep(.time-picker-popover) {
  z-index: 100;
  left: 0;
  right: auto;
}

.form-group :deep(.custom-dropdown.bordered .custom-dropdown-trigger) {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  font-weight: 600;
  min-height: 42px;
  transition: border-color 0.2s ease;
}

.form-group :deep(.custom-dropdown.bordered .custom-dropdown-trigger:hover),
.form-group :deep(.custom-dropdown.open.bordered .custom-dropdown-trigger) {
  border-color: var(--pink-dark, #db2777);
}

.form-group :deep(.custom-dropdown-menu) {
  z-index: 100;
  width: 100%;
  max-width: 100%;
}

.duration-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
}

.dur-icon {
  font-size: 16px;
  color: #f59e0b;
}

.estimate-summary-box {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.estimate-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.est-icon {
  font-size: 18px;
  color: #10b981;
}

.estimate-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.estimate-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.estimate-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.est-label {
  font-size: 11px;
  color: var(--muted);
}

.est-value {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.total-item {
  grid-column: 1 / -1;
  padding-top: 8px;
  border-top: 1px dashed var(--outline);
}

.est-value-total {
  font-size: 18px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
}

.preview-sessions-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 8px;
  border-top: 1px solid var(--outline);
}

.preview-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted);
}

.sessions-chips-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.session-chip {
  padding: 3px 8px;
  border-radius: 6px;
  background: var(--surface);
  border: 1px solid var(--outline);
  font-size: 11px;
  font-weight: 600;
  color: var(--ink);
}

.session-chip--conflict {
  background: color-mix(in srgb, var(--danger, #e5484d) 12%, var(--surface));
  border-color: var(--danger, #e5484d);
  color: var(--danger, #e5484d);
}

.chip-warn-icon {
  font-size: 12px;
  vertical-align: -2px;
  margin-right: 2px;
}

.more-chip {
  padding: 3px 8px;
  font-size: 11px;
  color: var(--muted);
}

.submit-action-btn {
  width: 100%;
}
</style>
