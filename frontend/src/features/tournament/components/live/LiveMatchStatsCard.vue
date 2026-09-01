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
        <h3 class="title">Thống Kê Trận Đấu Trực Tiếp (Live Analytics)</h3>
      </div>
      <span class="duration font-mono">
        <span class="material-symbols-outlined duration-ico">timer</span>
        Thời lượng: {{ match.stats.duration }}
      </span>
    </div>


    <div class="stats-comparison-list">
      <!-- Aces -->
      <div class="stat-row">
        <strong class="val-p1 font-mono">{{ match.stats.aces[0] }}</strong>
        <span class="stat-label">Điểm giao cầu ăn ngay (Aces)</span>
        <strong class="val-p2 font-mono">{{ match.stats.aces[1] }}</strong>
      </div>

      <!-- Winners -->
      <div class="stat-row">
        <strong class="val-p1 font-mono text-pink">{{ match.stats.winners[0] }}</strong>
        <span class="stat-label">Cú đánh ghi điểm (Winners)</span>
        <strong class="val-p2 font-mono text-pink">{{ match.stats.winners[1] }}</strong>
      </div>

      <!-- Unforced Errors -->
      <div class="stat-row">
        <strong class="val-p1 font-mono text-red">{{ match.stats.unforced_errors[0] }}</strong>
        <span class="stat-label">Lỗi tự đánh hỏng (Errors)</span>
        <strong class="val-p2 font-mono text-red">{{ match.stats.unforced_errors[1] }}</strong>
      </div>

      <!-- Highlights -->
      <div class="highlights-row">
        <div class="hl-item">
          <span>Tốc độ Smash tối đa:</span>
          <strong class="font-mono text-pink">{{ match.stats.smash_speed_max }}</strong>
        </div>
        <div class="hl-item">
          <span>Pha cầu bền nhất:</span>
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
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.hdr-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.hdr-ico {
  font-size: 18px;
  color: var(--pink-dark, #db2777);
}

.title {
  margin: 0;
  font-size: 15px;
  font-weight: 750;
}

.duration {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.duration-ico {
  font-size: 14px;
}


.stats-comparison-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.stat-row {
  display: grid;
  grid-template-columns: 60px 1fr 60px;
  align-items: center;
  text-align: center;
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
}

.stat-label {
  color: var(--muted);
}

.val-p1 {
  text-align: left;
  font-size: 14px;
}

.val-p2 {
  text-align: right;
  font-size: 14px;
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-red { color: #ef4444; }

.highlights-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  padding-top: 10px;
  border-top: 1px solid var(--outline);
}

.hl-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
}
</style>
