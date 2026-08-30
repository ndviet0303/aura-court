<script setup lang="ts">
import type { DisputeStats } from '../../types/dispute'

defineProps<{
  stats: DisputeStats
}>()
</script>

<template>
  <div class="dispute-kpi-grid">
    <!-- Card 1 -->
    <div class="kpi-card">
      <div class="kpi-icon-wrap icon-pending">
        <span class="material-symbols-outlined">pending_actions</span>
      </div>
      <div class="kpi-content">
        <span class="kpi-label">Chờ Thụ Lý & Phán Quyết</span>
        <div class="kpi-value-row">
          <span class="kpi-value text-pending">{{ stats.pending_count }}</span>
          <span class="kpi-sub">vụ việc</span>
        </div>
      </div>
    </div>

    <!-- Card 2 -->
    <div class="kpi-card">
      <div class="kpi-icon-wrap icon-investigating">
        <span class="material-symbols-outlined">search_insights</span>
      </div>
      <div class="kpi-content">
        <span class="kpi-label">Đang Điều Tra Bằng Chứng</span>
        <div class="kpi-value-row">
          <span class="kpi-value text-investigating">{{ stats.investigatingCount }}</span>
          <span class="kpi-sub">vụ đang thụ lý</span>
        </div>
      </div>
    </div>

    <!-- Card 3 -->
    <div class="kpi-card">
      <div class="kpi-icon-wrap icon-sla">
        <span class="material-symbols-outlined">alarm</span>
      </div>
      <div class="kpi-content">
        <span class="kpi-label">Khẩn Cấp (SLA &lt; 15 phút)</span>
        <div class="kpi-value-row">
          <span class="kpi-value text-sla">{{ stats.criticalSlaCount }}</span>
          <span class="kpi-sub">ưu tiên cao</span>
        </div>
      </div>
    </div>

    <!-- Card 4 -->
    <div class="kpi-card">
      <div class="kpi-icon-wrap icon-consensus">
        <span class="material-symbols-outlined">handshake</span>
      </div>
      <div class="kpi-content">
        <span class="kpi-label">Tỷ Lệ Đồng Thuận Phán Quyết</span>
        <div class="kpi-value-row">
          <span class="kpi-value text-consensus">{{ stats.consensusRate }}%</span>
          <span class="kpi-sub">128 đã giải quyết</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dispute-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.kpi-card {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 16px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  transition: transform 0.2s, box-shadow 0.2s;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.kpi-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-icon-wrap .material-symbols-outlined {
  font-size: 24px;
}

.icon-pending {
  background: var(--primary-container, #F8E8EC);
  color: var(--on-primary-container, #4e1827);
}

.icon-investigating {
  background: #e0f2fe;
  color: #0369a1;
}

.icon-sla {
  background: #fee2e2;
  color: #b91c1c;
}

.icon-consensus {
  background: #dcfce7;
  color: #15803d;
}

.kpi-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.kpi-label {
  font-size: 12px;
  color: #5d5f5f;
  font-weight: 500;
}

.kpi-value-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.kpi-value {
  font-size: 22px;
  font-weight: 800;
  line-height: 1.1;
}

.text-pending {
  color: #78555e;
}

.text-investigating {
  color: #0369a1;
}

.text-sla {
  color: #b91c1c;
}

.text-consensus {
  color: #15803d;
}

.kpi-sub {
  font-size: 11px;
  color: #817476;
}
</style>
