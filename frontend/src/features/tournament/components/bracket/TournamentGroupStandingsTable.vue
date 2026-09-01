<script setup lang="ts">
import type { GroupStanding } from '../../composables/useTournamentBracket'

defineProps<{
  standings: GroupStanding[]
}>()
</script>

<template>
  <div class="standings-card">
    <div class="card-header">
      <div class="title-wrap">
        <div class="hdr-ico-box">
          <span class="material-symbols-outlined">leaderboard</span>
        </div>
        <div>
          <h3 class="title">Bảng Xếp Hạng Vòng Bảng (Group Stage Leaderboard)</h3>
          <span class="sub">Top 2 vận động viên / cặp đôi dẫn đầu sẽ giành vé vào vòng Knockout Tứ Kết.</span>
        </div>
      </div>
    </div>

    <div v-if="standings.length === 0" class="empty-standings-card">
      <div class="empty-ico-box">
        <span class="material-symbols-outlined">leaderboard</span>
      </div>
      <h4 class="empty-title">Chưa Có Dữ Liệu Bảng Xếp Hạng</h4>
      <p class="empty-sub">
        Điểm số và thứ hạng của các VĐV sẽ được tự động tính toán sau khi các lượt trận diễn ra.
      </p>
    </div>

    <div v-else class="table-scroll-container">
      <div class="standings-table">
        <div class="table-hdr">
          <span>Hạng</span>
          <span>Vận Động Viên</span>
          <span>Câu Lạc Bộ</span>
          <span class="text-center">Số Trận</span>
          <span class="text-center">Thắng / Thua</span>
          <span class="text-center">Tỉ Số Set</span>
          <span class="text-right">Điểm</span>
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
              <img :src="s.avatar" :alt="s.name" class="p-avt" loading="lazy" />
              <strong class="p-name">{{ s.name }}</strong>
            </div>

            <span class="club-txt">{{ s.club }}</span>
            <span class="font-mono text-center">{{ s.mp }}</span>
            <span class="font-mono text-center">{{ s.w }} / {{ s.l }}</span>
            <span class="font-mono text-center">{{ s.setsRatio }}</span>
            <strong class="points-val font-mono text-right text-pink">{{ s.points }}</strong>
          </div>
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

.title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hdr-ico-box {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: rgba(201, 161, 90, 0.15);
  color: var(--accent, #c9a15a);
  display: flex;
  align-items: center;
  justify-content: center;
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.table-scroll-container {
  overflow-x: auto;
}

.standings-table {
  display: flex;
  flex-direction: column;
  border-radius: 14px;
  border: 1px solid var(--outline);
  overflow: hidden;
  min-width: 680px;
}

.table-hdr {
  display: grid;
  grid-template-columns: 70px 2.2fr 1.5fr 80px 100px 100px 80px;
  padding: 12px 18px;
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 750;
  color: var(--muted);
}

.standing-row {
  display: grid;
  grid-template-columns: 70px 2.2fr 1.5fr 80px 100px 100px 80px;
  padding: 12px 18px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.standing-row:last-child {
  border-bottom: 0;
}

.standing-row.qualified {
  background: rgba(16, 185, 129, 0.04);
}

.rank-col {
  display: flex;
  align-items: center;
  gap: 6px;
}

.rank-num {
  font-weight: 850;
  font-size: 13px;
}

.q-tag {
  font-size: 9px;
  font-weight: 850;
  color: #10b981;
  background: rgba(16, 185, 129, 0.15);
  padding: 2px 5px;
  border-radius: 4px;
}

.player-col {
  display: flex;
  align-items: center;
  gap: 10px;
}

.p-avt {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  border: 1.5px solid var(--outline);
}

.p-name {
  color: var(--ink);
}

.club-txt {
  color: var(--muted);
  font-size: 12.5px;
}

.text-center {
  text-align: center;
}

.text-right {
  text-align: right;
}

.points-val {
  font-size: 15px;
  font-weight: 850;
}

.text-pink {
  color: var(--primary, #db2777);
}

.empty-standings-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 50px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 16px;
  border: 1px dashed var(--outline);
}

.empty-ico-box {
  width: 50px;
  height: 50px;
  border-radius: 14px;
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
}

.empty-ico-box span {
  font-size: 26px;
}

.empty-title {
  margin: 0 0 6px;
  font-size: 15px;
  font-weight: 750;
  color: var(--ink);
}

.empty-sub {
  margin: 0;
  font-size: 12.5px;
  color: var(--muted);
  max-width: 400px;
}
</style>


