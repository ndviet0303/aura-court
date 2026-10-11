<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { MatchFixture } from '../../composables/useLiveScore'

defineProps<{
  fixtures: MatchFixture[]
  selectedId: number
}>()

const emit = defineEmits<{
  (e: 'select', id: number): void
}>()

const scrollContainer = ref<HTMLElement | null>(null)
const isDragging = ref(false)
const hasDragged = ref(false)
const startX = ref(0)
const scrollLeft = ref(0)

function onMouseDown(e: MouseEvent) {
  if (!scrollContainer.value) return
  isDragging.value = true
  hasDragged.value = false
  startX.value = e.pageX - scrollContainer.value.offsetLeft
  scrollLeft.value = scrollContainer.value.scrollLeft
}

function onMouseMove(e: MouseEvent) {
  if (!isDragging.value || !scrollContainer.value) return
  e.preventDefault()
  const x = e.pageX - scrollContainer.value.offsetLeft
  const walk = (x - startX.value) * 1.3
  if (Math.abs(walk) > 5) {
    hasDragged.value = true
  }
  scrollContainer.value.scrollLeft = scrollLeft.value - walk
}

function onMouseUp() {
  isDragging.value = false
  setTimeout(() => {
    hasDragged.value = false
  }, 60)
}

function onMouseLeave() {
  isDragging.value = false
}

function handleSelect(id: number) {
  if (hasDragged.value) return
  emit('select', id)
}

function scroll(direction: 'left' | 'right') {
  if (!scrollContainer.value) return
  const offset = direction === 'left' ? -280 : 280
  scrollContainer.value.scrollBy({ left: offset, behavior: 'smooth' })
}

function formatName(name: string): string {
  if (!name) return 'Chờ xác định'
  return name
}
</script>

<template>
  <div class="fixtures-selector-container">
    <!-- Header with label & quick scroll buttons -->
    <div class="selector-header">
      <div class="hdr-left">
        <span class="material-symbols-outlined hdr-ico">sports_tennis</span>
        <span class="hdr-title">Danh Sách Trận Đấu Sân</span>
        <span class="count-badge font-mono">{{ fixtures.length }} trận</span>
        <span class="drag-hint">
          <span class="material-symbols-outlined drag-ico">swipe</span>
          Kéo ngang để xem
        </span>
      </div>

      <div class="scroll-arrows">
        <button type="button" class="arrow-btn" aria-label="Cuộn sang trái" @click="scroll('left')">
          <span class="material-symbols-outlined">chevron_left</span>
        </button>
        <button
          type="button"
          class="arrow-btn"
          aria-label="Cuộn sang phải"
          @click="scroll('right')"
        >
          <span class="material-symbols-outlined">chevron_right</span>
        </button>
      </div>
    </div>

    <!-- Scrollable & Draggable Match Chips Track -->
    <div
      ref="scrollContainer"
      class="fixtures-selector-bar"
      :class="{ 'is-dragging': isDragging }"
      @mousedown="onMouseDown"
      @mousemove="onMouseMove"
      @mouseup="onMouseUp"
      @mouseleave="onMouseLeave"
    >
      <button
        v-for="f in fixtures"
        :key="f.id"
        type="button"
        class="fixture-card-chip"
        :class="{
          active: f.id === selectedId,
          'is-live': f.status === 'live',
          'is-completed': f.status === 'completed',
        }"
        @click="handleSelect(f.id)"
      >
        <!-- Top Meta Row: Live Status & Court -->
        <div class="chip-top-row">
          <span v-if="f.status === 'live'" class="live-pill">
            <span class="live-dot-pulse"></span>
            LIVE
          </span>
          <span v-else-if="f.status === 'completed'" class="status-pill completed"> KẾT THÚC </span>
          <span v-else class="status-pill scheduled"> SẮP ĐẤU </span>

          <span class="court-badge">
            <span class="material-symbols-outlined mini-ico">location_on</span>
            {{ f.court_label }}
          </span>

          <span class="round-badge">{{ f.round_label }}</span>
        </div>

        <!-- Matchup Rows: Player 1 & Player 2 with Real-time Scores -->
        <div class="chip-matchup">
          <div
            class="player-row"
            :class="{
              leader: (f.player1.current_score || 0) > (f.player2.current_score || 0),
              winner:
                f.status === 'completed' &&
                (f.player1.current_score || 0) > (f.player2.current_score || 0),
            }"
          >
            <div class="p-info">
              <img
                :src="f.player1.avatar"
                :alt="f.player1.name"
                class="p-mini-avt"
                loading="lazy"
              />
              <span class="p-name" :title="f.player1.name">{{ formatName(f.player1.name) }}</span>
            </div>
            <span class="p-score font-mono">{{ f.player1.current_score ?? 0 }}</span>
          </div>

          <div
            class="player-row"
            :class="{
              leader: (f.player2.current_score || 0) > (f.player1.current_score || 0),
              winner:
                f.status === 'completed' &&
                (f.player2.current_score || 0) > (f.player1.current_score || 0),
            }"
          >
            <div class="p-info">
              <img
                :src="f.player2.avatar"
                :alt="f.player2.name"
                class="p-mini-avt"
                loading="lazy"
              />
              <span class="p-name" :title="f.player2.name">{{ formatName(f.player2.name) }}</span>
            </div>
            <span class="p-score font-mono">{{ f.player2.current_score ?? 0 }}</span>
          </div>
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.fixtures-selector-container {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}

