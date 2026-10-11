<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { formatCurrency } from '@/i18n'
import { AuraButton } from '@/shared/components'
import { useAuthStore } from '@/stores/auth'
import { useDropInSessionBill } from '../composables/useDropInSessionBill'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const matchId = computed(() => route.params.id as string)

const {
  loading,
  submitting,
  error,
  conflict,
  notice,
  currentMatch,
  currentBill,
  actualHeadcount,
  courtDurationMinutes,
  courtHourlyRate,
  shuttlecockCount,
  shuttlecockUnitPrice,
  courtSubtotal,
  shuttlecockSubtotal,
  totalAmount,
  perPersonAmount,
  isApproximate,
  isFinalized,
  loadBill,
  endSession,
  saveDraft,
  finalizeBill,
} = useDropInSessionBill()

const isHost = computed(() => {
  if (!currentMatch.value || !auth.user) return false
  return currentMatch.value.host_id === auth.user.id || currentMatch.value.is_host === true
})

onMounted(() => {
  if (matchId.value) {
    loadBill(matchId.value)
  }
})

async function handleEndSession() {
  await endSession(matchId.value)
  await loadBill(matchId.value)
}

async function handleSaveDraft() {
  await saveDraft(matchId.value)
}

async function handleFinalize() {
  const confirmed = window.confirm(
    'Bạn có chắc chắn muốn chốt bảng chi phí này? Sau khi chốt, dữ liệu sẽ ở chế độ chỉ đọc và không thể sửa đổi.',
  )
  if (!confirmed) return
  await finalizeBill(matchId.value)
}
</script>

