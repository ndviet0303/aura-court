<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { TournamentItem } from '../types/tournament'

defineProps<{
  tournament: TournamentItem
}>()

const emit = defineEmits<{
  (e: 'register', t: TournamentItem): void
  (e: 'viewBracket', t: TournamentItem): void
}>()
</script>

<template>
  <div class="tournament-card" :class="`status-${tournament.status}`">
    <div class="card-img-wrap">
      <img :src="tournament.image_url" :alt="tournament.name" loading="lazy" />
      <span class="status-badge" :class="`badge-${tournament.status}`">
        {{
          tournament.status === 'open'
            ? '🔥 Đang mở đăng ký'
            : tournament.status === 'ongoing'
            ? '⚡ Đang thi đấu'
            : tournament.status === 'completed'
            ? '🏆 Đã kết thúc'
            : '⏳ Sắp diễn ra'
        }}
      </span>

      <span class="category-chip">{{ tournament.category }}</span>
    </div>

    <div class="card-body">
      <h3 class="tournament-name">{{ tournament.name }}</h3>

      <div class="meta-row">
        <span>📍 {{ tournament.venue }} ({{ tournament.city }})</span>
        <span>📅 {{ tournament.dates }}</span>
      </div>

      <p class="desc">{{ tournament.description }}</p>

      <!-- Live Match Preview if ongoing -->
      <div v-if="tournament.current_match" class="live-match-box">
        <div class="live-tag">🔴 LIVE TRẬN ĐẤU: {{ tournament.current_match.round_label }}</div>
        <div class="matchup-players">
          <span class="p-name">{{ tournament.current_match.player1_name }}</span>
          <span class="vs">VS</span>
          <span class="p-name">{{ tournament.current_match.player2_name }}</span>
        </div>
      </div>

      <!-- Winner info if completed -->
      <div v-if="tournament.winner_team" class="winner-box">
        <span>👑 Vô địch: <strong>{{ tournament.winner_team }}</strong></span>
      </div>

      <div class="slots-prize-bar">
        <div class="prize-col">
          <span class="lbl">Giải Thưởng:</span>
          <strong class="text-pink">{{ tournament.prize_pool }}</strong>
        </div>

        <div class="slots-col">
          <span class="lbl">Suất Tham Gia:</span>
          <strong>{{ tournament.current_slots }} / {{ tournament.max_slots }}</strong>
        </div>
      </div>

      <div class="card-actions">
        <button
          v-if="tournament.status === 'open' && tournament.current_slots < tournament.max_slots"
          class="btn-register"
          @click="emit('register', tournament)"
        >
          📝 Đăng Ký Ngay
        </button>

        <button
          class="btn-bracket"
          @click="emit('viewBracket', tournament)"
        >
          🏆 Sơ Đồ Nhánh
        </button>

        <RouterLink
          v-if="tournament.slug"
          :to="`/tournaments/${tournament.slug}/live`"
          class="btn-live-link"
        >
          📺 Live Score
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tournament-card {
  display: flex;
  flex-direction: column;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  overflow: hidden;
  transition: transform 0.15s, box-shadow 0.15s;
}

.tournament-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.card-img-wrap {
  position: relative;
  width: 100%;
  height: 180px;
}

.card-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.status-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 700;
}

.badge-open { background: #10b981; color: #fff; }
.badge-ongoing { background: var(--pink-dark, #db2777); color: #fff; }
.badge-completed { background: #64748b; color: #fff; }
.badge-upcoming { background: #f59e0b; color: #fff; }

.category-chip {
  position: absolute;
  top: 12px;
  right: 12px;
  padding: 4px 10px;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  color: #fff;
  font-size: 11px;
}

.card-body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  flex: 1;
}

.tournament-name {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  line-height: 1.3;
}

.meta-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--muted);
  flex-wrap: wrap;
  gap: 6px;
}

.desc {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.live-match-box {
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(236, 72, 153, 0.08);
  border: 1px solid rgba(236, 72, 153, 0.2);
}

.live-tag {
  font-size: 10px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  margin-bottom: 4px;
}

.matchup-players {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  font-weight: 700;
}

.vs {
  color: var(--muted);
  font-size: 10px;
}

.winner-box {
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.3);
  font-size: 12px;
}

.slots-prize-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-top: auto;
}

.prize-col, .slots-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.lbl {
  font-size: 10px;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.card-actions {
  display: flex;
  gap: 8px;
}

.btn-register {
  flex: 1;
  padding: 8px 12px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-bracket, .btn-live-link {
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
</style>
