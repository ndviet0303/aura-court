<script setup lang="ts">
defineProps<{
  show: boolean
  logs: any[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>

<template>
  <div v-if="show" class="modal-backdrop modal-overlay-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <h3 class="modal-title modal-title-text">🔒 Nhật ký kiểm toán lịch hoạt động</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div v-if="logs.length === 0" class="empty-logs">
          Chưa có bản ghi thay đổi lịch hoạt động nào.
        </div>
        <div v-else class="logs-list">
          <div v-for="log in logs" :key="log.id" class="log-row">
            <strong>{{ log.action }}</strong>
            <span>Người thực hiện: {{ log.user?.name || 'Hệ thống' }}</span>
            <span class="log-time">{{ log.created_at }}</span>
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
.logs-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.log-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
}

.log-time {
  color: var(--muted);
}

.empty-logs {
  font-size: 12px;
  color: var(--muted);
  text-align: center;
  padding: 20px;
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