<template>
  <main class="drop-in-bill-page">
    <div class="container">
      <div class="top-nav-bar">
        <AuraButton
          variant="ghost"
          size="sm"
          icon="arrow_back"
          @click="router.push('/matchmaking')"
        >
          Quay Lại Kèo Giao Lưu
        </AuraButton>
      </div>

      <div class="bill-page-card">
        <div class="page-head">
          <div class="title-row">
            <span class="material-symbols-outlined head-ico">receipt_long</span>
            <div>
              <h1 class="page-title">Bảng Chi Phí Buổi Giao Lưu Cầu Lông</h1>
              <span v-if="currentMatch" class="page-sub">
                {{ currentMatch.title }} · {{ currentMatch.venue_name
                }}{{ currentMatch.court_name ? ' (' + currentMatch.court_name + ')' : '' }}
              </span>
            </div>
          </div>
        </div>

        <div class="page-body">
          <!-- Alerts -->
          <div v-if="notice" class="alert-box alert-success">
            <span class="material-symbols-outlined alert-ico">check_circle</span>
            <span>{{ notice }}</span>
          </div>

          <div v-if="conflict" class="alert-box alert-warning">
            <span class="material-symbols-outlined alert-ico">warning</span>
            <span>{{ conflict }}</span>
          </div>

          <div v-if="error" class="alert-box alert-error">
            <span class="material-symbols-outlined alert-ico">error</span>
            <span>{{ error }}</span>
          </div>

          <div v-if="loading" class="loading-box">
            <span class="material-symbols-outlined spin-ico">sync</span>
            <span>Đang tải thông tin bảng chi phí...</span>
          </div>

          <div v-else>
            <!-- Case 1: Session still active -->
            <div
              v-if="
                currentMatch &&
                currentMatch.status !== 'ended' &&
                currentMatch.status !== 'completed'
              "
              class="session-active-card"
            >
              <span class="material-symbols-outlined state-icon">sports_score</span>
              <h3>Buổi chơi đang diễn ra hoặc chưa kết thúc</h3>
              <p>Bảng chi phí thực tế chỉ có thể được tạo và chốt sau khi buổi chơi kết thúc.</p>
              <div v-if="isHost" style="margin-top: 16px">
                <AuraButton
                  variant="primary"
                  size="md"
                  icon="stop_circle"
                  :loading="submitting"
                  @click="handleEndSession"
                >
                  Kết Thúc Buổi Chơi Ngay
                </AuraButton>
              </div>
            </div>

            <!-- Case 2: Finalized Read-Only -->
            <div v-else-if="isFinalized && currentBill" class="finalized-box">
              <div class="finalized-badge-row">
                <span class="badge-finalized">
                  <span class="material-symbols-outlined badge-ico">verified</span>
                  ĐÃ CHỐT CHI PHÍ
                </span>
                <span v-if="currentBill.finalized_at" class="finalized-time font-mono">
                  Thời điểm chốt: {{ currentBill.finalized_at }}
                </span>
              </div>

              <div class="breakdown-grid">
                <div class="breakdown-card">
                  <span class="material-symbols-outlined item-ico">stadium</span>
                  <div class="item-info">
                    <span class="label">Tiền Sân Thực Tế</span>
                    <div class="value font-mono highlight">
                      {{ formatCurrency(currentBill.court_subtotal) }}
                    </div>
                    <span class="sub font-mono">
                      {{ currentBill.court_duration_minutes }} phút ({{
                        (currentBill.court_duration_minutes / 60).toFixed(1)
                      }}h) × {{ formatCurrency(currentBill.court_hourly_rate) }}/h
                    </span>
                  </div>
                </div>

                <div class="breakdown-card">
                  <span class="material-symbols-outlined item-ico">sports_tennis</span>
                  <div class="item-info">
                    <span class="label">Tiền Cầu Thực Tế</span>
                    <div class="value font-mono highlight">
                      {{ formatCurrency(currentBill.shuttlecock_subtotal) }}
                    </div>
                    <span class="sub font-mono">
                      {{ currentBill.shuttlecock_count }} quả ×
                      {{ formatCurrency(currentBill.shuttlecock_unit_price) }}/quả
                    </span>
                  </div>
                </div>
              </div>

              <div class="total-summary-card">
                <div class="total-row">
                  <span>Tổng chi phí thực tế (Sân + Cầu):</span>
                  <span class="total-val font-mono">{{
                    formatCurrency(currentBill.total_amount)
                  }}</span>
                </div>
                <div class="divisor-row">
                  <span>Số người chia phí thực tế:</span>
                  <span class="font-mono"
                    ><strong>{{ currentBill.actual_headcount }}</strong> người</span
                  >
                </div>
                <div class="share-row">
                  <div class="share-col">
                    <span class="share-label">Phần mỗi người:</span>
                    <span v-if="currentBill.is_approximate" class="share-hint">
                      (Phép chia lẻ: {{ formatCurrency(currentBill.total_amount) }} ÷
                      {{ currentBill.actual_headcount }} người)
                    </span>
                  </div>
                  <div class="share-price font-mono">
                    <span v-if="currentBill.is_approximate">≈ </span>
                    {{ formatCurrency(currentBill.per_person_amount) }}
                  </div>
                </div>
              </div>

              <div class="transparency-notice">
                <span class="material-symbols-outlined">info</span>
                <span>
                  Bảng chi phí này được chốt trên thực tế sử dụng. Không bao gồm nước uống hoặc phụ
                  kiện ngoài. Hệ thống không tự động thu tiền hay trừ ví Aura.
                </span>
              </div>
            </div>

            <!-- Case 3: Draft Input Form -->
            <div v-else class="draft-form-box">
              <div v-if="!isHost" class="waiting-box">
                <span class="material-symbols-outlined">hourglass_top</span>
                <h3>Chủ cơ sở đang cập nhật bảng chi phí</h3>
                <p>Vui lòng quay lại sau khi chủ cơ sở xác nhận và chốt bảng chi phí thực tế.</p>
              </div>

              <form v-else @submit.prevent="handleFinalize">
                <div class="form-intro">
                  <span class="material-symbols-outlined">edit_note</span>
                  <div>
                    <h3>Nhập Chi Phí Thực Tế Sau Buổi Chơi</h3>
                    <p>
                      Chỉ áp dụng cho tiền sân và tiền cầu lông. Nước, đá, quấn cán thanh toán riêng
                      tại quầy.
                    </p>
                  </div>
                </div>

                <div class="inputs-grid">
                  <div class="form-group">
                    <label class="label">Số người chia phí thực tế:</label>
                    <input
                      v-model.number="actualHeadcount"
                      type="number"
                      min="1"
                      class="input font-mono"
                      required
                    />
                    <span class="hint">Đã trừ người vắng, cộng thêm khách vãng lai (walk-in)</span>
                  </div>

                  <div class="form-group">
                    <label class="label">Thời gian chơi thực tế (phút):</label>
                    <input
                      v-model.number="courtDurationMinutes"
                      type="number"
                      min="1"
                      class="input font-mono"
                      required
                    />
                    <span class="hint">{{ (courtDurationMinutes / 60).toFixed(1) }} giờ</span>
                  </div>

                  <div class="form-group">
                    <label class="label">Đơn giá sân (VNĐ/giờ):</label>
                    <input
                      v-model.number="courtHourlyRate"
                      type="number"
                      min="0"
                      step="1000"
                      class="input font-mono"
                      required
                    />
                  </div>

                  <div class="form-group">
                    <label class="label">Số quả cầu đã dùng:</label>
                    <input
                      v-model.number="shuttlecockCount"
                      type="number"
                      min="0"
                      step="1"
                      class="input font-mono"
                      required
                    />
                  </div>

                  <div class="form-group">
                    <label class="label">Đơn giá cầu (VNĐ/quả):</label>
                    <input
                      v-model.number="shuttlecockUnitPrice"
                      type="number"
                      min="0"
                      step="1000"
                      class="input font-mono"
                      required
                    />
                  </div>
                </div>

                <div class="preview-card">
                  <div class="preview-line">
                    <span>Tiền sân:</span>
                    <span class="font-mono"
                      ><strong>{{ formatCurrency(courtSubtotal) }}</strong></span
                    >
                  </div>
                  <div class="preview-line">
                    <span>Tiền cầu:</span>
                    <span class="font-mono"
                      ><strong>{{ formatCurrency(shuttlecockSubtotal) }}</strong></span
                    >
                  </div>
                  <div class="divider"></div>
                  <div class="preview-line preview-total">
                    <span>Tổng cộng:</span>
                    <span class="font-mono highlight">{{ formatCurrency(totalAmount) }}</span>
                  </div>
                  <div class="preview-line preview-share">
                    <span>Mỗi người ({{ actualHeadcount }} người):</span>
                    <span class="font-mono per-val">
                      <span v-if="isApproximate">≈ </span>
                      {{ formatCurrency(perPersonAmount) }}
                    </span>
                  </div>
                </div>

                <div class="btn-row">
                  <AuraButton
                    type="button"
                    variant="secondary"
                    size="md"
                    icon="save"
                    :loading="submitting"
                    @click="handleSaveDraft"
                  >
                    Lưu Bản Nháp
                  </AuraButton>
                  <AuraButton
                    type="submit"
                    variant="primary"
                    size="md"
                    icon="lock"
                    :loading="submitting"
                  >
                    Chốt Bảng Chi Phí (Chỉ Đọc)
                  </AuraButton>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.drop-in-bill-page {
  padding: 32px 16px;
  background: #f8f9fa;
  min-height: 100vh;
}

