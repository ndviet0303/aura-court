<script setup lang="ts">
import { ref } from 'vue'
import type { ReconciliationRun, ReconciliationDetail } from '../../types/reconciliation'

const props = defineProps<{
  show: boolean
  item: ReconciliationRun | null
  detail: ReconciliationDetail | null
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'resolve', id: number): void
}>()

const overrideNotes = ref('')
const isSigning = ref(false)

function formatCurrency(amount?: number): string {
  if (amount === undefined || amount === null) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(amount)
}

function handleResolve() {
  if (!props.item) return
  isSigning.value = true
  setTimeout(() => {
    emit('resolve', props.item!.id)
    isSigning.value = false
  }, 600)
}
</script>

<template>
  <div v-if="show" class="drawer-overlay" @click.self="emit('close')">
    <div class="drawer-container">
      <!-- Drawer Header -->
      <div class="drawer-header">
        <div class="header-main">
          <div class="title-row">
            <h3 class="drawer-title">Đối Chiếu Chi Tiết Giao Dịch</h3>
            <span class="txn-badge">{{ item?.txnCode || `REC-${item?.id}` }}</span>
          </div>
          <p class="drawer-sub">
            So sánh dữ liệu đa nguồn: Sổ cái nội bộ vs Webhook Cổng TT vs Sao kê Ngân hàng.
          </p>
        </div>
        <button class="btn-close" type="button" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Drawer Body -->
      <div class="drawer-body">
        <!-- Discrepancy Overview Banner -->
        <div
          class="discrepancy-banner"
          :class="{
            'banner-mismatch': item?.status === 'mismatch',
            'banner-matched': item?.status === 'matched',
          }"
        >
          <div class="banner-stat">
            <span class="stat-label">Trạng thái đối soát:</span>
            <span class="stat-value font-bold">
              {{ item?.status === 'mismatch' ? 'Phát hiện Chênh lệch' : 'Đã khớp toàn vẹn' }}
            </span>
          </div>
          <div class="banner-stat">
            <span class="stat-label">Mức chênh lệch (Delta):</span>
            <span class="stat-value text-error font-mono font-bold" v-if="item?.mismatch_amount">
              -{{ formatCurrency(item.mismatch_amount) }}
            </span>
            <span class="stat-value text-success font-mono font-bold" v-else>0 ₫</span>
          </div>
        </div>

        <!-- 3-Column Comparison Grid -->
        <div class="comparison-grid">
          <!-- Col 1: Sổ cái Aura Booking -->
          <div class="comparison-col">
            <div class="col-header">
              <span class="material-symbols-outlined col-icon">menu_book</span>
              <span class="col-title">1. Sổ Cái Aura</span>
            </div>
            <div class="col-content">
              <div class="info-row">
                <span class="row-label">Số tiền ghi nhận:</span>
                <span class="row-val font-bold font-mono">{{ formatCurrency(item?.journal_total) }}</span>
              </div>
              <div class="info-row">
                <span class="row-label">Thời gian đặt:</span>
                <span class="row-val">06:42:15 (Hôm nay)</span>
              </div>
              <div class="info-row">
                <span class="row-label">Cụm sân / Vị trí:</span>
                <span class="row-val">{{ item?.details?.court || 'Aura Court Q7 - Sân VIP 1' }}</span>
              </div>
              <div class="info-row">
                <span class="row-label">Khách hàng:</span>
                <span class="row-val">{{ item?.details?.customer || 'Trần Minh Quang (UID-8821)' }}</span>
              </div>
              <div class="info-tag tag-success">Trạng thái: Đã ghi nhận Ledger</div>
            </div>
          </div>

          <!-- Col 2: Cổng Thanh toán PSP -->
          <div class="comparison-col">
            <div class="col-header">
              <span class="material-symbols-outlined col-icon">account_balance_wallet</span>
              <span class="col-title">2. Cổng PSP</span>
            </div>
            <div class="col-content">
              <div class="info-row">
                <span class="row-label">Số tiền PSP báo:</span>
                <span class="row-val font-bold font-mono">{{ formatCurrency(item?.provider_total) }}</span>
              </div>
              <div class="info-row">
                <span class="row-label">Mã Ref Cổng:</span>
                <span class="row-val font-mono">FT260830001</span>
              </div>
              <div class="info-row">
                <span class="row-label">Đối tác thanh toán:</span>
                <span class="row-val">{{ item?.channel || 'VietQR Pro (MBBank)' }}</span>
              </div>
              <div class="info-row">
                <span class="row-label">Webhook Verified:</span>
                <span class="row-val">06:42:16 (120ms)</span>
              </div>
              <div class="info-tag tag-success">Trạng thái: Giao dịch Thành công</div>
            </div>
          </div>

          <!-- Col 3: Quyết toán Ngân hàng -->
          <div class="comparison-col" :class="{ 'col-error': item?.status === 'mismatch' }">
            <div class="col-header">
              <span class="material-symbols-outlined col-icon">account_balance</span>
              <span class="col-title">3. Quyết Toán Bank</span>
            </div>
            <div class="col-content">
              <div class="info-row">
                <span class="row-label">Số tiền thực có:</span>
                <span class="row-val font-bold font-mono" :class="{ 'text-error': item?.settlement_total === 0 }">
                  {{ formatCurrency(item?.settlement_total) }}
                </span>
              </div>
              <div class="info-row">
                <span class="row-label">Số tài khoản:</span>
                <span class="row-val font-mono">038899281928 (MB)</span>
              </div>
              <div class="info-row">
                <span class="row-label">Kỳ sao kê:</span>
                <span class="row-val">Batch T+0 (07:00)</span>
              </div>
              <div class="info-row">
                <span class="row-label">Khớp sao kê:</span>
                <span class="row-val text-error font-medium" v-if="item?.settlement_total === 0">
                  Chưa ghi có (-{{ formatCurrency(item?.mismatch_amount) }})
                </span>
                <span class="row-val text-success" v-else>Đã khớp đủ</span>
              </div>
              <div
                class="info-tag"
                :class="item?.settlement_total === 0 ? 'tag-error' : 'tag-success'"
              >
                {{ item?.settlement_total === 0 ? 'Treo Settlement (Delay)' : 'Đã quyết toán' }}
              </div>
            </div>
          </div>
        </div>

        <!-- AI Root-Cause Diagnostic Card -->
        <div class="ai-diagnosis-card">
          <div class="ai-header">
            <div class="ai-title-wrap">
              <span class="material-symbols-outlined ai-icon">psychology</span>
              <span class="ai-title">Chẩn Đoán Nguyên Nhân Gốc Rễ (AI Root-Cause Assistant)</span>
            </div>
            <span class="confidence-badge">Độ tin cậy: 99.4%</span>
          </div>
          <div class="ai-body">
            <p class="diagnosis-text">
              <strong>Phân tích hệ thống:</strong> Khách hàng đã quét mã QR thành công, đối tác PSP ghi nhận có tiền. Tuy nhiên luồng chuyển tiếp Napas247 sang tài khoản thụ hưởng ngân hàng bị trễ do bảo trì mạng lưới lúc 06:40. Tiền đã vào tài khoản tổng lúc 07:10 (sau chu kỳ sao kê sáng).
            </p>
            <div class="recommendation-box">
              <span class="rec-title">💡 Khuyến nghị hành động:</span>
              <span class="rec-desc">
                Xác nhận "Bù Trừ Tự Động" để ghi nhận khớp doanh thu và cập nhật hạn mức quyết toán an toàn mà không cần chờ đến sao kê 17:00 chiều.
              </span>
            </div>
          </div>
        </div>

        <!-- Resolution Form -->
        <div class="resolution-section" v-if="item?.status === 'mismatch'">
          <label class="form-label">Ghi chú kiểm toán / Ký duyệt can thiệp:</label>
          <textarea
            v-model="overrideNotes"
            rows="2"
            placeholder="Nhập lý do bù trừ hoặc mã tham chiếu tra soát ngân hàng..."
            class="notes-textarea"
          ></textarea>
        </div>
      </div>

      <!-- Drawer Footer -->
      <div class="drawer-footer">
        <button class="btn-cancel" type="button" @click="emit('close')">Đóng</button>
        <div class="footer-action-buttons">
          <button
            v-if="item?.status === 'mismatch'"
            class="btn-primary-resolve"
            type="button"
            :disabled="isSigning"
            @click="handleResolve"
          >
            <span class="material-symbols-outlined">verified</span>
            {{ isSigning ? 'Đang Ký Duyệt...' : 'Ký Duyệt Bù Trừ Tự Động' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(25, 28, 29, 0.45);
  backdrop-filter: blur(4px);
  z-index: 999;
  display: flex;
  justify-content: flex-end;
}

.drawer-container {
  width: 680px;
  max-width: 95vw;
  height: 100vh;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
  animation: slideIn 0.25s ease-out;
}

@keyframes slideIn {
  from { transform: translateX(100%); }
  to { transform: translateX(0); }
}

.drawer-header {
  padding: 20px 24px;
  border-bottom: 1px solid #e1e3e4;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: #f8f9fa;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.drawer-title {
  font-size: 18px;
  font-weight: 700;
  color: #191c1d;
  margin: 0;
}

.txn-badge {
  font-family: monospace;
  font-size: 12px;
  font-weight: 700;
  background: #ffd1dc;
  color: #7a5761;
  padding: 3px 8px;
  border-radius: 6px;
}

.drawer-sub {
  font-size: 13px;
  color: #5d5f5f;
  margin: 4px 0 0;
}

.btn-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: #5d5f5f;
  padding: 4px;
  border-radius: 50%;
  transition: background 0.2s;
}

