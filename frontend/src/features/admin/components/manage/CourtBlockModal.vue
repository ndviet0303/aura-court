<script setup lang="ts">
import { ref } from 'vue'
import type { Court } from '@/features/court/types/court'

const props = defineProps<{
  show: boolean
  court: Court | null
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'addBlock', payload: { starts_at: string; ends_at: string; reason: string }): void
  (e: 'deleteBlock', blockId: number): void
}>()

const startsAt = ref('')
const endsAt = ref('')
const reason = ref('Bảo dưỡng mặt thảm định kỳ')

function handleAdd() {
  if (!startsAt.value || !endsAt.value) return
  emit('addBlock', {
    starts_at: startsAt.value,
    ends_at: endsAt.value,
    reason: reason.value,
  })
  startsAt.value = ''
  endsAt.value = ''
}
</script>

<template>
  <div v-if="show && court" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">🔒 Khoá Sân Bảo Trì: {{ court.name }}</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- Add Block Form -->
        <div class="block-form-box">
          <h4 class="form-sub-title">Thêm Khung Giờ Khoá Sân</h4>

          <div class="form-group">
            <label class="form-label">Thời gian bắt đầu khoá:</label>
            <input v-model="startsAt" type="datetime-local" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">Thời gian kết thúc:</label>
            <input v-model="endsAt" type="datetime-local" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">Lý do khoá sân:</label>
            <input
              v-model="reason"
              type="text"
              class="form-input"
              placeholder="Vd: Thay thảm, giải đấu nội bộ..."
            />
          </div>

          <button
            class="btn-add-block"
            :disabled="!startsAt || !endsAt || saving"
            @click="handleAdd"
          >
            + Tạo Lịch Khoá Sân
          </button>
        </div>

        <!-- Existing Blocks List -->
        <div class="existing-blocks-section">
          <h4 class="form-sub-title">Danh Sách Lịch Khoá Hiện Tại ({{ court.blocks?.length || 0 }})</h4>

          <div v-if="!court.blocks?.length" class="empty-blocks-msg">
            Chưa có lịch bảo trì hoặc khoá sân nào.
          </div>

          <div v-else class="blocks-list">
            <div v-for="block in court.blocks" :key="block.id" class="block-item-row">
              <div class="block-info">
                <strong>{{ block.reason || 'Khoá sân bảo trì' }}</strong>
                <span>{{ block.starts_at }} ➔ {{ block.ends_at }}</span>
              </div>
              <button class="btn-del-block" @click="emit('deleteBlock', block.id)">
                Xoá
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.block-form-box {
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 20px;
}

.form-sub-title {
  margin: 0 0 12px;
  font-size: 13px;
  font-weight: 700;
}

.btn-add-block {
  width: 100%;
  padding: 10px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  margin-top: 6px;
}

.btn-add-block:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.existing-blocks-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.empty-blocks-msg {
  font-size: 12px;
  color: var(--muted);
  font-style: italic;
}

.blocks-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 200px;
  overflow-y: auto;
}

.block-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.block-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
}

.block-info span {
  color: var(--muted);
  font-size: 11px;
}

.btn-del-block {
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: transparent;
  color: #ef4444;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>
