<script setup lang="ts">
import { ref } from 'vue'
import type { Court, Venue } from '../../types/court'

defineProps<{
  courts: Court[]
  venues?: Venue[]
  selectedCourtId?: number | null
  selectedVenueId?: number | null
}>()

const emit = defineEmits<{
  (e: 'selectCourt', id: number): void
  (e: 'selectVenue', id: number | null): void
}>()

const courtsScrollRef = ref<HTMLElement | null>(null)

function scrollCourts(direction: 'left' | 'right') {
  if (!courtsScrollRef.value) return
  const offset = direction === 'left' ? -280 : 280
  courtsScrollRef.value.scrollBy({ left: offset, behavior: 'smooth' })
}

function getCourtTypeTag(c: Court): string {
  if (c.sport_zone?.name) return c.sport_zone.name
  if (c.name.toLowerCase().includes('vip')) return 'Sân VIP'
  if (c.name.toLowerCase().includes('bwf') || c.name.toLowerCase().includes('thảm')) return 'Thảm BWF'
  if (c.name.toLowerCase().includes('khán đài')) return 'Khán Đài'
  return 'Tiêu chuẩn'
}
</script>

<template>
  <div class="court-selector-container">
    <!-- Optional Venue Filter Tabs -->
    <div v-if="venues && venues.length > 1" class="venue-filter-row">
      <span class="filter-lbl">
        <span class="material-symbols-outlined icon-14">apartment</span>
        <span>Cơ sở:</span>
      </span>
      <div class="venue-pills">
        <button
          type="button"
          class="venue-pill-btn"
          :class="{ active: !selectedVenueId }"
          @click="emit('selectVenue', null)"
        >
          Tất cả cơ sở
        </button>
        <button
          v-for="v in venues"
          :key="v.id"
          type="button"
          class="venue-pill-btn"
          :class="{ active: selectedVenueId === v.id }"
          @click="emit('selectVenue', v.id)"
        >
          {{ v.name }}
        </button>
      </div>
    </div>

    <!-- Courts Carousel with Non-overlapping Navigation -->
    <div class="courts-header-row">
      <label class="selector-lbl">
        <span class="material-symbols-outlined icon-18">sports_tennis</span>
        <span>Chọn sân thi đấu:</span>
        <span class="courts-count-pill">{{ courts.length }} sân</span>
      </label>
    </div>

    <div class="courts-carousel-wrapper">
      <button
        v-if="courts.length > 3"
        type="button"
        class="scroll-step-btn left"
        @click="scrollCourts('left')"
        aria-label="Cuộn sang trái"
      >
        <span class="material-symbols-outlined icon-16">arrow_back_ios_new</span>
      </button>

      <div ref="courtsScrollRef" class="courts-pills-row">
        <button
          v-for="c in courts"
          :key="c.id"
          type="button"
          class="court-card-btn"
          :class="{ active: selectedCourtId === c.id }"
          @click="emit('selectCourt', c.id)"
        >
          <div class="c-card-top">
            <span class="c-type-badge">{{ getCourtTypeTag(c) }}</span>
            <span v-if="selectedCourtId === c.id" class="material-symbols-outlined active-check">
              check_circle
            </span>
          </div>

          <strong class="c-name">{{ c.name }}</strong>
          <span class="c-venue">
            <span class="material-symbols-outlined icon-12">location_on</span>
            <span>{{ c.venue?.name || 'Aura Court' }}</span>
          </span>
        </button>
      </div>

      <button
        v-if="courts.length > 3"
        type="button"
        class="scroll-step-btn right"
        @click="scrollCourts('right')"
        aria-label="Cuộn sang phải"
      >
        <span class="material-symbols-outlined icon-16">arrow_forward_ios</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.court-selector-container {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.venue-filter-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-lbl {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  font-weight: 800;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.venue-pills {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
}

.venue-pills::-webkit-scrollbar {
  display: none;
}

.venue-pill-btn {
  padding: 6px 14px;
  border-radius: 999px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}

:global(html.dark:not([data-ui-version="v2"])) .venue-pill-btn {
  background: #1e191c;
  border-color: #382d33;
}

.venue-pill-btn:hover {
  border-color: var(--accent);
  color: var(--ink);
}

.venue-pill-btn.active {
  background: var(--primary);
  border-color: var(--primary);
  color: var(--on-primary);
  box-shadow: 0 4px 12px rgba(107, 45, 63, 0.25);
}

:global(html.dark:not([data-ui-version="v2"])) .venue-pill-btn.active {
  background: #e58e9f;
  color: #1c0a10;
  border-color: #e58e9f;
  box-shadow: 0 4px 12px rgba(229, 142, 159, 0.3);
}

.courts-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.selector-lbl {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ink);
}

.courts-count-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.05));
  color: var(--muted);
  border: 1px solid var(--outline);
}

