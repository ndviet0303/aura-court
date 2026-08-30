<script setup lang="ts">
import { ref, watch } from 'vue'
import type { DisputeCase, ArbitrateDisputePayload } from '../../types/dispute'

const props = defineProps<{
  show: boolean
  item: DisputeCase | null
  isSubmitting?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: ArbitrateDisputePayload): void
}>()

const selectedRefundMethod = ref<'wallet' | 'payos_bank' | 'partial_wallet' | 'none'>('wallet')
const refundAmount = ref<number>(360000)
const selectedGoodwill = ref<'voucher_20' | 'reputation_points' | 'none'>('voucher_20')
const penaltyNotes = ref('')
const penaltyPoints = ref(2)
const arbitratorNotes = ref('')

watch(
  () => props.item,
  (newItem) => {
    if (newItem) {
      refundAmount.value = newItem.amountDisputed || 360000
      penaltyNotes.value = 'Lập biên bản nhắc nhở ca trực do lỗi ghi đè thủ công không kiểm tra đối soát.'
      arbitratorNotes.value = 'Chấp thuận hoàn 100% giá trị đặt sân cho nguyên đơn do lỗi hệ thống và thao tác của cơ sở.'
    }
  },
  { immediate: true }
)

function handleSubmit() {
  const goodwillMap = {
    voucher_20: 'Voucher giảm 20% đơn tiếp theo',
    reputation_points: '500 Điểm Danh Vọng Aura Rewards',
    none: 'Không bồi thường thêm',
  }

  emit('submit', {
    decision: 'resolve_and_refund',
    refundMethod: selectedRefundMethod.value,
    refundAmount: refundAmount.value,
    compensationType: selectedGoodwill.value,
    compensationValue: goodwillMap[selectedGoodwill.value],
    penaltyNotes: penaltyNotes.value,
    penaltyPoints: penaltyPoints.value,
    notes: arbitratorNotes.value,
  })
}
</script>

<template>
  <div>
    <!-- Backdrop -->
    <div
      v-if="show"
      class="drawer-backdrop"
      @click="emit('close')"
    ></div>

    <!-- Slide-over Panel -->
    <div
      class="arbitration-drawer"
      :class="{ 'drawer-open': show }"
      role="dialog"
      aria-modal="true"
    >
      <!-- Drawer Header -->
      <div class="drawer-header">
        <div>
          <h3 class="drawer-title">Lập Biên Bản Phán Quyết &amp; Bồi Thường</h3>
          <p class="drawer-sub">
            Vụ việc: #{{ item?.disputeCode }} • Cụm sân: {{ item?.facility.name }}
          </p>
        </div>
        <button type="button" class="btn-close" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Drawer Body -->
      <div class="drawer-body" style="scrollbar-width: none;">
        <!-- Financial Context -->
        <div class="context-card">
          <div class="context-row">
            <span class="context-lbl">Tổng giá trị tranh chấp:</span>
            <span class="context-val text-primary font-bold">
              {{ (item?.amountDisputed || 0).toLocaleString('vi-VN') }} ₫
            </span>
          </div>
          <div class="context-row">
            <span class="context-lbl">Nguyên đơn nhận bồi hoàn:</span>
            <span class="context-val">{{ item?.complainant.name }}</span>
          </div>
        </div>

        <!-- Form 1: Refund Method -->
        <div class="form-group">
          <label class="form-label">Phương thức bồi hoàn tài chính</label>
          <select v-model="selectedRefundMethod" class="form-select">
            <option value="wallet">Hoàn 100% vào Ví Nội Bộ (Khuyên dùng - Tức thì)</option>
            <option value="payos_bank">Hoàn về Tài khoản Ngân hàng nguồn (PayOS 1-2 ngày)</option>
            <option value="partial_wallet">Hoàn 50% vào Ví Nội Bộ (Thỏa thuận chia sẻ)</option>
            <option value="none">Không hoàn tiền (Bác bỏ bồi hoàn)</option>
          </select>
        </div>

        <!-- Form 2: Goodwill Compensation -->
        <div class="form-group">
          <label class="form-label">Gói đền bù thiện chí (Goodwill Compensation)</label>
          <div class="radio-options">
            <label class="radio-card" :class="{ selected: selectedGoodwill === 'voucher_20' }">
              <input
                v-model="selectedGoodwill"
                type="radio"
                value="voucher_20"
                class="radio-input"
              />
              <div class="radio-text">
                <span class="radio-title">Voucher Giảm 20%</span>
                <span class="radio-desc">Áp dụng cho mọi cụm sân Aura</span>
              </div>
            </label>

            <label class="radio-card" :class="{ selected: selectedGoodwill === 'reputation_points' }">
              <input
                v-model="selectedGoodwill"
                type="radio"
                value="reputation_points"
                class="radio-input"
              />
              <div class="radio-text">
                <span class="radio-title">+500 Điểm Danh Vọng</span>
                <span class="radio-desc">Cộng trực tiếp vào Aura Club</span>
              </div>
            </label>
          </div>
        </div>

        <!-- Form 3: Facility Penalty Notes -->
        <div class="form-group">
          <label class="form-label">Ghi nhận chế tài đối với Cơ sở Vận hành</label>
          <textarea
            v-model="penaltyNotes"
            class="form-textarea"
            placeholder="Nhập nội dung xử lý vi phạm ca trực..."
            rows="3"
          ></textarea>
        </div>

        <!-- Form 4: Arbitrator Notes -->
        <div class="form-group">
          <label class="form-label">Căn cứ &amp; Diễn giải Phán quyết Kiểm toán</label>
          <textarea
            v-model="arbitratorNotes"
            class="form-textarea"
            placeholder="Nhập căn cứ pháp lý & điều khoản đối soát..."
            rows="3"
          ></textarea>
        </div>

        <!-- E-Signature Verification Badge -->
        <div class="signature-box">
          <span class="material-symbols-outlined sig-icon">verified_user</span>
          <div class="sig-details">
            <span class="sig-title">Ký số xác thực bởi Trưởng ban Kiểm soát</span>
            <span class="sig-hash">SHA256: 8f9a2c...b109e4 (Lưu vào sổ cái bất biến)</span>
          </div>
        </div>
      </div>

      <!-- Drawer Footer -->
      <div class="drawer-footer">
        <button
          type="button"
          class="btn-cancel"
          :disabled="isSubmitting"
          @click="emit('close')"
        >
          Hủy bỏ
        </button>
        <button
          type="button"
          class="btn-submit"
          :disabled="isSubmitting"
          @click="handleSubmit"
        >
          <span class="material-symbols-outlined">check_circle</span>
          {{ isSubmitting ? 'Đang ban hành...' : 'Ban Hành Phán Quyết Ký Số' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(25, 28, 29, 0.4);
  backdrop-filter: blur(4px);
  z-index: 999;
  animation: fadeIn 0.2s ease-out;
}

.arbitration-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 480px;
  max-width: 90vw;
  background: #ffffff;
  z-index: 1000;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.12);
  display: flex;
  flex-direction: column;
  transform: translateX(100%);
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.drawer-open {
  transform: translateX(0);
}

