<script setup lang="ts">
import { computed } from 'vue'
import { SelectDropdown, DatePicker, TimePicker, type DropdownOption } from '@/shared/components'
import type { Court } from '@/features/court/types/court'

const props = defineProps<{
  courts: Court[]
  courtOptions: DropdownOption[]
  selectedVenueName: string
  selectedCourtId: number | null
  matchDate: string
  matchTime: string
  duration: number
  quickVenues: Array<{ name: string; slug: string; city: string }>
}>()

const emit = defineEmits<{
  (e: 'update:selectedVenueName', name: string): void
  (e: 'update:selectedCourtId', id: number): void
  (e: 'update:matchDate', date: string): void
  (e: 'update:matchTime', time: string): void
  (e: 'update:duration', hours: number): void
  (e: 'selectQuickVenue', venueName: string): void
}>()

const durationOptions: DropdownOption[] = [
  { value: 1, label: '1 tiếng (60 phút)', icon: 'timer' },
  { value: 1.5, label: '1.5 tiếng (90 phút)', icon: 'timer' },
  { value: 2, label: '2 tiếng (120 phút)', icon: 'timer' },
  { value: 2.5, label: '2.5 tiếng (150 phút)', icon: 'timer' },
  { value: 3, label: '3 tiếng (180 phút)', icon: 'timer' },
  { value: 4, label: '4 tiếng (240 phút)', icon: 'timer' },
]

const todayMin = computed(() => {
  return new Date().toISOString().slice(0, 10)
})
</script>

<template>
  <div class="match-location-section">
    <div class="section-head">
      <div class="section-title-wrap">
        <span class="material-symbols-outlined head-icon">location_on</span>
        <h3 class="section-title">Địa Điểm & Thời Gian Trận Đấu</h3>
      </div>
      <span class="section-desc">Chọn cụm sân Aura và khung giờ diễn ra trận đấu</span>
    </div>

    <!-- Quick Venues Selection -->
    <div class="quick-venues-box">
      <span class="quick-label">Cụm sân gợi ý:</span>
      <div class="quick-pills-row">
        <button
          v-for="v in quickVenues"
          :key="v.slug"
          type="button"
          class="quick-pill"
          :class="{ active: selectedVenueName === v.name }"
          @click="emit('selectQuickVenue', v.name)"
        >
          <span class="material-symbols-outlined pill-ico">domain</span>
          <span>{{ v.name }}</span>
        </button>
      </div>
    </div>

    <div class="form-grid">
      <!-- Venue Name Input -->
      <div class="form-field-group">
        <label for="venue-name-input" class="field-label">
          <span class="material-symbols-outlined label-ico">apartment</span>
          Tên địa điểm / Trung tâm thể thao
        </label>
        <input
          id="venue-name-input"
          :value="selectedVenueName"
          type="text"
          class="styled-input"
          placeholder="Nhập tên cụm sân..."
          @input="emit('update:selectedVenueName', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <!-- Court Dropdown -->
      <div class="form-field-group">
        <label class="field-label">
          <span class="material-symbols-outlined label-ico">stadium</span>
          Chọn sân cụ thể (tùy chọn)
        </label>
        <SelectDropdown
          :model-value="selectedCourtId || 0"
          :options="courtOptions"
          variant="bordered"
          placeholder="Chọn sân..."
          @update:model-value="emit('update:selectedCourtId', Number($event))"
        />
      </div>

      <!-- Match Date (DatePicker) -->
      <div class="form-field-group">
        <label class="field-label">
          <span class="material-symbols-outlined label-ico">calendar_month</span>
          Ngày giao lưu
        </label>
        <div class="picker-container">
          <DatePicker
            :model-value="matchDate"
            :min="todayMin"
            placeholder="Chọn ngày thi đấu"
            @update:model-value="emit('update:matchDate', $event)"
          />
        </div>
      </div>

      <!-- Match Time (TimePicker) & Duration -->
      <div class="form-field-row">
        <div class="form-field-group flex-1">
          <label class="field-label">
            <span class="material-symbols-outlined label-ico">schedule</span>
            Giờ bắt đầu
          </label>
          <div class="picker-container">
            <TimePicker
              :model-value="matchTime"
              placeholder="Chọn giờ"
              @update:model-value="emit('update:matchTime', $event)"
            />
          </div>
        </div>

        <div class="form-field-group flex-1">
          <label class="field-label">
            <span class="material-symbols-outlined label-ico">timer</span>
            Thời lượng
          </label>
          <SelectDropdown
            :model-value="duration"
            :options="durationOptions"
            variant="bordered"
            placeholder="Chọn thời lượng"
            @update:model-value="emit('update:duration', Number($event))"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.match-location-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 22px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 18px;
  box-shadow: var(--shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.04));
}

.section-head {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.section-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.head-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.section-title {
  margin: 0;
  font-size: 15.5px;
  font-weight: 700;
  color: var(--ink);
}

.section-desc {
  font-size: 12.5px;
  color: var(--muted);
}

.quick-venues-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 14px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border-radius: 14px;
  border: 1px dashed var(--outline);
}

.quick-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.quick-pills-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.quick-pill {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.quick-pill:hover {
  border-color: rgba(219, 39, 119, 0.4);
  background: rgba(219, 39, 119, 0.04);
}

.quick-pill.active {
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  border-color: var(--pink-dark, #db2777);
}

.pill-ico {
  font-size: 15px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.form-field-row {
  display: flex;
  gap: 12px;
  grid-column: span 1;
}

.flex-1 {
  flex: 1;
  min-width: 0;
}

.form-field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.label-ico {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
}

.styled-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 600;
  outline: none;
  font-family: inherit;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.styled-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

.picker-container {
  width: 100%;
}

@media (max-width: 680px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .form-field-row {
    flex-direction: column;
  }
}
</style>
