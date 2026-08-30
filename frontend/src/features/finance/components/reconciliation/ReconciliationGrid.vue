<script setup lang="ts">
import type { ReconciliationRun } from '../../types/reconciliation'

defineProps<{
  items: ReconciliationRun[]
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'investigate', item: ReconciliationRun): void
  (e: 'auto-resolve', id: number): void
}>()

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(amount)
}
</script>

<template>
  <div class="grid-card">
    <div class="grid-header">
      <div class="header-left">
        <h2 class="grid-title">Lưới Đối Soát Đa Chiều (3-Way Reconciliation Grid)</h2>
        <span class="count-badge">{{ items.length }} phiên ghi nhận</span>
      </div>
      <div class="grid-subtitle">
        Đối chiếu trực tiếp giữa Sổ cái Aura Booking, Webhook Cổng PSP và Dòng tiền Quyết toán Ngân hàng.
      </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
      <table class="recon-table">
        <thead>
          <tr>
            <th>Mã TXN / Phiên</th>
            <th>Kênh & Đối Tác</th>
            <th class="text-right">Sổ Cái Nội Bộ</th>
            <th class="text-right">Cổng PSP</th>
            <th class="text-right">Quyết Toán Bank</th>
            <th class="text-right">Chênh Lệch</th>
            <th class="text-center">Trạng Thái</th>
            <th class="text-center">Mức Rủi Ro</th>
            <th class="text-right">Thao Tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="9" class="text-center py-8 text-muted">
              <span class="material-symbols-outlined spin-icon">sync</span>
              Đang tải dữ liệu đối soát...
            </td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="9" class="text-center py-8 text-muted">
              Không tìm thấy phiên đối soát nào phù hợp với bộ lọc.
            </td>
          </tr>
          <tr
            v-for="item in items"
            :key="item.id"
            class="recon-row"
            :class="{ 'row-warning': item.status === 'mismatch' }"
          >
            <!-- TXN Code -->
            <td>
              <div class="txn-code-wrap">
                <span class="txn-code">{{ item.txnCode || `REC-${item.id}` }}</span>
                <span class="txn-date">{{ item.run_date }}</span>
              </div>
            </td>

            <!-- Channel -->
            <td>
              <div class="channel-info">
                <span class="channel-name">{{ item.channel || 'VietQR Pro' }}</span>
                <span class="customer-preview" v-if="item.details?.customer">
                  {{ item.details.customer }}
                </span>
              </div>
            </td>

            <!-- Ledger Total -->
            <td class="text-right font-mono font-medium">
              {{ formatCurrency(item.journal_total) }}
            </td>

            <!-- PSP Total -->
            <td class="text-right font-mono font-medium">
              {{ formatCurrency(item.provider_total) }}
            </td>

            <!-- Settlement Bank Total -->
            <td
              class="text-right font-mono font-medium"
              :class="{ 'text-error': item.mismatch_amount > 0 && item.settlement_total === 0 }"
            >
              {{ formatCurrency(item.settlement_total) }}
            </td>

            <!-- Delta Variance -->
            <td class="text-right font-mono font-bold">
              <span v-if="item.mismatch_amount === 0" class="text-success">0 ₫</span>
              <span v-else class="text-error flex-align-right">
                <span class="material-symbols-outlined text-[14px]">trending_down</span>
                -{{ formatCurrency(item.mismatch_amount) }}
              </span>
            </td>

            <!-- Status Badge -->
            <td class="text-center">
              <span
                class="status-pill"
                :class="{
                  'status-matched': item.status === 'matched',
                  'status-mismatch': item.status === 'mismatch',
                  'status-running': item.status === 'running',
                }"
              >
                <span class="status-dot"></span>
                {{ item.statusLabel || (item.status === 'matched' ? 'Đã khớp 100%' : 'Lệch số tiền') }}
              </span>
            </td>

            <!-- Risk Level Pill -->
            <td class="text-center">
              <span
                class="risk-pill"
                :class="{
                  'risk-high': item.riskLevel === 'high' || item.riskLevel === 'critical',
                  'risk-med': item.riskLevel === 'medium',
                  'risk-low': item.riskLevel === 'low' || !item.riskLevel,
                }"
              >
                {{ item.riskLevel === 'high' ? 'Cao' : item.riskLevel === 'medium' ? 'Trung bình' : 'Thấp' }}
              </span>
            </td>

            <!-- Actions -->
            <td class="text-right">
              <div class="action-btn-group">
                <button
                  class="btn-investigate"
                  type="button"
                  title="Xem đối chiếu chi tiết và chẩn đoán AI"
                  @click="emit('investigate', item)"
                >
                  <span class="material-symbols-outlined">analytics</span>
                  Đối Chiếu
                </button>
                <button
                  v-if="item.status === 'mismatch'"
                  class="btn-auto-fix-sm"
                  type="button"
                  title="Bù trừ tự động"
                  @click="emit('auto-resolve', item.id)"
                >
                  <span class="material-symbols-outlined">auto_fix_high</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.grid-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #e1e3e4;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
  overflow: hidden;
}