.drawer-header {
  padding: 20px 24px;
  border-bottom: 1px solid #edeeef;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: #f8f9fa;
}

.drawer-title {
  font-size: 16px;
  font-weight: 800;
  color: #191c1d;
  margin: 0;
}

.drawer-sub {
  font-size: 12px;
  color: #5d5f5f;
  margin: 4px 0 0;
}

.btn-close {
  background: transparent;
  border: none;
  color: #817476;
  cursor: pointer;
  padding: 4px;
  border-radius: 50%;
}

.btn-close:hover {
  background: #edeeef;
  color: #191c1d;
}

.drawer-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.context-card {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.context-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.context-lbl {
  color: #5d5f5f;
}

.context-val {
  color: #191c1d;
  font-weight: 600;
}

.text-primary {
  color: #78555e;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  font-size: 13px;
  font-weight: 700;
  color: #191c1d;
}

.form-select,
.form-textarea {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 10px;
  padding: 10px 14px;
  font-size: 13px;
  color: #191c1d;
  outline: none;
  font-family: inherit;
  transition: border-color 0.2s;
}

.form-select:focus,
.form-textarea:focus {
  border-color: #78555e;
  background: #ffffff;
}

.radio-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.radio-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: 1px solid #e1e3e4;
  border-radius: 10px;
  background: #f8f9fa;
  cursor: pointer;
  transition: all 0.2s;
}

.radio-card:hover {
  background: #edeeef;
}

.radio-card.selected {
  background: #ffd1dc;
  border-color: #ffd1dc;
}

.radio-input {
  accent-color: #78555e;
}

.radio-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.radio-title {
  font-size: 13px;
  font-weight: 700;
  color: #191c1d;
}

.radio-desc {
  font-size: 11px;
  color: #5d5f5f;
}

.signature-box {
  background: #fdf4ff;
  border: 1px solid #f0abfc;
  border-radius: 10px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.sig-icon {
  font-size: 24px;
  color: #86198f;
}

.sig-details {
  display: flex;
  flex-direction: column;
}

.sig-title {
  font-size: 12px;
  font-weight: 700;
  color: #701a75;
}

.sig-hash {
  font-size: 10px;
  color: #86198f;
  font-family: monospace;
}

.drawer-footer {
  padding: 16px 24px;
  border-top: 1px solid #edeeef;
  background: #ffffff;
  display: flex;
  gap: 12px;
}

.btn-cancel {
  flex: 1;
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 10px;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 600;
  color: #5d5f5f;
  cursor: pointer;
}

.btn-submit {
  flex: 2;
  background: #78555e;
  border: none;
  border-radius: 10px;
  padding: 10px 18px;
  font-size: 13px;
  font-weight: 700;
  color: #ffffff;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 2px 8px rgba(120, 85, 94, 0.25);
}

.btn-submit:hover:not(:disabled) {
  background: #5e3e47;
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
</style>