.icon-18 {
  font-size: 18px;
  color: var(--primary);
}

.courts-carousel-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.scroll-step-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 68px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, var(--surface));
  color: var(--muted);
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.2s ease;
}

:global(html.dark:not([data-ui-version="v2"])) .scroll-step-btn {
  background: #231c20;
  border-color: #382d33;
}

.scroll-step-btn:hover {
  background: var(--primary-container);
  color: var(--primary);
  border-color: var(--primary);
}

:global(html.dark:not([data-ui-version="v2"])) .scroll-step-btn:hover {
  background: rgba(229, 142, 159, 0.2);
  color: #e58e9f;
}

.courts-pills-row {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding: 4px 2px;
  width: 100%;
  scrollbar-width: none;
}

.courts-pills-row::-webkit-scrollbar {
  display: none;
}

.court-card-btn {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  min-width: 220px;
  padding: 12px 16px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  text-align: left;
}

:global(html.dark:not([data-ui-version="v2"])) .court-card-btn {
  background: #1e191c;
  border-color: #382d33;
}

.court-card-btn:hover:not(.active) {
  border-color: var(--accent);
  background: var(--soft);
  transform: translateY(-2px);
}

:global(html.dark:not([data-ui-version="v2"])) .court-card-btn:hover:not(.active) {
  background: #282126;
  border-color: #d8b46e;
}

.court-card-btn.active {
  border-color: var(--primary);
  background: var(--primary-container);
  box-shadow: 0 4px 18px rgba(107, 45, 63, 0.15);
  transform: translateY(-2px);
}

:global(html.dark:not([data-ui-version="v2"])) .court-card-btn.active {
  border-color: #e58e9f;
  background: linear-gradient(145deg, #321922 0%, #201016 100%);
  box-shadow: 0 6px 24px rgba(229, 142, 159, 0.25);
}

.c-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  gap: 8px;
}

.c-type-badge {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  padding: 2px 7px;
  border-radius: 6px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.05));
  color: var(--muted);
  border: 1px solid var(--outline);
}

.court-card-btn.active .c-type-badge {
  background: var(--primary);
  color: var(--on-primary);
  border-color: transparent;
}

:global(html.dark:not([data-ui-version="v2"])) .court-card-btn.active .c-type-badge {
  background: #e58e9f;
  color: #1c0a10;
}

.active-check {
  font-size: 16px;
  color: var(--primary);
}

:global(html.dark:not([data-ui-version="v2"])) .active-check {
  color: #e58e9f;
}

.c-name {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ink);
}

.court-card-btn.active .c-name {
  color: var(--primary);
}

:global(html.dark:not([data-ui-version="v2"])) .court-card-btn.active .c-name {
  color: #f7a8b8;
}

.c-venue {
  display: flex;
  align-items: center;
  gap: 3px;
  font-size: 11.5px;
  color: var(--muted);
}

.icon-12 {
  font-size: 12px;
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}

@media (max-width: 640px) {
  .scroll-step-btn {
    display: none;
  }
  .court-card-btn {
    min-width: 190px;
    padding: 10px 14px;
  }
}
</style>
