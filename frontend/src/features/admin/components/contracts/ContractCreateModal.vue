<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'create', payload: any): void
}>()

const customerName = ref('')
const phone = ref('')
const courtName = ref('Sân 1 (Sân Huấn Luyện)')
const weekday = ref('Thứ 2, 4, 6')
const timeSlot = ref('18:00 - 20:00')
const monthlyAmount = ref(4800000)

function handleCreate() {
  if (!customerName.value.trim() || !phone.value.trim()) return
  emit('create', {
    customer_name: customerName.value.trim(),
    phone: phone.value.trim(),
    court_name: courtName.value,
    weekday: weekday.value,
    time_slot: timeSlot.value,
    total_monthly_amount: monthlyAmount.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">➕ Tạo Hợp Đồng Đặt Sân Định Kỳ Mới</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <form @submit.prevent="handleCreate">
        <div class="modal-body">
          <div class="create-form-grid">
            <div class="form-group">
              <label class="form-label">Tên khách hàng / CLB đại diện:</label>
              <input v-model="customerName" type="text" class="form-input" placeholder="Vd: CLB Cầu Lông Tân Bình..." />
            </div>

            <div class="form-group">
              <label class="form-label">Số điện thoại liên hệ:</label>
              <input v-model="phone" type="tel" class="form-input" placeholder="0901 234 567" />
            </div>

            <div class="form-group">
              <label class="form-label">Chọn sân thi đấu:</label>
              <input v-model="courtName" type="text" class="form-input" />
            </div>

            <div class="form-group">
              <label class="form-label">Lịch thứ trong tuần:</label>
              <input v-model="weekday" type="text" class="form-input" placeholder="Vd: Thứ 2, 4, 6" />
            </div>

            <div class="form-group">
              <label class="form-label">Khung giờ cố định:</label>
              <input v-model="timeSlot" type="text" class="form-input" placeholder="18:00 - 20:00" />
            </div>

            <div class="form-group">
              <label class="form-label">Giá trị hợp đồng mỗi tháng (VND):</label>
              <input v-model.number="monthlyAmount" type="number" step="100000" class="form-input" />
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="emit('close')">Huỷ</button>
          <button
            type="submit"
            class="btn-primary"
            :disabled="!customerName.trim() || !phone.trim()"
          >
            Ký Hợp Đồng & Kích Hoạt
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.create-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 24px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
