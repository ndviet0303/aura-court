<script setup lang="ts">
import type { DisputeCase } from '../../types/dispute'

defineProps<{
  item: DisputeCase
  isSubmitting?: boolean
}>()

const emit = defineEmits<{
  (e: 'openArbitration'): void
  (e: 'dismiss'): void
  (e: 'relocate'): void
}>()
</script>

<template>
  <div class="action-toolbar">
    <div class="toolbar-content">
      <!-- Button 1: Dismiss -->
      <button
        type="button"
        class="btn-dismiss"
        :disabled="isSubmitting || item.status === 'resolved'"
        @click="emit('dismiss')"
      >
        <span class="material-symbols-outlined">close</span>
        Bác Bỏ Khiếu Nại
      </button>

      <!-- Button 2: Relocate Court -->
      <button
        type="button"
        class="btn-relocate"
        :disabled="isSubmitting"
        @click="emit('relocate')"
      >
        <span class="material-symbols-outlined">swap_horiz</span>
        Điều Chuyển Sân 7 Thay Thế
      </button>

      <!-- Button 3: Main Action - Arbitrate & Refund -->
      <button
        type="button"
        class="btn-arbitrate"
        :disabled="isSubmitting"
        @click="emit('openArbitration')"
      >
        <span class="material-symbols-outlined">gavel</span>
        Lập Biên Bản Phán Quyết &amp; Đền Bù
      </button>
    </div>
  </div>
</template>

<style scoped>
.action-toolbar {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 16px;
  padding: 16px 24px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.toolbar-content {
  display: flex;
  gap: 12px;
  align-items: center;
}

.btn-dismiss {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 12px;
  padding: 12px 18px;
  font-size: 13px;
  font-weight: 700;
  color: #5d5f5f;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-dismiss:hover:not(:disabled) {
  background: #edeeef;
  color: #191c1d;
}

.btn-relocate {
  flex: 1.2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: #ffd1dc;
  border: 1px solid #ffd1dc;
  border-radius: 12px;
  padding: 12px 18px;
  font-size: 13px;
  font-weight: 700;
  color: #78555e;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-relocate:hover:not(:disabled) {
  background: #ffb8c8;
}

.btn-arbitrate {
  flex: 1.8;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: #78555e;
  border: none;
  border-radius: 12px;
  padding: 12px 24px;
  font-size: 14px;
  font-weight: 700;
  color: #ffffff;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.25);
  transition: all 0.2s;
}

.btn-arbitrate:hover:not(:disabled) {
  background: #5e3e47;
  transform: translateY(-1px);
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
