<script setup lang="ts">
import type { CircuitLeaderboardItem } from '../types/tournament'

defineProps<{
  leaderboard: CircuitLeaderboardItem[]
}>()

function getRankBadgeClass(rank: number): string {
  if (rank === 1) return 'rank-gold'
  if (rank === 2) return 'rank-silver'
  if (rank === 3) return 'rank-bronze'
  return 'rank-default'
}

function getRankIcon(rank: number): string {
  if (rank === 1) return 'military_tech'
  if (rank === 2) return 'workspace_premium'
  if (rank === 3) return 'award_star'
  return 'tag'
}
</script>

<template>
  <div class="circuit-leaderboard-card">
    <div class="card-header">
      <div class="hdr-left">
        <div class="hdr-icon-box">
          <span class="material-symbols-outlined hdr-icon">military_tech</span>
        </div>
        <div>
          <div class="hdr-title-row">
            <h2 class="hdr-title">{{ $t('tournament.leaderboardTitle') }}</h2>
            <span class="circuit-badge">Master Cup Race</span>
          </div>
          <p class="hdr-sub">
            {{ $t('tournament.leaderboardSub') }}
          </p>
        </div>
      </div>
    </div>

    <div class="leaderboard-grid">
      <div
        v-for="team in leaderboard"
        :key="team.rank"
        class="team-rank-card"
        :class="[getRankBadgeClass(team.rank), { 'is-champion': team.rank === 1 }]"
      >
        <div class="rank-badge-wrap">
          <span class="material-symbols-outlined rank-ico">{{ getRankIcon(team.rank) }}</span>
          <span class="rank-num font-mono">#{{ team.rank }}</span>
        </div>

        <div class="player-profile">
          <img :src="team.avatar" :alt="team.name" class="player-avatar" loading="lazy" />
          <div class="player-meta">
            <strong class="team-name" :title="team.name">{{ team.name }}</strong>
            <div class="sub-meta">
              <span class="won-tag">
                <span class="material-symbols-outlined mini-trophy">emoji_events</span>
                {{ $t('tournament.championshipCupsWon', { count: team.tournamentsWon }) }}
              </span>
              <span v-if="team.city" class="city-tag">· {{ team.city }}</span>
            </div>
          </div>
        </div>

        <div class="points-block">
          <span class="points-val font-mono">{{ team.points.toLocaleString('vi-VN') }}</span>
          <span class="points-lbl">PTS</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.circuit-leaderboard-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.hdr-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.hdr-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(201, 161, 90, 0.15);
  border: 1px solid rgba(201, 161, 90, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--accent, #c9a15a);
  flex-shrink: 0;
}

.hdr-icon {
  font-size: 26px;
}

.hdr-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.hdr-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.circuit-badge {
  font-size: 10.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 3px 8px;
  border-radius: 6px;
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  border: 1px solid rgba(236, 72, 153, 0.2);
}

.hdr-sub {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: var(--muted);
  line-height: 1.4;
}

.leaderboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px;
}

.team-rank-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  transition:
    transform 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.team-rank-card:hover {
  transform: translateY(-2px);
  border-color: var(--primary-border, var(--pink-dark));
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}

.rank-gold {
  border-color: rgba(201, 161, 90, 0.4);
  background: linear-gradient(135deg, rgba(201, 161, 90, 0.08) 0%, rgba(201, 161, 90, 0.02) 100%);
}

.rank-silver {
  border-color: rgba(148, 163, 184, 0.35);
  background: linear-gradient(135deg, rgba(148, 163, 184, 0.08) 0%, rgba(148, 163, 184, 0.02) 100%);
}

.rank-bronze {
  border-color: rgba(217, 119, 6, 0.35);
  background: linear-gradient(135deg, rgba(217, 119, 6, 0.08) 0%, rgba(217, 119, 6, 0.02) 100%);
}

.rank-badge-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  gap: 1px;
}

.rank-ico {
  font-size: 18px;
}

.rank-gold .rank-ico {
  color: #d97706;
}
.rank-silver .rank-ico {
  color: #64748b;
}
.rank-bronze .rank-ico {
  color: #b45309;
}
.rank-default .rank-ico {
  color: var(--muted);
  font-size: 14px;
}

.rank-num {
  font-size: 11px;
  font-weight: 800;
  color: var(--muted);
}

.player-profile {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 0;
}

.player-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--outline);
  flex-shrink: 0;
}

.player-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.team-name {
  font-size: 13px;
  font-weight: 750;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sub-meta {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--muted);
}

.won-tag {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  color: var(--pink-dark, #db2777);
  font-weight: 600;
}

.mini-trophy {
  font-size: 13px;
}

.points-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1px;
}

.points-val {
  font-size: 14px;
  font-weight: 850;
  color: var(--primary, #db2777);
}

.points-lbl {
  font-size: 9.5px;
  font-weight: 700;
  color: var(--muted);
  letter-spacing: 0.5px;
}
</style>
