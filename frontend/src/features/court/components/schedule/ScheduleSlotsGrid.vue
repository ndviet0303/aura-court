<script setup lang="ts">
import type { AvailabilitySlot } from '../../types/court'

defineProps<{
  slots: AvailabilitySlot[]
  selectedSlots: AvailabilitySlot[]
}>()

const emit = defineEmits<{
  (e: 'toggleSlot', slot: AvailabilitySlot): void
}>()

function isSelected(slot: AvailabilitySlot, selected: AvailabilitySlot[]) {
  return selected.some((s) => s.startsAt === slot.startsAt)
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="schedule-slots-section">
    <div class="section-legend">
      <div class="legend-item"><span class="box-legend available"></span> Còn trống</div>
      <div class="legend-item"><span class="box-legend selected"></span> Đang chọn</div>
      <div class="legend-item"><span class="box-legend occupied"></span> Đã có người đặt</div>
    </div>

    <div v-if="slots.length === 0" class="empty-slots">
      <span>🏸</span>
      <p>Chưa có dữ liệu khung giờ cho ngày đã chọn.</p>
    </div>

    <div v-else class="slots-grid">
      <button
        v-for="slot in slots"
        :key="slot.startsAt"
        type="button"
        class="slot-cell-btn"
        :class="{
          'is-available': slot.available,
          'is-occupied': !slot.available,
          'is-selected': isSelected(slot, selectedSlots)
        }"
        :disabled="!slot.available"
        @click="emit('toggleSlot', slot)"
      >
        <span class="s-time font-mono">{{ slot.startsAt.slice(11, 16) }} - {{ slot.endsAt.slice(11, 16) }}</span>
        <span class="s-price font-mono">{{ formatMoney(slot.price) }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.schedule-slots-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.section-legend {
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: var(--muted);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.box-legend {
  width: 14px;
  height: 14px;
  border-radius: 4px;
}

.box-legend.available { background: var(--surface-secondary, rgba(0, 0, 0, 0.05)); border: 1px solid var(--outline); }
.box-legend.selected { background: var(--accent, #C9A15A); }
.box-legend.occupied { background: #fee2e2; }

.slots-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 12px;
}

.slot-cell-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 14px 10px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.slot-cell-btn.is-available:hover {
  border-color: var(--accent, #C9A15A);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(201, 161, 90, 0.15);
}

.slot-cell-btn.is-selected {
  background: linear-gradient(135deg, #dfba72 0%, #c9a15a 100%) !important;
  border-color: #b88e45 !important;
  color: #1c1202 !important;
  font-weight: 800;
  box-shadow: 0 4px 16px rgba(201, 161, 90, 0.4) !important;
  transform: translateY(-2px);
}

.slot-cell-btn.is-selected .s-price {
  color: #3b2507 !important;
  font-weight: 700;
}

:global(html.dark) .slot-cell-btn.is-selected {
  background: linear-gradient(135deg, #e5c382 0%, #d8b46e 100%) !important;
  border-color: #fae0a2 !important;
  color: #1c1202 !important;
  box-shadow: 0 4px 20px rgba(216, 180, 110, 0.5) !important;
}

:global(html.dark) .slot-cell-btn.is-selected .s-price {
  color: #241402 !important;
  font-weight: 700;
}

.slot-cell-btn.is-occupied {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-color: transparent;
  color: var(--muted);
  cursor: not-allowed;
  opacity: 0.6;
  text-decoration: line-through;
}

.s-time { font-size: 12px; font-weight: 700; }
.s-price { font-size: 11px; }

.empty-slots {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 20px;
  color: var(--muted);
  font-size: 13px;
}
</style>
