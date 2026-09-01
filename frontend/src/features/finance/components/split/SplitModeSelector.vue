<script setup lang="ts">
import type { SplitMode } from '../../types/split'

defineProps<{
  mode: SplitMode
}>()

const emit = defineEmits<{
  (e: 'update:mode', val: SplitMode): void
}>()

const MODES = [
  {
    key: 'equal' as SplitMode,
    title: 'Chia Đều (Equal)',
    sub: 'Tự động chia đều 100% hóa đơn cho tất cả thành viên trong nhóm',
    icon: 'balance',
    badge: 'Phổ biến nhất',
  },
  {
    key: 'itemized' as SplitMode,
    title: 'Theo Món (Itemized)',
    sub: 'Tùy chọn ai dùng nước, cầu lông, thuê vợt sẽ chịu thêm phần đó',
    icon: 'receipt_long',
    badge: 'Chi tiết từng món',
  },
  {
    key: 'time' as SplitMode,
    title: 'Theo Thời Lượng (Time)',
    sub: 'Tính tiền sân theo số phút thực tế từng người tham gia (60p / 90p / 120p)',
    icon: 'schedule',
    badge: 'Công bằng giờ chơi',
  },
  {
    key: 'custom' as SplitMode,
    title: 'Tự Nhập Tùy Chỉnh',
    sub: 'Nhập tay số tiền cụ thể theo thỏa thuận riêng giữa các bạn bè',
    icon: 'tune',
    badge: 'Tùy biến 100%',
  },
]
</script>

<template>
  <div class="split-mode-selector">
    <button
      v-for="item in MODES"
      :key="item.key"
      type="button"
      class="mode-card"
      :class="{ 'mode-card--active': mode === item.key }"
      @click="emit('update:mode', item.key)"
    >
      <div class="mode-header">
        <div class="mode-icon-circle">
          <span class="material-symbols-outlined mode-icon">{{ item.icon }}</span>
        </div>
        <span v-if="item.badge" class="mode-badge">{{ item.badge }}</span>
      </div>

      <div class="mode-content">
        <strong class="mode-title">{{ item.title }}</strong>
        <p class="mode-desc">{{ item.sub }}</p>
      </div>

      <div class="mode-indicator">
        <span class="material-symbols-outlined check-icon">
          {{ mode === item.key ? 'radio_button_checked' : 'radio_button_unchecked' }}
        </span>
      </div>
    </button>
  </div>
</template>

<style scoped>
.split-mode-selector {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.mode-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--border, var(--outline, #382f35));
  background: var(--surface, #1e191c);
  color: var(--on-surface, #FAF7F3);
  cursor: pointer;
  text-align: left;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-sizing: border-box;
}

.mode-card:hover {
  border-color: var(--primary, #E58E9F);
  background: var(--surface-container, rgba(255, 255, 255, 0.04));
  transform: translateY(-2px);
}

.mode-card--active {
  border-color: var(--primary, #E58E9F);
  background: var(--surface-container-high, rgba(229, 142, 159, 0.08));
  box-shadow: 0 4px 18px rgba(229, 142, 159, 0.15);
}

.mode-header {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.mode-icon-circle {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--surface-container, rgba(255, 255, 255, 0.05));
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary, #E58E9F);
  border: 1px solid var(--border, transparent);
}

.mode-card--active .mode-icon-circle {
  background: var(--primary, #E58E9F);
  color: #1c0a10;
}

.mode-icon {
  font-size: 20px;
}

.mode-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 5px;
  background: rgba(229, 142, 159, 0.12);
  color: var(--primary, #E58E9F);
  border: 1px solid rgba(229, 142, 159, 0.2);
}

.mode-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.mode-title {
  font-size: 14px;
  font-weight: 750;
  color: var(--on-surface, #FAF7F3);
}

.mode-desc {
  margin: 0;
  font-size: 11.5px;
  color: var(--muted, #9e939a);
  line-height: 1.4;
}

.mode-indicator {
  align-self: flex-end;
  color: var(--muted, #9e939a);
}

.mode-card--active .mode-indicator {
  color: var(--primary, #E58E9F);
}

.check-icon {
  font-size: 18px;
}
</style>