.container {
  max-width: 800px;
  margin: 0 auto;
}

.top-nav-bar {
  margin-bottom: 16px;
}

.bill-page-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
  border: 1px solid #eee;
  overflow: hidden;
}

.page-head {
  padding: 24px;
  border-bottom: 1px solid #eee;
  background: #fff;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.head-ico {
  font-size: 36px;
  color: #8b263e;
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: #111;
}

.page-sub {
  font-size: 14px;
  color: #666;
}

.page-body {
  padding: 24px;
}

.alert-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 14px;
  margin-bottom: 20px;
}

.alert-success {
  background: #e8f5e9;
  color: #2e7d32;
}
.alert-warning {
  background: #fff8e1;
  color: #f57f17;
}
.alert-error {
  background: #ffebee;
  color: #c62828;
}

.loading-box,
.session-active-card,
.waiting-box {
  text-align: center;
  padding: 48px 24px;
  color: #666;
}

.state-icon {
  font-size: 48px;
  color: #8b263e;
  margin-bottom: 12px;
}

.spin-ico {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.finalized-badge-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.badge-finalized {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #2e7d32;
  color: #fff;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 700;
}

.breakdown-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}

.breakdown-card {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #f9f9f9;
  border-radius: 12px;
  border: 1px solid #eee;
}

.item-ico {
  color: #8b263e;
  font-size: 28px;
}

.item-info .label {
  font-size: 12px;
  color: #666;
  display: block;
}

.item-info .value {
  font-size: 20px;
  font-weight: 800;
  color: #111;
  margin: 4px 0;
}

.item-info .sub {
  font-size: 11px;
  color: #888;
}

.total-summary-card {
  background: #fff9fa;
  border: 1px solid rgba(139, 38, 62, 0.2);
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 20px;
}

.total-row,
.divisor-row {
  display: flex;
  justify-content: space-between;
  font-size: 16px;
  margin-bottom: 12px;
}

.total-val {
  font-size: 20px;
  font-weight: 800;
  color: #8b263e;
}

.share-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 16px;
  border-top: 1px dashed rgba(139, 38, 62, 0.2);
}

.share-label {
  font-size: 17px;
  font-weight: 800;
}

.share-hint {
  display: block;
  font-size: 12px;
  color: #666;
}

.share-price {
  font-size: 28px;
  font-weight: 800;
  color: #8b263e;
}

.transparency-notice {
  display: flex;
  gap: 10px;
  background: #f5f5f5;
  padding: 14px 18px;
  border-radius: 10px;
  font-size: 13px;
  color: #555;
  line-height: 1.5;
}

.form-intro {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.form-intro h3 {
  margin: 0 0 4px;
  font-size: 17px;
  font-weight: 700;
}

.form-intro p {
  margin: 0;
  font-size: 13px;
  color: #666;
}

.inputs-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 24px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group .label {
  font-size: 13px;
  font-weight: 600;
  color: #333;
}

.form-group .input {
  padding: 10px 14px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 15px;
  outline: none;
}

.form-group .hint {
  font-size: 11px;
  color: #777;
}

.preview-card {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 18px 22px;
  margin-bottom: 24px;
  border: 1px solid #eee;
}

.preview-line {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  margin-bottom: 8px;
}

.divider {
  height: 1px;
  background: #e0e0e0;
  margin: 12px 0;
}

.preview-total {
  font-size: 16px;
  font-weight: 700;
}

.preview-share {
  font-size: 18px;
  font-weight: 800;
  color: #8b263e;
}

.per-val {
  font-size: 20px;
  font-weight: 800;
}

.btn-row {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
</style>
