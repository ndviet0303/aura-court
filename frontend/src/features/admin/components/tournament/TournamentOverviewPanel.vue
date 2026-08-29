<script setup lang="ts">
import type { TournamentAdminData } from '../../types/tournamentAdmin'

defineProps<{
  tournament: TournamentAdminData
}>()
</script>

<template>
  <div class="tournament-overview-container">
    <h3 class="overview-title">Tổng Quan Hoạt Động Giải Đấu</h3>
    <p class="overview-sub">Thống kê chỉ số vận hành, tài chính giải và tiến độ các vòng đấu.</p>

    <!-- 4 KPI Cards -->
    <div class="kpi-cards-grid">
      <div class="kpi-card">
        <span class="kpi-icon">👥</span>
        <span class="kpi-label">Tổng Vận Động Viên</span>
        <strong class="kpi-val">142 VĐV</strong>
        <span class="kpi-trend text-green">↑ Đạt 100% chỉ tiêu</span>
      </div>

      <div class="kpi-card">
        <span class="kpi-icon">💰</span>
        <span class="kpi-label">Tổng Giải Thưởng</span>
        <strong class="kpi-val">{{ tournament.prizePool }}</strong>
        <span class="kpi-trend">{{ tournament.prizePoolUsd }}</span>
      </div>

      <div class="kpi-card">
        <span class="kpi-icon">🏸</span>
        <span class="kpi-label">Trận Đã Hoàn Thành</span>
        <strong class="kpi-val">18 / 31 Trận</strong>
        <span class="kpi-trend text-blue">Tiến độ: 58%</span>
      </div>

      <div class="kpi-card">
        <span class="kpi-icon">⏱️</span>
        <span class="kpi-label">Sân Đang Khai Thác</span>
        <strong class="kpi-val">4 / 4 Sân</strong>
        <span class="kpi-trend text-green">Hoạt động bình thường</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tournament-overview-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 20px;
}

.overview-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.overview-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.kpi-cards-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-top: 8px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.kpi-icon {
  font-size: 20px;
}

.kpi-label {
  font-size: 12px;
  color: var(--muted);
}

.kpi-val {
  font-size: 18px;
  color: var(--ink);
}

.kpi-trend {
  font-size: 11px;
}

.text-green { color: #10b981; }
.text-blue { color: #3b82f6; }

@media (max-width: 900px) {
  .kpi-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
