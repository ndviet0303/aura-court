<script setup lang="ts">
import type { HealthTrendPoint } from '../../types/reconciliation'

defineProps<{
  healthTrend: HealthTrendPoint[]
  matchRate: number
}>()

const liveLogs = [
  {
    time: '14:02:15',
    title: 'Batch Sync Thành công',
    desc: 'Đã nhận sao kê MT940 từ MBBank (1,450 giao dịch khớp).',
    type: 'sync',
  },
  {
    time: '12:45:00',
    title: 'AI Auto-Resolve',
    desc: 'Tự động bù trừ sai lệch phí cổng 10,000 ₫ cho TXN-88288D.',
    type: 'auto_resolve',
  },
  {
    time: '09:15:32',
    title: 'Cảnh báo Delay Webhook',
    desc: 'Phát hiện trễ webhook Napas247 -1.4M ₫ trên TXN-88291A.',
    type: 'mismatch',
  },
  {
    time: '06:00:00',
    title: 'Khởi động Chu kỳ T+0',
    desc: 'Hệ thống đối soát tự động kích hoạt phiên sáng sớm.',
    type: 'sync',
  },
]
</script>

<template>
  <div class="analytics-layout">
    <!-- Left Column: 7-Day Trend Chart & Metrics -->
    <div class="analytics-card">
      <div class="card-header">
        <div class="header-titles">
          <span class="material-symbols-outlined header-icon">monitoring</span>
          <h3 class="card-title">Tỷ Lệ Khớp Lệnh & Xu Hướng Đối Soát (7 Ngày)</h3>
        </div>
        <span class="rate-badge">Độ chính xác: {{ matchRate }}%</span>
      </div>

      <!-- Trend Visualization -->
      <div class="chart-container">
        <div class="bars-wrapper">
          <div
            v-for="(point, idx) in healthTrend"
            :key="idx"
            class="bar-column"
          >
            <div class="bar-track">
              <div
                class="bar-fill"
                :class="{
                  'bar-ok': point.status === 'matched',
                  'bar-warn': point.status === 'mismatch',
                }"
                :style="{ height: point.status === 'matched' ? '92%' : '65%' }"
              ></div>
            </div>
            <span class="bar-date">{{ point.date }}</span>
          </div>
        </div>
      </div>

      <div class="chart-footer">
        <div class="legend-item">
          <span class="legend-dot dot-ok"></span>
          <span>Khớp 100% (Không sai lệch)</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot dot-warn"></span>
          <span>Có sai lệch cần xử lý</span>
        </div>
      </div>
    </div>

    <!-- Right Column: Live Event Audit Stream -->
    <div class="analytics-card">
      <div class="card-header">
        <div class="header-titles">
          <span class="material-symbols-outlined header-icon">history_edu</span>
          <h3 class="card-title">Nhật Ký Sự Kiện Đối Soát Tức Thì (Live Stream)</h3>
        </div>
        <span class="live-dot-wrap">
          <span class="live-pulse"></span>
          REALTIME
        </span>
      </div>

      <!-- Stream List -->
      <div class="stream-list">
        <div
          v-for="(log, idx) in liveLogs"
          :key="idx"
          class="stream-item"
        >
          <div class="stream-icon-box" :class="`icon-${log.type}`">
            <span class="material-symbols-outlined text-[16px]">
              {{ log.type === 'sync' ? 'sync' : log.type === 'auto_resolve' ? 'auto_fix_high' : 'warning' }}
            </span>
          </div>
          <div class="stream-details">
            <div class="stream-top">
              <span class="stream-item-title">{{ log.title }}</span>
              <span class="stream-time">{{ log.time }}</span>
            </div>
            <p class="stream-desc">{{ log.desc }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.analytics-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 900px) {
  .analytics-layout {
    grid-template-columns: 1fr;
  }
}

.analytics-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #e1e3e4;
  padding: 18px 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f3f4f5;
  padding-bottom: 12px;
}

.header-titles {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-icon {
  font-size: 20px;
  color: #78555e;
}

.card-title {
  font-size: 14px;
  font-weight: 700;
  color: #191c1d;
  margin: 0;
}

.rate-badge {
  font-size: 12px;
  font-weight: 700;
  background: #ffd1dc;
  color: #7a5761;
  padding: 3px 10px;
  border-radius: 999px;
}

.live-dot-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  color: #059669;
}

.live-pulse {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(1.2); }
}

.chart-container {
  height: 140px;
  display: flex;
  align-items: flex-end;
  padding: 10px 0;
}

.bars-wrapper {
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 12px;
}

.bar-column {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
  gap: 6px;
}

.bar-track {
  flex: 1;
  width: 100%;
  max-width: 28px;
  background: #f3f4f5;
  border-radius: 6px;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}

.bar-fill {
  width: 100%;
  border-radius: 6px 6px 0 0;
  transition: height 0.4s ease;
}

.bar-ok {
  background: #78555e;
}

.bar-warn {
  background: #ba1a1a;
}

.bar-date {
  font-size: 11px;
  color: #5d5f5f;
  font-weight: 500;
}

.chart-footer {
  display: flex;
  align-items: center;
  gap: 16px;
  font-size: 12px;
  color: #5d5f5f;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.dot-ok {
  background: #78555e;
}

.dot-warn {
  background: #ba1a1a;
}

.stream-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.stream-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px;
  border-radius: 10px;
  background: #f8f9fa;
  border: 1px solid #f3f4f5;
}

.stream-icon-box {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-sync {
  background: #e6f4ea;
  color: #137333;
}

.icon-auto_resolve {
  background: #ffd1dc;
  color: #7a5761;
}

.icon-mismatch {
  background: #ffdad6;
  color: #ba1a1a;
}

.stream-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stream-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stream-item-title {
  font-size: 12px;
  font-weight: 700;
  color: #191c1d;
}

.stream-time {
  font-size: 11px;
  color: #817476;
  font-family: monospace;
}

.stream-desc {
  font-size: 11px;
  color: #5d5f5f;
  margin: 0;
}
</style>
