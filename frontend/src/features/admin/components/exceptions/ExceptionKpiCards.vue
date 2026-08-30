<script setup lang="ts">
import type { ExceptionStats } from '../../types/exceptions'

defineProps<{
  stats: ExceptionStats
}>()
</script>

<template>
  <section class="kpi-grid">
    <div class="kpi-card card-critical">
      <div class="kpi-meta">
        <span class="kpi-label">Cảnh báo nghiêm trọng</span>
        <div class="kpi-icon-box bg-critical-tint">
          <span class="material-symbols-outlined text-critical">warning</span>
        </div>
      </div>
      <div class="kpi-value text-critical">{{ stats.criticalCount }}</div>
      <div class="kpi-hint">Vi phạm hạn mức & gian lận</div>
    </div>

    <div class="kpi-card card-warning">
      <div class="kpi-meta">
        <span class="kpi-label">Chờ xử lý thủ công</span>
        <div class="kpi-icon-box bg-warning-tint">
          <span class="material-symbols-outlined text-warning">pending_actions</span>
        </div>
      </div>
      <div class="kpi-value text-warning">{{ stats.pendingCount }}</div>
      <div class="kpi-hint">Lệch hoàn tiền & đối soát VietQR</div>
    </div>

    <div class="kpi-card card-success">
      <div class="kpi-meta">
        <span class="kpi-label">Đã can thiệp hôm nay</span>
        <div class="kpi-icon-box bg-success-tint">
          <span class="material-symbols-outlined text-success">task_alt</span>
        </div>
      </div>
      <div class="kpi-value text-success">{{ stats.resolvedCount }}</div>
      <div class="kpi-hint">Đã ký duyệt và chốt sổ</div>
    </div>

    <div class="kpi-card card-risk">
      <div class="kpi-meta">
        <span class="kpi-label">Tổng giá trị rủi ro đang giữ</span>
        <div class="kpi-icon-box bg-primary-tint">
          <span class="material-symbols-outlined text-primary">security</span>
        </div>
      </div>
      <div class="kpi-value text-ink">{{ stats.totalRiskValue.toLocaleString('vi-VN') }} ₫</div>
      <div class="kpi-hint">Tạm giữ chờ xác minh</div>
    </div>
  </section>
</template>

<style scoped>
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.kpi-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 18px;
  border: 1px solid #e1e3e4;
  display: flex;
  flex-direction: column;
  gap: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.card-critical { border-left: 4px solid #ba1a1a; }
.card-warning { border-left: 4px solid #f59e0b; }
.card-success { border-left: 4px solid #10b981; }
.card-risk { border-left: 4px solid #78555e; }

.kpi-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kpi-label {
  font-size: 13px;
  font-weight: 600;
  color: #5d5f5f;
}

.kpi-icon-box {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bg-critical-tint { background: rgba(186, 26, 26, 0.1); }
.bg-warning-tint { background: rgba(245, 158, 11, 0.1); }
.bg-success-tint { background: rgba(16, 185, 129, 0.1); }
.bg-primary-tint { background: rgba(120, 85, 94, 0.1); }

.text-critical { color: #ba1a1a; }
.text-warning { color: #d97706; }
.text-success { color: #10b981; }
.text-primary { color: #78555e; }
.text-ink { color: #191c1d; }

.kpi-value {
  font-size: 24px;
  font-weight: 700;
}

.kpi-hint {
  font-size: 12px;
  color: #817476;
}
</style>