.selector-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 4px;
}

.hdr-left {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.hdr-ico {
  font-size: 18px;
  color: var(--pink-dark, #db2777);
}

.hdr-title {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ink);
}

.count-badge {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  background: var(--surface-container, rgba(0, 0, 0, 0.05));
  padding: 2px 8px;
  border-radius: 999px;
}

.drag-hint {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  color: var(--muted);
  margin-left: 6px;
  opacity: 0.85;
}

.drag-ico {
  font-size: 14px;
}

.scroll-arrows {
  display: flex;
  align-items: center;
  gap: 6px;
}

.arrow-btn {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  display: grid;
  place-items: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.arrow-btn:hover {
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  color: var(--ink);
  border-color: var(--pink-dark, #db2777);
}

.arrow-btn span {
  font-size: 18px;
}

/* Scrollable & Draggable Bar */
.fixtures-selector-bar {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding: 4px 2px 10px;
  scroll-behavior: smooth;
  cursor: grab;
  user-select: none;
  -webkit-user-select: none;
}

.fixtures-selector-bar.is-dragging {
  cursor: grabbing;
  scroll-behavior: auto;
}

.fixtures-selector-bar::-webkit-scrollbar {
  height: 5px;
}

.fixtures-selector-bar::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.04);
  border-radius: 999px;
}

:global(html.dark .fixtures-selector-bar::-webkit-scrollbar-track) {
  background: rgba(255, 255, 255, 0.06);
}

.fixtures-selector-bar::-webkit-scrollbar-thumb {
  background: rgba(219, 39, 119, 0.3);
  border-radius: 999px;
}

.fixtures-selector-bar::-webkit-scrollbar-thumb:hover {
  background: var(--pink-dark, #db2777);
}

/* Individual Fixture Card Chip */
.fixture-card-chip {
  flex-shrink: 0;
  width: 240px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 14px;
  border-radius: 14px;
  border: 1.5px solid var(--outline);
  background: var(--surface);
  cursor: pointer;
  text-align: left;
  transition:
    transform 0.18s ease,
    border-color 0.18s ease,
    box-shadow 0.18s ease;
  position: relative;
  user-select: none;
}

.fixture-card-chip:hover {
  transform: translateY(-2px);
  border-color: rgba(219, 39, 119, 0.4);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
}

.fixture-card-chip.active {
  border-color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.06);
  box-shadow:
    0 0 0 1px var(--pink-dark, #db2777),
    0 8px 22px rgba(219, 39, 119, 0.15);
}

/* Top Meta Row */
.chip-top-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  font-size: 11px;
  pointer-events: none;
}

.live-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 10px;
  font-weight: 800;
  color: #ef4444;
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.3);
  padding: 2px 7px;
  border-radius: 999px;
  letter-spacing: 0.5px;
}

.live-dot-pulse {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ef4444;
  box-shadow: 0 0 8px #ef4444;
  animation: pulse-dot 1.4s ease-in-out infinite;
}

@keyframes pulse-dot {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.4);
    opacity: 0.5;
  }
}

.status-pill {
  font-size: 10px;
  font-weight: 750;
  padding: 2px 7px;
  border-radius: 999px;
  letter-spacing: 0.3px;
}

.status-pill.completed {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.status-pill.scheduled {
  background: rgba(148, 163, 184, 0.15);
  color: var(--muted);
}

.court-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 11px;
  font-weight: 700;
  color: var(--ink);
}

.mini-ico {
  font-size: 13px;
  color: var(--muted);
}

.round-badge {
  font-size: 10.5px;
  color: var(--muted);
  margin-left: auto;
}

/* Matchup Player Rows */
.chip-matchup {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-top: 4px;
  border-top: 1px solid var(--outline);
  pointer-events: none;
}

.player-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 2px 0;
}

.p-info {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
  flex: 1;
}

.p-mini-avt {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid var(--outline);
}

.p-name {
  font-size: 12.5px;
  font-weight: 650;
  color: var(--ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.p-score {
  font-size: 13px;
  font-weight: 750;
  color: var(--muted);
  padding: 1px 6px;
  border-radius: 4px;
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
}

.player-row.leader .p-name {
  font-weight: 800;
  color: var(--ink);
}

.player-row.leader .p-score {
  color: var(--pink-dark, #db2777);
  font-weight: 800;
  background: rgba(219, 39, 119, 0.1);
}

.player-row.winner .p-name {
  color: var(--pink-dark, #db2777);
}
</style>
