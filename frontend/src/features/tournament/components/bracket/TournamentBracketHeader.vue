<script setup lang="ts">
import type { TournamentItem } from '../../composables/useTournamentBracket'

defineProps<{
  tournament: TournamentItem
  tournaments: TournamentItem[]
  selectedTournamentId: number
  selectedCategory: 'singles_men' | 'doubles_men' | 'mixed_doubles'
  activeTab: 'bracket' | 'standings'
}>()

const emit = defineEmits<{
  (e: 'update:selectedTournamentId', id: number): void
  (e: 'update:selectedCategory', cat: 'singles_men' | 'doubles_men' | 'mixed_doubles'): void
  (e: 'update:activeTab', tab: 'bracket' | 'standings'): void
}>()
</script>

<template>
  <div class="bracket-header-card">
    <div class="header-main-row">
      <div class="t-info">
        <div class="title-row">
          <h1 class="t-title">{{ tournament.name }}</h1>
          <span class="status-pill font-mono">● {{ tournament.status.toUpperCase() }}</span>
        </div>
        <p class="t-sub">📍 {{ tournament.location }} · 📅 {{ tournament.dates }} · 👥 {{ tournament.playersCount }} VĐV</p>
      </div>

      <div class="t-selectors">
        <select
          :value="selectedTournamentId"
          class="custom-select"
          @change="emit('update:selectedTournamentId', Number(($event.target as HTMLSelectElement).value))"
        >
          <option v-for="t in tournaments" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>

        <select
          :value="selectedCategory"
          class="custom-select"
          @change="emit('update:selectedCategory', ($event.target as HTMLSelectElement).value as any)"
        >
          <option value="singles_men">🏸 Đơn Nam (Men's Singles)</option>
          <option value="doubles_men">🏸 Đôi Nam (Men's Doubles)</option>
          <option value="mixed_doubles">🏸 Đôi Nam Nữ (Mixed Doubles)</option>
        </select>
      </div>
    </div>

    <!-- Tab navigation -->
    <div class="tabs-nav-row">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'bracket' }"
        @click="emit('update:activeTab', 'bracket')"
      >
        🌳 Sơ Đồ Nhánh Đấu (Knockout Bracket)
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'standings' }"
        @click="emit('update:activeTab', 'standings')"
      >
        📊 Bảng Điểm Vòng Bảng (Group Standings)
      </button>
    </div>
  </div>
</template>

<style scoped>
.bracket-header-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.header-main-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.t-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.t-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.status-pill {
  font-size: 11px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
}

.t-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.t-selectors {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.custom-select {
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.tabs-nav-row {
  display: flex;
  gap: 10px;
  border-top: 1px solid var(--outline);
  padding-top: 14px;
}

.tab-btn {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.tab-btn.active {
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  border-color: rgba(236, 72, 153, 0.2);
}
</style>
