<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Organization } from '../../composables/useOrganizationOwner'

const props = defineProps<{
  org: Organization
}>()

const emit = defineEmits<{
  (e: 'save', payload: Partial<Organization>): void
}>()

const autoApprove = ref(props.org.auto_approve_booking ?? true)
const resaleFee = ref(props.org.resale_fee_percent ?? 5)
const graceHours = ref(props.org.cancellation_grace_hours ?? 12)

watch(
  () => props.org,
  (o) => {
    autoApprove.value = o.auto_approve_booking ?? true
    resaleFee.value = o.resale_fee_percent ?? 5
    graceHours.value = o.cancellation_grace_hours ?? 12
  }
)

function handleSave() {
  emit('save', {
    auto_approve_booking: autoApprove.value,
    resale_fee_percent: resaleFee.value,
    cancellation_grace_hours: graceHours.value,
  })
}
</script>

<template>
  <div class="org-policies-tab-card">
    <h3 class="tab-title">⚙️ Chính Sách Vận Hành & Tự Động Hóa</h3>

    <form class="policies-form" @submit.prevent="handleSave">
      <div class="policy-item-row">
        <div class="p-text">
          <strong>Tự Động Duyệt Đơn Đặt Sân</strong>
          <span>Đơn đặt sân thanh toán VietQR / Thẻ thành công sẽ tự động giữ lịch mà không cần nhân viên bấm duyệt.</span>
        </div>
        <label class="switch-lbl">
          <input v-model="autoApprove" type="checkbox" />
          <span class="slider"></span>
        </label>
      </div>

      <div class="form-grid grid-stack-mobile">
        <div class="form-group">
          <label class="form-label">Phí sàn chuyển nhượng P2P (%):</label>
          <input v-model.number="resaleFee" type="number" min="0" max="20" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">Thời hạn hủy đặt sân trước giờ chơi (tiếng):</label>
          <input v-model.number="graceHours" type="number" min="1" max="72" class="form-input" />
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-save">
          💾 Lưu Cấu Hình Chính Sách
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.org-policies-tab-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.tab-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.policies-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.policy-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  gap: 16px;
}

.p-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.p-text strong {
  font-size: 14px;
}

.p-text span {
  font-size: 12px;
  color: var(--muted);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
}

.btn-save {
  padding: 10px 24px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
