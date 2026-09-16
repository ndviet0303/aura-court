<script setup lang="ts">
import { watch } from 'vue'
import { formatCurrency } from '@/i18n'
import { AuraButton } from '@/shared/components'
import { useDropInSessionBill } from '../composables/useDropInSessionBill'
import type { SocialMatch, DropInSessionBill } from '../types/social'

const props = defineProps<{
  show: boolean
  match: SocialMatch | null
  isHost?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'updated', bill: DropInSessionBill): void
}>()

const {
  loading,
  submitting,
  error,
  conflict,
  notice,
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

watch(
  () => [props.show, props.match?.id],
  ([show, matchId]) => {
    if (show && matchId) {
      loadBill(matchId as number)
    }
  },
  { immediate: true }
)

async function handleEndSession() {
  if (!props.match) return
  await endSession(props.match.id)
  await loadBill(props.match.id)
}

async function handleSaveDraft() {
  if (!props.match) return
  await saveDraft(props.match.id)
  if (currentBill.value) {
    emit('updated', currentBill.value)
  }
}

async function handleFinalize() {
  if (!props.match) return
  const confirmed = window.confirm(
    'Bạn có chắc chắn muốn chốt bảng chi phí này? Sau khi chốt, dữ liệu sẽ ở chế độ chỉ đọc và không thể sửa đổi.'
  )
  if (!confirmed) return
  await finalizeBill(props.match.id)
  if (currentBill.value) {
    emit('updated', currentBill.value)
  }
}
</script>

<template>
  <div v-if="show && match" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <!-- Header -->
      <div class="modal-header">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-ico">receipt_long</span>
          <div>
            <h3 class="modal-title">Bảng Chi Phí Buổi Giao Lưu Cầu Lông</h3>
            <span class="modal-sub">{{ match.title }} ({{ match.venue_name }}{{ match.court_name ? ' · ' + match.court_name : '' }})</span>
          </div>
        </div>
        <button type="button" class="modal-close-btn" aria-label="Đóng" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body">
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

        <div v-if="loading" class="loading-state">
          <span class="material-symbols-outlined spin-ico">sync</span>
          <span>Đang tải thông tin chi phí...</span>
        </div>

        <div v-else-if="error" class="session-active-card" style="border-color: #ef9a9a; background: #fffbfa;">
          <div class="state-icon-box" style="background: rgba(198, 40, 40, 0.1); color: #c62828;">
            <span class="material-symbols-outlined state-icon">error</span>
          </div>
          <h4>Không thể tải bảng chi phí</h4>
          <p>{{ error }}</p>
        </div>

        <div v-else>
          <!-- Case 1: Session not ended yet -->
          <div v-if="match.status !== 'ended' && match.status !== 'completed'" class="session-active-card">
            <div class="state-icon-box">
              <span class="material-symbols-outlined state-icon">sports_score</span>
            </div>
            <h4>Buổi chơi đang diễn ra hoặc chưa kết thúc</h4>
            <p>
              Bảng chi phí thực tế chỉ có thể được tạo và chốt sau khi buổi chơi đã kết thúc.
            </p>

            <div v-if="isHost || match.is_host" style="margin-top: 16px;">
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

          <!-- Case 2: Finalized Read-Only Bill -->
          <div v-else-if="isFinalized && currentBill" class="finalized-bill-box">
            <div class="finalized-badge-row">
              <span class="badge-finalized">
                <span class="material-symbols-outlined badge-ico">verified</span>
                ĐÃ CHỐT CHI PHÍ
              </span>
              <span v-if="currentBill.finalized_at" class="finalized-time font-mono">
                {{ currentBill.finalized_at }}
              </span>
            </div>

            <div class="breakdown-grid">
              <div class="breakdown-card">
                <div class="card-icon-col">
                  <span class="material-symbols-outlined">stadium</span>
                </div>
                <div class="card-info-col">
                  <span class="label">Tiền Sân Thực Tế</span>
                  <div class="value font-mono highlight">{{ formatCurrency(currentBill.court_subtotal) }}</div>
                  <span class="sub font-mono">
                    {{ currentBill.court_duration_minutes }} phút ({{ (currentBill.court_duration_minutes / 60).toFixed(1) }}h) × {{ formatCurrency(currentBill.court_hourly_rate) }}/h
                  </span>
                </div>
              </div>

              <div class="breakdown-card">
                <div class="card-icon-col">
                  <span class="material-symbols-outlined">sports_tennis</span>
                </div>
                <div class="card-info-col">
                  <span class="label">Tiền Cầu Thực Tế</span>
                  <div class="value font-mono highlight">{{ formatCurrency(currentBill.shuttlecock_subtotal) }}</div>
                  <span class="sub font-mono">
                    {{ currentBill.shuttlecock_count }} quả × {{ formatCurrency(currentBill.shuttlecock_unit_price) }}/quả
                  </span>
                </div>
              </div>
            </div>

            <!-- Total and Share Card -->
            <div class="total-summary-card">
              <div class="total-row">
                <span class="total-label">Tổng Chi Phí Thực Tế (Sân + Cầu):</span>
                <span class="total-val font-mono">{{ formatCurrency(currentBill.total_amount) }}</span>
              </div>
              <div class="divisor-row">
                <span>Số người chia phí thực tế:</span>
                <span class="font-mono"><strong>{{ currentBill.actual_headcount }}</strong> người</span>
              </div>
              <div class="share-row">
                <div class="share-col">
                  <span class="share-label">Phần Mỗi Người Cần Đóng:</span>
                  <span class="share-hint" v-if="currentBill.is_approximate">
                    (Phép chia lẻ: {{ formatCurrency(currentBill.total_amount) }} ÷ {{ currentBill.actual_headcount }} người)
                  </span>
                </div>
                <div class="share-price font-mono">
                  <span v-if="currentBill.is_approximate" class="approx-tag" title="Giá trị làm tròn gần đúng">≈</span>
                  {{ formatCurrency(currentBill.per_person_amount) }}
                </div>
              </div>
            </div>

            <div class="transparency-notice">
              <span class="material-symbols-outlined notice-ico">info</span>
              <span>
                Bảng chi phí này được host chốt dựa trên số giờ sân và số quả cầu thực tế sử dụng. Không bao gồm nước uống hoặc phụ kiện ngoài. Các thành viên tự thanh toán trực tiếp cho host hoặc quản lý sân.
              </span>
            </div>
          </div>

          <!-- Case 3: Draft Form (Session ended, not finalized) -->
          <div v-else class="draft-form-box">
            <div v-if="!isHost && !match.is_host" class="waiting-host-card">
              <span class="material-symbols-outlined">hourglass_top</span>
              <h4>Chủ cơ sở đang cập nhật bảng chi phí</h4>
              <p>Chủ cơ sở đang xác nhận số người, giờ chơi và số quả cầu. Bảng chi phí sẽ hiển thị ngay khi được chốt.</p>
            </div>

            <form v-else @submit.prevent="handleFinalize">
              <div class="form-section-intro">
                <span class="material-symbols-outlined intro-ico">edit_note</span>
                <div>
                  <h4>Nhập Chi Phí Thực Tế Sau Buổi Chơi</h4>
                  <p>Chỉ áp dụng cho tiền sân và tiền cầu lông. Nước, đá, quấn cán thanh toán riêng tại quầy.</p>
                </div>
              </div>

              <div class="inputs-grid">
                <div class="form-group">
                  <label class="input-label">
                    <span class="material-symbols-outlined">group</span>
                    Số người chia phí thực tế:
                  </label>
                  <input
                    v-model.number="actualHeadcount"
                    type="number"
                    min="1"
                    max="100"
                    step="1"
                    class="input-control font-mono"
                    required
                  />
                  <span class="input-hint">Đã trừ người vắng, cộng thêm khách vãng lai (walk-in)</span>
                </div>

                <div class="form-group">
                  <label class="input-label">
                    <span class="material-symbols-outlined">timer</span>
                    Thời gian chơi thực tế (phút):
                  </label>
                  <input
                    v-model.number="courtDurationMinutes"
                    type="number"
                    min="1"
                    step="1"
                    class="input-control font-mono"
                    required
                  />
                  <span class="input-hint">{{ (courtDurationMinutes / 60).toFixed(1) }} giờ (ví dụ: 90 phút = 1.5 giờ)</span>
                </div>

                <div class="form-group">
                  <label class="input-label">
                    <span class="material-symbols-outlined">stadium</span>
                    Đơn giá sân (VNĐ/giờ):
                  </label>
                  <input
                    v-model.number="courtHourlyRate"
                    type="number"
                    min="0"
                    step="1000"
                    class="input-control font-mono"
                    required
                  />
                  <span class="input-hint">Xác nhận đơn giá giờ sân của cơ sở</span>
                </div>

                <div class="form-group">
                  <label class="input-label">
                    <span class="material-symbols-outlined">sports_tennis</span>
                    Số quả cầu đã dùng:
                  </label>
                  <input
                    v-model.number="shuttlecockCount"
                    type="number"
                    min="0"
                    step="1"
                    class="input-control font-mono"
                    required
                  />
                  <span class="input-hint">Nhập số nguyên quả cầu đã phát (0 nếu không dùng)</span>
                </div>

                <div class="form-group">
                  <label class="input-label">
                    <span class="material-symbols-outlined">payments</span>
                    Đơn giá cầu (VNĐ/quả):
                  </label>
                  <input
                    v-model.number="shuttlecockUnitPrice"
                    type="number"
                    min="0"
                    step="1000"
                    class="input-control font-mono"
                    required
                  />
                  <span class="input-hint">Ví dụ: 25.000 VNĐ/quả</span>
                </div>
              </div>

              <!-- Realtime Preview -->
              <div class="realtime-preview-card">
                <div class="preview-head">
                  <span class="material-symbols-outlined">preview</span>
                  <span>Xem trước kết quả tính toán tự động</span>
                </div>

                <div class="preview-row">
                  <span>Tiền sân ({{ courtDurationMinutes }} phút):</span>
                  <span class="font-mono"><strong>{{ formatCurrency(courtSubtotal) }}</strong></span>
                </div>

                <div class="preview-row">
                  <span>Tiền cầu ({{ shuttlecockCount }} quả):</span>
                  <span class="font-mono"><strong>{{ formatCurrency(shuttlecockSubtotal) }}</strong></span>
                </div>

                <div class="preview-divider"></div>

                <div class="preview-row preview-total">
                  <span>Tổng cộng:</span>
                  <span class="font-mono highlight">{{ formatCurrency(totalAmount) }}</span>
                </div>

                <div class="preview-row preview-share">
                  <span>Mỗi người ({{ actualHeadcount }} người):</span>
                  <span class="font-mono per-person-val">
                    <span v-if="isApproximate">≈ </span>
                    {{ formatCurrency(perPersonAmount) }}
                  </span>
                </div>
              </div>

              <div class="form-actions-row">
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
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  background: #fff;
  border-radius: 16px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
}

