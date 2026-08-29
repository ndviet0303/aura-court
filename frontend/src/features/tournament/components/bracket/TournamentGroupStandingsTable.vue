<script setup lang="ts">
import type { GroupStanding } from '../../composables/useTournamentBracket'

defineProps<{
  standings: GroupStanding[]
}>()
</script>

<template>
  <div class="standings-card">
    <div class="card-header">
      <h3 class="title">🏆 Bảng Xếp Hạng Vòng Bảng (Group Stage Leaderboard)</h3>
      <span class="sub">Top 2 vận động viên / cặp đôi dẫn đầu sẽ giành vé vào vòng Knockout Tứ Kết</span>
    </div>

    <div class="standings-table">
      <div class="table-hdr">
        <span>Hạng</span>
        <span>Vận Động Viên</span>
        <span>Câu Lạc Bộ</span>
        <span>Số Trận</span>
        <span>Thắng / Thua</span>
        <span>Tỉ Số Set</span>
        <span>Điểm</span>
      </div>

      <div class="table-body">
        <div
          v-for="s in standings"
          :key="s.rank"
          class="standing-row"
          :class="{ qualified: s.isQualified }"
        >
          <div class="rank-col">
            <span class="rank-num font-mono">#{{ s.rank }}</span>
            <span v-if="s.isQualified" class="q-tag">Q</span>
          </div>

          <div class="player-col">
            <img :src="s.avatar" class="p-avt" />
            <strong>{{ s.name }}</strong>
          </div>

          <span>{{ s.club }}</span>
          <span class="font-mono">{{ s.mp }}</span>
          <span class="font-mono">{{ s.w }} / {{ s.l }}</span>
          <span class="font-mono">{{ s.setsRatio }}</span>
          <strong class="points-val font-mono text-pink">{{ s.points }}</strong>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.standings-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.standings-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-hdr {
  display: grid;
  grid-template-columns: 80px 2fr 1.5fr 80px 100px 100px 80px;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.standing-row {
  display: grid;
  grid-template-columns: 80px 2fr 1.5fr 80px 100px 100px 80px;
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.standing-row:last-child {
  border-bottom: 0;
}

.standing-row.qualified {
  background: rgba(16, 185, 129, 0.03);
}

.rank-col {
  display: flex;
  align-items: center;
  gap: 6px;
}

.rank-num {
  font-weight: 800;
}

.q-tag {
  font-size: 9px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.15);
  padding: 1px 4px;
  border-radius: 3px;
}

.player-col {
  display: flex;
  align-items: center;
  gap: 10px;
}

.p-avt {
  width: 28px;
  height: 28px;
  border-radius: 50%;
}

.points-val {
  font-size: 15px;
}

.text-pink { color: var(--pink-dark, #db2777); }
</style>
