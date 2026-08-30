<script setup lang="ts">
import type { KycStats } from '../../types/kyc'

defineProps<{
  stats: KycStats
}>()
</script>

<template>
  <div class="kyc-kpi-grid">
    <!-- Card 1: Pending -->
    <div class="kpi-card">
      <div class="kpi-label">Hồ sơ Chờ thẩm định</div>
      <div class="kpi-value-row">
        <span class="kpi-number">{{ stats.pendingCount }}</span>
        <span class="kpi-badge warning">3 ưu tiên</span>
      </div>
      <div class="kpi-desc">Cần xử lý trước giờ mở sàn giao dịch</div>
    </div>

    <!-- Card 2: AI OCR Confidence -->
    <div class="kpi-card">
      <div class="kpi-label">Độ tin cậy AI OCR & NAPAS</div>
      <div class="kpi-value-row">
        <span class="kpi-number">{{ stats.aiConfidenceRate }}%</span>
        <span class="kpi-badge success">Tự động</span>
      </div>
      <div class="kpi-desc">Tỷ lệ so khớp CCCD & tài khoản ngân hàng</div>
    </div>

    <!-- Card 3: Avg Processing Time -->
    <div class="kpi-card">
      <div class="kpi-label">Thời gian xử lý trung bình</div>
      <div class="kpi-value-row">
        <span class="kpi-number">{{ stats.avgProcessingTimeMinutes }}</span>
        <span class="kpi-unit">phút / hồ sơ</span>
      </div>
      <div class="kpi-desc">Tối ưu hơn 40% so với quy trình thủ công</div>
    </div>

    <!-- Card 4: High Risk / Flagged -->
    <div class="kpi-card alert-card">
      <div class="kpi-label alert-text">Cảnh báo sai lệch / Rủi ro</div>
      <div class="kpi-value-row">
        <span class="kpi-number alert-text">{{ stats.flaggedCount }}</span>
        <span class="kpi-badge danger">Cần can thiệp</span>
      </div>
      <div class="kpi-desc alert-desc">Trùng địa chỉ hoặc tên GPKD khác biệt</div>
    </div>
  </div>
</template>

<style scoped>
.kyc-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

@media (max-width: 1024px) {
  .kyc-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .kyc-kpi-grid {
    grid-template-columns: 1fr;
  }
}

.kpi-card {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 16px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  transition: transform 0.2s, box-shadow 0.2s;
}

.kpi-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.alert-card {
  background: #fff8f7;
  border-color: #ffdad6;
}

.kpi-label {
  font-size: 13px;
  font-weight: 600;
  color: #5d5f5f;
}

.alert-text {
  color: #ba1a1a !important;
}

.kpi-value-row {
  display: flex;
  align-items: baseline;
  gap: 10px;
}

.kpi-number {
  font-size: 28px;
  font-weight: 800;
  color: #191c1d;
  line-height: 1.1;
  letter-spacing: -0.02em;
}

.kpi-unit {
  font-size: 13px;
  color: #5d5f5f;
  font-weight: 500;
}

.kpi-desc {
  font-size: 12px;
  color: #817476;
  margin-top: 2px;
}

.alert-desc {
  color: #ba1a1a;
  opacity: 0.85;
}

.kpi-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
}

.kpi-badge.success {
  background: #d1fae5;
  color: #059669;
}

.kpi-badge.warning {
  background: #fef3c7;
  color: #d97706;
}

.kpi-badge.danger {
  background: #fee2e2;
  color: #dc2626;
}
</style>