.modal-lg {
  max-width: 680px;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #eee;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-ico {
  font-size: 28px;
  color: #8b263e;
}

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #111;
}

.modal-sub {
  font-size: 13px;
  color: #666;
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 20px;
  cursor: pointer;
  color: #888;
}

.modal-body {
  padding: 24px;
}

.alert-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 14px;
  margin-bottom: 16px;
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

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 40px;
  color: #666;
}

.spin-ico {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.session-active-card,
.waiting-host-card {
  text-align: center;
  padding: 40px 20px;
  background: #fdfdfd;
  border: 1px dashed #ddd;
  border-radius: 12px;
}

.state-icon-box {
  display: inline-flex;
  padding: 16px;
  border-radius: 50%;
  background: rgba(139, 38, 62, 0.1);
  color: #8b263e;
  margin-bottom: 12px;
}

.state-icon {
  font-size: 36px;
}

.finalized-badge-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
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
  gap: 14px;
  margin-bottom: 18px;
}

.breakdown-card {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #f9f9f9;
  border-radius: 12px;
  border: 1px solid #eee;
}

.card-icon-col {
  color: #8b263e;
}

.card-info-col .label {
  display: block;
  font-size: 12px;
  color: #666;
}

.card-info-col .value {
  font-size: 18px;
  font-weight: 700;
  color: #111;
  margin: 4px 0;
}

