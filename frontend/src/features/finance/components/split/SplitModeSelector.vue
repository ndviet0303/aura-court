<script setup lang="ts">
import type { SplitMode } from '../../composables/useGroupSplitBill'

defineProps<{
  mode: SplitMode
}>()

const emit = defineEmits<{
  (e: 'update:mode', val: SplitMode): void
}>()
</script>

<template>
  <div class="split-mode-selector">
    <button
      class="mode-btn"
      :class="{ active: mode === 'equal' }"
      @click="emit('update:mode', 'equal')"
    >
      <span class="mode-ico">⚖️</span>
      <div class="mode-info">
        <strong>Chia Đều (Equal)</strong>
        <span>Chia bằng nhau cho mọi thành viên</span>
      </div>
    </button>

    <button
      class="mode-btn"
      :class="{ active: mode === 'itemized' }"
      @click="emit('update:mode', 'itemized')"
    >
      <span class="mode-ico">🥤</span>
      <div class="mode-info">
        <strong>Theo Món (Itemized)</strong>
        <span>Ai dùng nước / cầu lông trả thêm</span>
      </div>
    </button>

    <button
      class="mode-btn"
      :class="{ active: mode === 'time' }"
      @click="emit('update:mode', 'time')"
    >
      <span class="mode-ico">⏱️</span>
      <div class="mode-info">
        <strong>Theo Thời Gian</strong>
        <span>Đánh bao nhiêu phút tính bấy nhiêu</span>
      </div>
    </button>

    <button
      class="mode-btn"
      :class="{ active: mode === 'custom' }"
      @click="emit('update:mode', 'custom')"
    >
      <span class="mode-ico">✏️</span>
      <div class="mode-info">
        <strong>Tự Nhập Tùy Chỉnh</strong>
        <span>Nhập tay số tiền hoặc % từng người</span>
      </div>
    </button>
  </div>
</template>

<style scoped>
.split-mode-selector {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 10px;
}

.mode-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  text-align: left;
  transition: all 0.15s;
}

.mode-btn.active {
  border-color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.05);
}

.mode-ico {
  font-size: 22px;
}

.mode-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.mode-info strong {
  font-size: 13px;
}

.mode-info span {
  font-size: 11px;
  color: var(--muted);
}
</style>
