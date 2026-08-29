<script setup lang="ts">
import { ref } from 'vue'
import type { SpecialDateItem } from '../../composables/useAdminSchedule'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: { date: string; name: string; type: SpecialDateItem['type']; hours?: string }): void
}>()

const date = ref('2026-11-20')
const name = ref('Ngày Nhà Giáo VN 20/11')
const type = ref<SpecialDateItem['type']>('special_hours')
const hours = ref('08:00 - 22:00')

function handleSubmit() {
  if (!date.value || !name.value.trim()) return
  emit('submit', {
    date: date.value,
    name: name.value.trim(),
    type: type.value,
    hours: hours.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop modal-overlay-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title modal-title-text">🎉 Thêm ngày đặc biệt / Lễ Tết</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Chọn ngày diễn ra:</label>
          <input v-model="date" type="date" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">Tên dịp đặc biệt:</label>
          <input v-model="name" type="text" class="form-input" placeholder="Vd: Giáng Sinh, Tết Dương Lịch..." />
        </div>

        <div class="form-group">
          <label class="form-label">Loại cấu hình:</label>
          <select v-model="type" class="form-input">
            <option value="holiday">Ngày nghỉ lễ (Holiday)</option>
            <option value="special_hours">Khung giờ đặc biệt</option>
            <option value="closed">Đóng cửa toàn cơ sở</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Khung giờ áp dụng:</label>
          <input v-model="hours" type="text" class="form-input" placeholder="08:00 - 22:00" />
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" @click="handleSubmit">
          Thêm Ngày Đặc Biệt
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
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