.card-info-col .sub {
  font-size: 11px;
  color: #888;
}

.total-summary-card {
  background: #fff9fa;
  border: 1px solid rgba(139, 38, 62, 0.2);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
}

.total-row,
.divisor-row {
  display: flex;
  justify-content: space-between;
  font-size: 15px;
  margin-bottom: 10px;
}

.total-val {
  font-size: 18px;
  font-weight: 700;
  color: #8b263e;
}

.share-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 14px;
  border-top: 1px dashed rgba(139, 38, 62, 0.2);
}

.share-label {
  font-size: 16px;
  font-weight: 700;
  color: #111;
}

.share-hint {
  display: block;
  font-size: 12px;
  color: #666;
}

.share-price {
  font-size: 24px;
  font-weight: 800;
  color: #8b263e;
}

.transparency-notice {
  display: flex;
  gap: 10px;
  background: #f5f5f5;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13px;
  color: #555;
  line-height: 1.5;
}

.form-section-intro {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.form-section-intro h4 {
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 700;
}

.form-section-intro p {
  margin: 0;
  font-size: 13px;
  color: #666;
}

.inputs-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.input-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #333;
}

.input-control {
  padding: 10px 14px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 15px;
  outline: none;
  transition: border-color 0.2s;
}

.input-control:focus {
  border-color: #8b263e;
}

.input-hint {
  font-size: 11px;
  color: #777;
}

.realtime-preview-card {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 16px 20px;
  margin-bottom: 20px;
  border: 1px solid #eee;
}

.preview-head {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 14px;
  color: #333;
  margin-bottom: 12px;
}

.preview-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  margin-bottom: 8px;
  color: #555;
}

.preview-divider {
  height: 1px;
  background: #e0e0e0;
  margin: 10px 0;
}

.preview-total {
  font-size: 16px;
  font-weight: 700;
  color: #111;
}

.preview-share {
  font-size: 17px;
  font-weight: 700;
  color: #8b263e;
}

.per-person-val {
  font-size: 18px;
  font-weight: 800;
}

.form-actions-row {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
</style>
