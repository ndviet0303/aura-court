<script setup lang="ts">
import type { MembershipPlan } from '../../types/membership'

defineProps<{
  show: boolean
  plan: MembershipPlan | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'archive-plan', planId: string | number): void
}>()
</script>

<template>
  <div v-if="show && plan" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-box" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div class="header-title-block">
          <span class="material-symbols-outlined header-icon">published_with_changes</span>
          <div>
            <h3 class="modal-title">Quản Lý Phiên Bản: {{ plan.name }}</h3>
            <span class="version-tag">{{ plan.version }} • Active</span>
          </div>
        </div>
        <button type="button" class="btn-close" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="stats-overview-box">
          <div class="stat-col">
            <span class="stat-lbl">Hội viên đang dùng bản này</span>
            <strong class="stat-val">{{ plan.activeSubscribers }} Thành viên</strong>
          </div>
          <div class="stat-col">
            <span class="stat-lbl">Tỷ lệ duy trì</span>
            <strong class="stat-val text-green">94.8%</strong>
          </div>
        </div>

        <div class="info-notice">
          <span class="material-symbols-outlined icon-20 text-primary">info</span>
          <p class="notice-text">
            Mỗi khi bạn sửa chính sách và chọn <strong>"Lưu thành phiên bản mới"</strong>, hệ thống tự động giữ nguyên quyền lợi cho {{ plan.activeSubscribers }} hội viên này cho đến hết kỳ hạn đã thanh toán.
          </p>
        </div>

        <div class="perks-snapshot">
          <h4 class="snapshot-title">Quyền lợi phiên bản hiện hành ({{ plan.version }}):</h4>
          <ul class="snapshot-list">
            <li v-for="(p, i) in plan.perks" :key="i" class="snapshot-item">
              <span class="material-symbols-outlined icon-16 text-primary">check_circle</span>
              <span>{{ p }}</span>
            </li>
          </ul>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-secondary" @click="emit('close')">Đóng</button>
          <button
            type="button"
            class="btn-archive"
            @click="emit('archive-plan', plan.id); emit('close')"
          >
            Lưu trữ gói (Archive)
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-box {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 20px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  border: 1px solid var(--outline-variant, #e1e3e4);
}

:global(html.dark) .modal-box {
  background: #1e2025;
  border-color: #353942;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--surface-container, #edeeef);
}

:global(html.dark) .modal-header {
  border-color: #353942;
}

.header-title-block {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  font-size: 26px;
  color: var(--primary, #78555e);
}

:global(html.dark) .header-icon {
  color: #f8b4c4;
}

.modal-title {
  margin: 0 0 2px 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .modal-title {
  color: #ffffff;
}

.version-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
}

.btn-close {
  border: none;
  background: transparent;
  color: var(--on-surface-variant, #4f4446);
  cursor: pointer;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.stats-overview-box {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 12px;
  background: var(--surface-container-low, #f3f4f5);
}

:global(html.dark) .stats-overview-box {
  background: #262930;
}

.stat-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat-lbl {
  font-size: 11px;
  color: var(--on-surface-variant, #4f4446);
}

.stat-val {
  font-size: 16px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .stat-val {
  color: #ffffff;
}

.text-green {
  color: #10b981;
}

.info-notice {
  display: flex;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(120, 85, 94, 0.06);
  border: 1px solid var(--primary-fixed, #ffd9e2);
}

:global(html.dark) .info-notice {
  background: rgba(248, 180, 196, 0.06);
  border-color: #53333e;
}

.notice-text {
  margin: 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .notice-text {
  color: #e2e8f0;
}

.perks-snapshot {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.snapshot-title {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .snapshot-title {
  color: #ffffff;
}

.snapshot-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.snapshot-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--on-surface-variant, #4f4446);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 6px;
  padding-top: 16px;
  border-top: 1px solid var(--surface-container, #edeeef);
}

:global(html.dark) .modal-footer {
  border-color: #353942;
}

.btn-secondary {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-archive {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid #ef4444;
  background: transparent;
  color: #ef4444;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.btn-archive:hover {
  background: #ef4444;
  color: #ffffff;
}
</style>
