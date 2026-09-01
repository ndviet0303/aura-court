<script setup lang="ts">
import { computed } from 'vue'
import { AuraButton } from '@/shared/components'
import type { TournamentItem } from '../types/tournament'

const props = defineProps<{
  tournament: TournamentItem
}>()

const emit = defineEmits<{
  (e: 'register', t: TournamentItem): void
  (e: 'viewBracket', t: TournamentItem): void
}>()

const statusLabel = computed(() => {
  switch (props.tournament.status) {
    case 'open':
      return 'Đang mở đăng ký'
    case 'ongoing':
      return 'Đang tranh tài'
    case 'completed':
      return 'Đã kết thúc'
    case 'upcoming':
    default:
      return 'Sắp diễn ra'
  }
})

const statusIcon = computed(() => {
  switch (props.tournament.status) {
    case 'open':
      return 'how_to_reg'
    case 'ongoing':
      return 'bolt'
    case 'completed':
      return 'check_circle'
    case 'upcoming':
    default:
      return 'schedule'
  }
})

const sportIcon = computed(() => {
  switch (props.tournament.sport) {
    case 'pickleball':
      return 'sports_tennis'
    case 'tennis':
      return 'sports_tennis'
    case 'football_5v5':
      return 'sports_soccer'
    case 'badminton':
    default:
      return 'sports_handball'
  }
})

const slotPercentage = computed(() => {
  if (!props.tournament.max_slots) return 0
  return Math.min(100, Math.round((props.tournament.current_slots / props.tournament.max_slots) * 100))
})
</script>

<template>
  <div class="tournament-card" :class="`status-${tournament.status}`">
    <!-- Card Cover Banner -->
    <div class="card-img-wrap">
      <img :src="tournament.image_url" :alt="tournament.name" loading="lazy" class="card-img" />
      <div class="card-img-overlay"></div>

      <!-- Status Badge -->
      <span class="status-badge" :class="`badge-${tournament.status}`">
        <span class="material-symbols-outlined badge-ico">{{ statusIcon }}</span>
        <span>{{ statusLabel }}</span>
      </span>

      <!-- Sport / Category Chip -->
      <span class="category-chip">
        <span class="material-symbols-outlined chip-ico">{{ sportIcon }}</span>
        <span>{{ tournament.category || tournament.sport }}</span>
      </span>
    </div>

    <!-- Card Main Body -->
    <div class="card-body">
      <h3 class="tournament-name" :title="tournament.name">{{ tournament.name }}</h3>

      <div class="meta-row">
        <span class="meta-item">
          <span class="material-symbols-outlined meta-ico">location_on</span>
          <span>{{ tournament.venue }} ({{ tournament.city }})</span>
        </span>
        <span class="meta-item">
          <span class="material-symbols-outlined meta-ico">calendar_month</span>
          <span>{{ tournament.dates }}</span>
        </span>
      </div>

      <p class="desc">{{ tournament.description }}</p>

      <!-- Live Match Preview if ongoing -->
      <div v-if="tournament.current_match" class="live-match-box">
        <div class="live-tag">
          <span class="live-dot"></span>
          <span>LIVE: {{ tournament.current_match.round_label }} · {{ tournament.current_match.court }}</span>
        </div>
        <div class="matchup-players">
          <span class="p-name">{{ tournament.current_match.player1_name }}</span>
          <span class="vs">VS</span>
          <span class="p-name">{{ tournament.current_match.player2_name }}</span>
        </div>
      </div>

      <!-- Winner info if completed -->
      <div v-if="tournament.winner_team" class="winner-box">
        <span class="material-symbols-outlined winner-ico">emoji_events</span>
        <span>Vô địch: <strong>{{ tournament.winner_team }}</strong></span>
      </div>

      <!-- Slots & Prize Pool Summary Bar -->
      <div class="slots-prize-bar">
        <div class="prize-col">
          <span class="lbl">
            <span class="material-symbols-outlined mini-ico">payments</span>
            Tổng Quỹ Thưởng
          </span>
          <strong class="prize-val">{{ tournament.prize_pool }}</strong>
        </div>

        <div class="slots-col">
          <div class="slots-info">
            <span class="lbl">
              <span class="material-symbols-outlined mini-ico">group</span>
              Suất Tham Gia
            </span>
            <strong class="slots-count">{{ tournament.current_slots }}/{{ tournament.max_slots }}</strong>
          </div>
          <div class="progress-track" :title="`${slotPercentage}% đã đăng ký`">
            <div class="progress-fill" :style="{ width: `${slotPercentage}%` }"></div>
          </div>
        </div>
      </div>

      <!-- Card Action Buttons -->
      <div class="card-actions">
        <AuraButton
          v-if="tournament.status === 'open' && tournament.current_slots < tournament.max_slots"
          variant="primary"
          size="sm"
          icon="how_to_reg"
          block
          @click="emit('register', tournament)"
        >
          Đăng Ký
        </AuraButton>

        <AuraButton
          variant="outline"
          size="sm"
          icon="account_tree"
          @click="emit('viewBracket', tournament)"
        >
          Sơ Đồ
        </AuraButton>

        <AuraButton
          v-if="tournament.slug"
          :to="`/tournaments/${tournament.slug}/live`"
          variant="secondary"
          size="sm"
          icon="scoreboard"
        >
          Tỷ Số
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tournament-card {
  display: flex;
  flex-direction: column;
  border-radius: 18px;
  border: 1px solid var(--outline);
  background: var(--surface);
  overflow: hidden;
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.2s ease;
}

