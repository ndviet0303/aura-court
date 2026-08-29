<script setup lang="ts">
defineProps<{
  totalVenues: number
  totalCourts: number
}>()
</script>

<template>
  <div class="venue-kpi-bar">
    <div class="kpi-card">
      <span class="kpi-lbl">Tổng Số Cụm Sân</span>
      <strong class="kpi-val">{{ totalVenues }}</strong>
      <span class="kpi-sub text-green">Đang vận hành toàn quốc</span>
    </div>

    <div class="kpi-card">
      <span class="kpi-lbl">Tổng Quy Mô Sân Đấu</span>
      <strong class="kpi-val text-pink">{{ totalCourts }}</strong>
      <span class="kpi-sub">Sân đạt chuẩn thi đấu</span>
    </div>
  </div>
</template>

<style scoped>
.venue-kpi-bar {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px 20px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.kpi-lbl {
  font-size: 11px;
  color: var(--muted);
  font-weight: 700;
}

.kpi-val {
  font-size: 24px;
}

.kpi-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
</style>