.grid-header {
  padding: 18px 20px 14px;
  border-bottom: 1px solid #f3f4f5;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.grid-title {
  font-size: 16px;
  font-weight: 700;
  color: #78555e;
  margin: 0;
}

.count-badge {
  font-size: 12px;
  font-weight: 600;
  background: #f3f4f5;
  color: #5d5f5f;
  padding: 2px 8px;
  border-radius: 999px;
}

.grid-subtitle {
  font-size: 13px;
  color: #5d5f5f;
  margin-top: 4px;
}

.table-responsive {
  overflow-x: auto;
}

.recon-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 13px;
}

.recon-table th {
  background: #f8f9fa;
  color: #5d5f5f;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px 16px;
  border-bottom: 1px solid #e1e3e4;
}

.recon-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f3f4f5;
  color: #191c1d;
  vertical-align: middle;
}

.recon-row {
  transition: background 0.15s;
}

.recon-row:hover {
  background: #fafafa;
}

.row-warning {
  background: #fffcfb;
}

.txn-code-wrap {
  display: flex;
  flex-direction: column;
}

.txn-code {
  font-family: monospace;
  font-weight: 700;
  color: #191c1d;
}

.txn-date {
  font-size: 11px;
  color: #817476;
}

.channel-info {
  display: flex;
  flex-direction: column;
}

.channel-name {
  font-weight: 600;
  color: #191c1d;
}

.customer-preview {
  font-size: 11px;
  color: #5d5f5f;
}

.text-right {
  text-align: right;
}

.text-center {
  text-align: center;
}

.font-mono {
  font-family: monospace;
}

.font-medium {
  font-weight: 500;
}

.font-bold {
  font-weight: 700;
}

.text-success {
  color: #059669;
}

.text-error {
  color: #ba1a1a;
}

.flex-align-right {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status-matched {
  background: #e6f4ea;
  color: #137333;
}
.status-matched .status-dot {
  background: #137333;
}

.status-mismatch {
  background: #ffdad6;
  color: #ba1a1a;
}
.status-mismatch .status-dot {
  background: #ba1a1a;
}

.status-running {
  background: #e8eaed;
  color: #5f6368;
}
.status-running .status-dot {
  background: #5f6368;
}

.risk-pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.risk-high {
  background: #fce8e6;
  color: #c5221f;
}

.risk-med {
  background: #fef7e0;
  color: #b06000;
}

.risk-low {
  background: #e6f4ea;
  color: #137333;
}

.action-btn-group {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-investigate {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  color: #191c1d;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-investigate:hover {
  background: #ffd1dc;
  border-color: #ffd1dc;
  color: #7a5761;
}

.btn-auto-fix-sm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: #78555e;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.2s;
}

.btn-auto-fix-sm:hover {
  background: #5e3e47;
}

.spin-icon {
  animation: spin 1s linear infinite;
  display: inline-block;
  vertical-align: middle;
}
</style>