.btn-close:hover {
  background: #e1e3e4;
  color: #191c1d;
}

.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.discrepancy-banner {
  display: flex;
  justify-content: space-between;
  padding: 14px 18px;
  border-radius: 12px;
}

.banner-mismatch {
  background: #ffdad6;
  border: 1px solid #ba1a1a;
}

.banner-matched {
  background: #e6f4ea;
  border: 1px solid #137333;
}

.stat-label {
  font-size: 12px;
  color: #4f4446;
  display: block;
}

.stat-value {
  font-size: 15px;
  margin-top: 2px;
  display: block;
}

.comparison-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

@media (max-width: 600px) {
  .comparison-grid {
    grid-template-columns: 1fr;
  }
}

.comparison-col {
  background: #f8f9fa;
  border-radius: 12px;
  border: 1px solid #e1e3e4;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.col-error {
  border-color: #ffdad6;
  background: #fffcfb;
}

.col-header {
  display: flex;
  align-items: center;
  gap: 6px;
  border-bottom: 1px solid #e1e3e4;
  padding-bottom: 8px;
}

.col-icon {
  font-size: 18px;
  color: var(--primary, #6B2D3F);
}

.col-title {
  font-size: 13px;
  font-weight: 700;
  color: #191c1d;
}

.col-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.info-row {
  display: flex;
  flex-direction: column;
}

.row-label {
  font-size: 11px;
  color: #5d5f5f;
}

.row-val {
  font-size: 13px;
  color: #191c1d;
}

.info-tag {
  margin-top: 6px;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 6px;
  text-align: center;
}

.tag-success {
  background: #e6f4ea;
  color: #137333;
}

.tag-error {
  background: #ffdad6;
  color: #ba1a1a;
}

.ai-diagnosis-card {
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border, #e8e1d7);
  border-radius: 14px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ai-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.ai-title-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.ai-icon {
  font-size: 20px;
  color: var(--primary, #6B2D3F);
}

.ai-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--primary, #6B2D3F);
}

.confidence-badge {
  font-size: 11px;
  font-weight: 700;
  background: var(--primary-container, #F8E8EC);
  color: var(--on-primary-container, #4e1827);
  padding: 2px 8px;
  border-radius: 999px;
}

.diagnosis-text {
  font-size: 13px;
  color: #191c1d;
  line-height: 1.5;
  margin: 0;
}

.recommendation-box {
  background: #ffffff;
  border-radius: 8px;
  padding: 10px 12px;
  border: 1px dashed var(--border, #e8e1d7);
  font-size: 12px;
}

.rec-title {
  font-weight: 700;
  color: var(--primary, #6B2D3F);
  display: block;
}

.rec-desc {
  color: #4f4446;
  margin-top: 2px;
  display: block;
}

.resolution-section {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: #191c1d;
}

.notes-textarea {
  border: 1px solid #e1e3e4;
  border-radius: 8px;
  padding: 10px;
  font-size: 13px;
  outline: none;
  font-family: inherit;
}

.notes-textarea:focus {
  border-color: var(--primary, #6B2D3F);
}

.drawer-footer {
  padding: 16px 24px;
  border-top: 1px solid #e1e3e4;
  background: #f8f9fa;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.btn-cancel {
  background: transparent;
  border: 1px solid #e1e3e4;
  padding: 8px 18px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-primary-resolve {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--primary, #6B2D3F);
  color: var(--on-primary, #ffffff);
  border: none;
  padding: 9px 20px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-primary-resolve:hover:not(:disabled) {
  background: var(--primary-hover, #562332);
}

.btn-primary-resolve:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.font-mono {
  font-family: monospace;
}
.font-bold {
  font-weight: 700;
}
.font-medium {
  font-weight: 500;
}
.text-error {
  color: #ba1a1a;
}
.text-success {
  color: #059669;
}
</style>
