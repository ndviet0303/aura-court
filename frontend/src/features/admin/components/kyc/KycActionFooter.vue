<script setup lang="ts">
const props = defineProps<{
  selectedReason: string
  customReason: string
  isSubmitting: boolean
  isApproved?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:selectedReason', val: string): void
  (e: 'update:customReason', val: string): void
  (e: 'approve'): void
  (e: 'reject'): void
  (e: 'requestSupplement'): void
}>()

const sampleReasons = [
  { value: 'Ảnh CCCD mờ, mất góc hoặc không rõ số', label: 'Ảnh CCCD mờ, mất góc hoặc không rõ số' },
  { value: 'Tên thương hiệu sai lệch hoàn toàn với Giấy phép ĐKKD', label: 'Tên thương hiệu sai lệch hoàn toàn với Giấy phép ĐKKD' },
  { value: 'Tài khoản ngân hàng thụ hưởng không chính chủ', label: 'Tài khoản ngân hàng thụ hưởng không chính chủ' },
  { value: 'Thiếu hợp đồng thuê mặt bằng / quyền sử dụng sân', label: 'Thiếu hợp đồng thuê mặt bằng / quyền sử dụng sân' },
  { value: 'Mã số thuế doanh nghiệp đang trong tình trạng tạm ngừng hoạt động', label: 'Mã số thuế doanh nghiệp đang tạm ngừng' },
]
</script>

<template>
  <div class="kyc-action-footer">
    <div class="footer-left">
      <select
        :value="selectedReason"
        class="reason-select"
        @change="emit('update:selectedReason', ($event.target as HTMLSelectElement).value)"
      >
        <option value="">-- Chọn mẫu lý do từ chối / yêu cầu bổ sung --</option>
        <option v-for="r in sampleReasons" :key="r.value" :value="r.value">
          {{ r.label }}
        </option>
      </select>
    </div>

    <div class="footer-actions">
      <button
        type="button"
        class="btn-action supplement"
        :disabled="isSubmitting"
        @click="emit('requestSupplement')"
      >
        <span class="material-symbols-outlined icon-18">edit_note</span>
        Yêu cầu bổ sung
      </button>

      <button
        type="button"
        class="btn-action reject"
        :disabled="isSubmitting"
        @click="emit('reject')"
      >
        <span class="material-symbols-outlined icon-18">block</span>
        Từ chối hồ sơ
      </button>

      <button
        type="button"
        class="btn-action approve"
        :disabled="isSubmitting"
        @click="emit('approve')"
      >
        <span class="material-symbols-outlined icon-18">verified</span>
        <span>Phê duyệt & Kích hoạt Chủ sân</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.kyc-action-footer {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(8px);
  border: 1px solid #e1e3e4;
  border-radius: 16px;
  padding: 14px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
  flex-wrap: wrap;
}

.footer-left {
  flex: 1;
  min-width: 280px;
}

.reason-select {
  width: 100%;
  border: 1px solid #e1e3e4;
  background: #ffffff;
  border-radius: 10px;
  padding: 10px 14px;
  font-size: 13px;
  color: #191c1d;
  outline: none;
  font-family: inherit;
  cursor: pointer;
}

.reason-select:focus {
  border-color: #78555e;
}

.footer-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-action {
  border: none;
  padding: 10px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s ease;
}

.btn-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-action.supplement {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  color: #191c1d;
}

.btn-action.supplement:hover:not(:disabled) {
  background: #edeeef;
}

.btn-action.reject {
  background: #fee2e2;
  border: 1px solid #fecaca;
  color: #b91c1c;
}

.btn-action.reject:hover:not(:disabled) {
  background: #fecaca;
}

.btn-action.approve {
  background: #78555e;
  color: #ffffff;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
}

.btn-action.approve:hover:not(:disabled) {
  background: #5e3e47;
  box-shadow: 0 6px 18px rgba(120, 85, 94, 0.35);
  transform: translateY(-1px);
}
</style>
