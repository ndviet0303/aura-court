<script setup lang="ts">
import type { MatchFixture } from '../../composables/useLiveScore'

defineProps<{
  match: MatchFixture
}>()
</script>

<template>
  <div class="live-stats-card">
    <div class="card-header">
      <div class="hdr-title-wrap">
        <span class="material-symbols-outlined hdr-ico">analytics</span>
        <h3 class="title">Chỉ Số & Thống Kê Trận Đấu (Match Statistics)</h3>
      </div>
      <span class="duration font-mono">
        <span class="material-symbols-outlined duration-ico">timer</span>
        Thời lượng: {{ match.stats.duration }}
      </span>
    </div>

    <!-- Players Header for stats -->
    <div class="players-stats-head">
      <div class="p-col p1-col">
        <span class="p-label">{{ match.player1.name }}</span>
      </div>
      <div class="center-col">
        <span class="vs-badge">VS</span>
      </div>
      <div class="p-col p2-col">
        <span class="p-label">{{ match.player2.name }}</span>
      </div>
    </div>

    <div class="stats-comparison-list">
      <!-- Total Points Won -->
      <div class="stat-row">
        <strong class="val-p1 font-mono highlight">{{ match.stats.total_points_won ? match.stats.total_points_won[0] : match.player1.current_score }}</strong>
        <div class="stat-mid">
          <span class="stat-label">Tổng Điểm Thắng (Total Points)</span>
          <div class="stat-bars">
            <div
              class="bar-left"
              :style="{
                width: `${Math.round(((match.stats.total_points_won ? match.stats.total_points_won[0] : match.player1.current_score) / Math.max(1, (match.stats.total_points_won ? match.stats.total_points_won[0] + match.stats.total_points_won[1] : match.player1.current_score + match.player2.current_score))) * 100)}%`
              }"
            ></div>
            <div
              class="bar-right"
              :style="{
                width: `${Math.round(((match.stats.total_points_won ? match.stats.total_points_won[1] : match.player2.current_score) / Math.max(1, (match.stats.total_points_won ? match.stats.total_points_won[0] + match.stats.total_points_won[1] : match.player1.current_score + match.player2.current_score))) * 100)}%`
              }"
            ></div>
          </div>
        </div>
        <strong class="val-p2 font-mono highlight">{{ match.stats.total_points_won ? match.stats.total_points_won[1] : match.player2.current_score }}</strong>
      </div>

      <!-- Aces -->
      <div class="stat-row">
        <strong class="val-p1 font-mono">{{ match.stats.aces[0] }}</strong>
        <div class="stat-mid">
          <span class="stat-label">Điểm giao cầu ăn ngay (Aces)</span>
        </div>
        <strong class="val-p2 font-mono">{{ match.stats.aces[1] }}</strong>
      </div>

      <!-- Winners -->
      <div class="stat-row">
        <strong class="val-p1 font-mono text-pink">{{ match.stats.winners[0] }}</strong>
        <div class="stat-mid">
          <span class="stat-label">Cú đánh ghi điểm (Winners)</span>
        </div>
        <strong class="val-p2 font-mono text-pink">{{ match.stats.winners[1] }}</strong>
      </div>

      <!-- Unforced Errors -->
      <div class="stat-row">
        <strong class="val-p1 font-mono text-red">{{ match.stats.unforced_errors[0] }}</strong>
        <div class="stat-mid">
          <span class="stat-label">Lỗi tự đánh hỏng (Unforced Errors)</span>
        </div>
        <strong class="val-p2 font-mono text-red">{{ match.stats.unforced_errors[1] }}</strong>
      </div>

      <!-- 1st Serve In % -->
      <div v-if="match.stats.first_serve_pct" class="stat-row">
        <strong class="val-p1 font-mono">{{ match.stats.first_serve_pct[0] }}%</strong>
        <div class="stat-mid">
          <span class="stat-label">Tỷ Lệ Giao Bóng 1 (1st Serve In)</span>
        </div>
        <strong class="val-p2 font-mono">{{ match.stats.first_serve_pct[1] }}%</strong>
      </div>

      <!-- Break Points -->
      <div v-if="match.stats.break_points" class="stat-row">
        <strong class="val-p1 font-mono">{{ match.stats.break_points[0] }}</strong>
        <div class="stat-mid">
          <span class="stat-label">Điểm Break Giành Được (Break Points)</span>
        </div>
        <strong class="val-p2 font-mono">{{ match.stats.break_points[1] }}</strong>
      </div>

      <!-- Max Consecutive Points -->
      <div v-if="match.stats.max_consecutive_pts" class="stat-row">
        <strong class="val-p1 font-mono">{{ match.stats.max_consecutive_pts[0] }}</strong>
        <div class="stat-mid">
          <span class="stat-label">Chuỗi Điểm Liên Tiếp Cao Nhất</span>
        </div>
        <strong class="val-p2 font-mono">{{ match.stats.max_consecutive_pts[1] }}</strong>
      </div>

      <!-- Net Points Won % -->
      <div v-if="match.stats.net_points_won_pct" class="stat-row">
        <strong class="val-p1 font-mono text-pink">{{ match.stats.net_points_won_pct[0] }}%</strong>
        <div class="stat-mid">
          <span class="stat-label">Tỷ Lệ Thắng Điểm Trên Lưới</span>
        </div>
        <strong class="val-p2 font-mono text-pink">{{ match.stats.net_points_won_pct[1] }}%</strong>
      </div>

      <!-- Highlights -->
      <div class="highlights-row">
        <div class="hl-item">
          <span class="hl-lbl">Tốc độ Smash tối đa</span>
          <strong class="font-mono text-pink">{{ match.stats.smash_speed_max }}</strong>
        </div>
        <div class="hl-item">
          <span class="hl-lbl">Pha cầu bền nhất</span>
          <strong class="font-mono">{{ match.stats.longest_rally }}</strong>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.live-stats-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 22px;
  border-radius: 18px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--outline);
}