.tournament-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
  border-color: var(--primary-border, var(--pink-dark));
}

.card-img-wrap {
  position: relative;
  width: 100%;
  height: 190px;
  background: var(--surface-container-high, #2b2b2b);
  overflow: hidden;
}

.card-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.tournament-card:hover .card-img {
  transform: scale(1.04);
}

.card-img-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0,0,0,0.35) 0%, rgba(0,0,0,0.1) 40%, rgba(0,0,0,0.7) 100%);
}

.status-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 750;
  letter-spacing: 0.2px;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

.badge-ico {
  font-size: 14px;
}

.badge-open {
  background: rgba(16, 185, 129, 0.9);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
}

.badge-ongoing {
  background: rgba(225, 29, 72, 0.9);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(225, 29, 72, 0.4);
}

.badge-completed {
  background: rgba(100, 116, 139, 0.9);
  color: #ffffff;
}

.badge-upcoming {
  background: rgba(217, 119, 6, 0.9);
  color: #ffffff;
}

.category-chip {
  position: absolute;
  top: 12px;
  right: 12px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  color: #ffffff;
  font-size: 11px;
  font-weight: 600;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.chip-ico {
  font-size: 14px;
}

.card-body {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  flex: 1;
}

.tournament-name {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  line-height: 1.35;
  color: var(--ink);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.meta-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.meta-ico {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
  flex-shrink: 0;
}

.desc {
  margin: 0;
  font-size: 12.5px;
  color: var(--muted);
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Live match preview */
.live-match-box {
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(225, 29, 72, 0.06);
  border: 1px solid rgba(225, 29, 72, 0.2);
}

.live-tag {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 10.5px;
  font-weight: 800;
  color: #e11d48;
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.live-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #e11d48;
  animation: pulse-dot 1.4s infinite;
}

@keyframes pulse-dot {
  0% { transform: scale(0.9); opacity: 1; }
  50% { transform: scale(1.4); opacity: 0.5; }
  100% { transform: scale(0.9); opacity: 1; }
}

.matchup-players {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12.5px;
  font-weight: 700;
}

.vs {
  font-size: 10px;
  font-weight: 800;
  color: var(--muted);
  padding: 2px 6px;
  background: var(--surface-container, rgba(0,0,0,0.05));
  border-radius: 4px;
}

/* Winner Box */
.winner-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 10px;
  background: rgba(201, 161, 90, 0.1);
  border: 1px solid rgba(201, 161, 90, 0.3);
  font-size: 12px;
  color: var(--ink);
}

.winner-ico {
  font-size: 18px;
  color: var(--accent, #c9a15a);
}

/* Slots & Prize Pool */
.slots-prize-bar {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-top: auto;
}

.prize-col, .slots-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.lbl {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10.5px;
  font-weight: 600;
  color: var(--muted);
}

.mini-ico {
  font-size: 13px;
}

.prize-val {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--primary, #db2777);
  font-family: inherit;
}

.slots-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.slots-count {
  font-size: 12px;
  font-weight: 700;
}

.progress-track {
  width: 100%;
  height: 6px;
  border-radius: 999px;
  background: var(--outline);
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--pink-dark, #db2777), var(--primary, #6b2d3f));
  transition: width 0.3s ease;
}

.card-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 4px;
}
</style>

