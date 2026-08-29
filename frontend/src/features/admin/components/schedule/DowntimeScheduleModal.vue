<script setup lang="ts">
import { ref } from 'vue'
import type { Court } from '@/features/court/types/court'

defineProps<{
  show: boolean
  courts: Court[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: { court_id: number; starts_at: string; ends_at: string; reason: string }): void
}>()

const courtId = ref<number>(1)
const startsAt = ref('2026-10-12T08:00')
const endsAt = ref('2026-10-14T17:00')
const reason = ref('Bảo dưỡng & Thay thảm sân thi đấu')

function handleSubmit() {
  if (!startsAt.value || !endsAt.value || !reason.value.trim()) return
  emit('submit', {
    court_id: courtId.value,
    starts_at: startsAt.value,
    ends_at: endsAt.value,
    reason: reason.value.trim(),
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop modal-overlay-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title modal-title-text">🔒 Lên lịch bảo trì & Khoá sân</h3>
        <button class="modal-close-btn btn-modal-close" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Chọn sân cần bảo trì:</label>
          <select v-model="courtId" class="form-input">
            <option v-for="c in courts" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Thời gian bắt đầu khoá:</label>
          <input v-model="startsAt" type="datetime-local" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">Thời gian kết thúc khoá:</label>
          <input v-model="endsAt" type="datetime-local" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">Lý do bảo trì:</label>
          <input v-model="reason" type="text" class="form-input" placeholder="Vd: Sơn lại vạch, sửa đèn..." />
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" @click="handleSubmit">
          Xác Nhận Khoá Sân
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
