<script setup lang="ts">
defineProps<{
  leaderboard: Array<{ rank: number; name: string; points: number; tournamentsWon: number; avatar: string }>
}>()
</script>

<template>
  <div class="circuit-leaderboard-card">
    <div class="card-header">
      <span class="hdr-icon">🏆</span>
      <div>
        <h3 class="hdr-title">Bảng Xếp Hạng Circuit Aura Points 2026</h3>
        <p class="hdr-sub">Tích lũy điểm thưởng qua từng giải đấu để tranh tài vòng Chung Kết Năm Master Cup.</p>
      </div>
    </div>

    <div class="leaderboard-grid">
      <div
        v-for="team in leaderboard"
        :key="team.rank"
        class="team-rank-row"
        :class="{ 'top-1': team.rank === 1 }"
      >
        <span class="rank-badge">{{ team.avatar }} #{{ team.rank }}</span>
        <strong class="team-name">{{ team.name }}</strong>
        <span class="won-tag">🥇 {{ team.tournamentsWon }} Giải Vô Địch</span>
        <strong class="points-val text-pink">{{ team.points }} pts</strong>
      </div>
    </div>
  </div>
</template>

<style scoped>
.circuit-leaderboard-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hdr-icon {
  font-size: 28px;
}

.hdr-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}

.hdr-sub {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.leaderboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 10px;
}

.team-rank-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 13px;
}

.team-rank-row.top-1 {
  border-color: #f59e0b;
  background: rgba(245, 158, 11, 0.05);
}

.rank-badge {
  font-weight: 800;
  font-size: 13px;
}

.won-tag {
  font-size: 11px;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}
</style>
