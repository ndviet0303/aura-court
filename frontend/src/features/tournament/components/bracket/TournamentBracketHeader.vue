<script setup lang="ts">
import { computed } from 'vue'
import { SelectDropdown, AuraButton } from '@/shared/components'
import type { TournamentItem } from '../../composables/useTournamentBracket'

const props = defineProps<{
  tournament: TournamentItem | null
  tournaments: TournamentItem[]
  selectedTournamentId: number | null
  selectedCategory: 'singles_men' | 'doubles_men' | 'mixed_doubles'
  activeTab: 'bracket' | 'standings'
}>()


const emit = defineEmits<{
  (e: 'update:selectedTournamentId', id: number): void
  (e: 'update:selectedCategory', cat: 'singles_men' | 'doubles_men' | 'mixed_doubles'): void
  (e: 'update:activeTab', tab: 'bracket' | 'standings'): void
}>()

const tournamentOptions = computed(() => {
  return props.tournaments.map((t) => ({
    value: t.id,
    label: t.name,
    icon: 'emoji_events',
  }))
})

const categoryOptions = [
  { value: 'singles_men', label: 'Đơn Nam (Men\'s Singles)', icon: 'person' },
  { value: 'doubles_men', label: 'Đôi Nam (Men\'s Doubles)', icon: 'group' },
  { value: 'mixed_doubles', label: 'Đôi Nam Nữ (Mixed Doubles)', icon: 'diversity_3' },
]
</script>

<template>
  <header class="bracket-header-card">
    <div class="header-top-nav">
      <AuraButton to="/tournaments" variant="ghost" size="sm" icon="arrow_back">
        Quay lại Danh Sách Giải
      </AuraButton>
    </div>

    <div class="header-main-row">
      <div v-if="tournament" class="t-info">
        <div class="title-row">
          <h1 class="t-title">{{ tournament.name }}</h1>
          <span class="status-pill font-mono">
            <span class="status-dot"></span>
            {{ tournament.status.toUpperCase() }}
          </span>
        </div>
        <div class="meta-row">
          <span class="meta-tag">
            <span class="material-symbols-outlined meta-ico">location_on</span>
            {{ tournament.location }}
          </span>
          <span class="meta-tag">
            <span class="material-symbols-outlined meta-ico">calendar_month</span>
            {{ tournament.dates }}
          </span>
          <span class="meta-tag">
            <span class="material-symbols-outlined meta-ico">group</span>
            {{ tournament.playersCount }} VĐV tham gia
          </span>
        </div>
      </div>

      <div class="t-selectors">
        <div class="select-box">
          <SelectDropdown
            :model-value="selectedTournamentId"
            :options="tournamentOptions"
            variant="bordered"
            aria-label="Chọn giải đấu"
            @update:model-value="emit('update:selectedTournamentId', Number($event))"
          />
        </div>

        <div class="select-box">
          <SelectDropdown
            :model-value="selectedCategory"
            :options="categoryOptions"
            variant="bordered"
            aria-label="Chọn hạng mục thi đấu"
            @update:model-value="emit('update:selectedCategory', $event as any)"
          />
        </div>
      </div>
    </div>

    <!-- Tab navigation -->
    <div class="tabs-nav-row" role="tablist">
      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'bracket' }"
        role="tab"
        :aria-selected="activeTab === 'bracket'"
        @click="emit('update:activeTab', 'bracket')"
      >
        <span class="material-symbols-outlined tab-ico">account_tree</span>
        <span>Sơ Đồ Cây Nhánh Đấu (Knockout Bracket)</span>
      </button>
      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'standings' }"
        role="tab"
        :aria-selected="activeTab === 'standings'"
        @click="emit('update:activeTab', 'standings')"
      >
        <span class="material-symbols-outlined tab-ico">leaderboard</span>
        <span>Bảng Xếp Hạng Vòng Bảng (Standings)</span>
      </button>
    </div>
  </header>
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
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.header-top-nav {
  display: flex;
}

.header-main-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 20px;
}

.t-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 650px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.t-title {
  margin: 0;
  font-size: 22px;
  font-weight: 850;
  color: var(--ink);
  line-height: 1.3;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
}

.meta-row {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  font-size: 12.5px;
  color: var(--muted);
}

.meta-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.meta-ico {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
}

.t-selectors {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.select-box {
  min-width: 220px;
}

.tabs-nav-row {
  display: flex;
  gap: 10px;
  border-top: 1px solid var(--outline);
  padding-top: 16px;
  overflow-x: auto;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: 12px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  font-size: 13.5px;
  font-weight: 650;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.tab-btn:hover {
  background: var(--surface-container-high, rgba(0, 0, 0, 0.04));
  color: var(--ink);
}

.tab-btn.active {
  background: var(--pink-light, rgba(236, 72, 153, 0.1));
  color: var(--pink-dark, #db2777);
  border-color: rgba(236, 72, 153, 0.25);
}

.tab-ico {
  font-size: 18px;
}
</style>