.hdr-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.hdr-ico {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.title {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: var(--ink);
}

.duration {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  padding: 3px 10px;
  border-radius: 999px;
}

.duration-ico {
  font-size: 14px;
}

.players-stats-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 12px;
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
  border-radius: 10px;
  font-size: 13px;
  font-weight: 750;
}

.p-col {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.p1-col {
  text-align: left;
  color: var(--pink-dark, #db2777);
}

.p2-col {
  text-align: right;
  color: var(--ink);
}

.center-col {
  padding: 0 12px;
}

.vs-badge {
  font-size: 10px;
  font-weight: 800;
  color: var(--muted);
  letter-spacing: 0.5px;
}

.stats-comparison-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.stat-row {
  display: grid;
  grid-template-columns: 50px 1fr 50px;
  align-items: center;
  text-align: center;
  padding: 8px 12px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12.5px;
  transition: background 0.15s ease;
}

.stat-row:hover {
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
}

.stat-mid {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.stat-label {
  color: var(--muted);
  font-size: 12px;
  font-weight: 500;
}

.stat-bars {
  display: flex;
  width: 100%;
  max-width: 180px;
  height: 4px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--outline);
  gap: 2px;
}

.bar-left {
  background: var(--pink-dark, #db2777);
  transition: width 0.3s ease;
}

.bar-right {
  background: var(--accent, #c9a15a);
  transition: width 0.3s ease;
}

.val-p1 {
  text-align: left;
  font-size: 14px;
  font-weight: 800;
}

.val-p2 {
  text-align: right;
  font-size: 14px;
  font-weight: 800;
}

.highlight {
  color: var(--ink);
  font-size: 15px;
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-red { color: #ef4444; }

.highlights-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-top: 6px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
  border: 1px dashed var(--outline);
}

.hl-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
}

.hl-lbl {
  color: var(--muted);
  font-size: 11px;
}
</style>
