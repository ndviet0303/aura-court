<script setup lang="ts">
import type { Court } from '@/features/court/types/court'

defineProps<{
  courts: Court[]
  selectedVenueName: string
  selectedCourtId: number
  matchDate: string
  matchTime: string
  duration: number
  quickVenues: Array<{ name: string; slug: string }>
}>()

const emit = defineEmits<{
  (e: 'update:selectedVenueName', name: string): void
  (e: 'update:selectedCourtId', id: number): void
  (e: 'update:matchDate', date: string): void
  (e: 'update:matchTime', time: string): void
  (e: 'update:duration', hours: number): void
}>()
</script>

<template>
  <div class="match-location-card">
    <div class="card-header">
      <h3 class="title">📍 Địa Điểm & Thời Gian</h3>
      <p class="sub">Chọn cụm sân Aura và khung giờ dự kiến tổ chức trận đấu.</p>
    </div>

    <!-- Quick venue pills -->
    <div class="quick-venues-row">
      <button
        v-for="v in quickVenues"
        :key="v.slug"
        type="button"
        class="quick-venue-pill"
        :class="{ active: selectedVenueName === v.name }"
        @click="emit('update:selectedVenueName', v.name)"
      >
        🏢 {{ v.name }}
      </button>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Chọn sân:</label>
        <select
          :value="selectedCourtId"
          class="form-input"
          @change="emit('update:selectedCourtId', Number(($event.target as HTMLSelectElement).value))"
        >
          <option v-for="c in courts" :key="c.id" :value="c.id">
            {{ c.name }} {{ c.venue ? `(${c.venue.name})` : '' }}
          </option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Ngày giao lưu:</label>
        <input
          :value="matchDate"
          type="date"
          class="form-input"
          @input="emit('update:matchDate', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="form-group">
        <label class="form-label">Giờ bắt đầu:</label>
        <input
          :value="matchTime"
          type="time"
          class="form-input"
          @input="emit('update:matchTime', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="form-group">
        <label class="form-label">Thời lượng (giờ):</label>
        <input
          :value="duration"
          type="number"
          min="1"
          max="8"
          class="form-input"
          @input="emit('update:duration', Number(($event.target as HTMLInputElement).value))"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.match-location-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.quick-venues-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.quick-venue-pill {
  padding: 6px 14px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.quick-venue-pill.active {
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  border-color: rgba(236, 72, 153, 0.3);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
}

@media (max-width: 600px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
