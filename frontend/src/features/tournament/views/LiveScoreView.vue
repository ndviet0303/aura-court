<script setup lang="ts">
import { useLiveScore } from '../composables/useLiveScore'

import LiveFixturesSelector from '../components/live/LiveFixturesSelector.vue'
import LiveMatchScoreboard from '../components/live/LiveMatchScoreboard.vue'
import LiveMatchStatsCard from '../components/live/LiveMatchStatsCard.vue'
import LivePlayByPlayTicker from '../components/live/LivePlayByPlayTicker.vue'
import LiveUmpireControlPanel from '../components/live/LiveUmpireControlPanel.vue'

const {
  isUmpireMode,
  notice,
  fixtures,
  selectedFixtureId,
  activeMatch,
  playEvents,
  addPoint,
  undoPoint,
} = useLiveScore()
</script>

<template>
  <div class="live-score-view">
    <!-- Header Section -->
    <header class="live-header">
      <div class="header-titles">
        <h1 class="page-title">Live Score & Tỉ Số Trực Tiếp</h1>
        <p class="page-sub">Cập nhật điểm số từng pha bóng, tốc độ và phân tích thống kê giải đấu theo thời gian thực.</p>
      </div>

      <div class="header-actions">
        <button
          class="btn-umpire-toggle"
          :class="{ active: isUmpireMode }"
          @click="isUmpireMode = !isUmpireMode"
        >
          🎛️ {{ isUmpireMode ? 'Đóng Chế Độ Trọng Tài' : 'Bật Chế Độ Trọng Tài (Umpire)' }}
        </button>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>

    <!-- Active Fixtures Selector Bar -->
    <LiveFixturesSelector
      :fixtures="fixtures"
      :selected-id="selectedFixtureId"
      @select="selectedFixtureId = $event"
    />

    <!-- Main Scoreboard Display -->
    <LiveMatchScoreboard
      v-if="activeMatch"
      :match="activeMatch"
    />

    <!-- Umpire Controls (when activated) -->
    <LiveUmpireControlPanel
      v-if="isUmpireMode && activeMatch"
      :match="activeMatch"
      @add-point="addPoint"
      @undo-point="undoPoint"
    />

    <!-- Analytics & Ticker Grid -->
    <div v-if="activeMatch" class="live-analytics-grid">
      <LiveMatchStatsCard :match="activeMatch" />
      <LivePlayByPlayTicker :events="playEvents" />
    </div>
  </div>
</template>

<style scoped>
.live-score-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1300px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.live-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.page-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.btn-umpire-toggle {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-umpire-toggle.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.live-analytics-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

@media (max-width: 850px) {
  .live-analytics-grid {
    grid-template-columns: 1fr;
  }
}
</style>
