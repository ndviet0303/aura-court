<script setup lang="ts">
import { ref } from 'vue'
import type { PlanVersionAudit } from '../../types/membership'

const props = defineProps<{
  audits: PlanVersionAudit[]
}>()

const isExpanded = ref(false)

function getVisibleAudits() {
  if (isExpanded.value) return props.audits
  return props.audits.slice(0, 4)
}
</script>

<template>
  <div class="version-timeline-card">
    <div class="card-header-row">
      <h3 class="card-title">Lịch sử phiên bản</h3>
      <span class="material-symbols-outlined header-icon">history</span>
    </div>

    <p class="card-description">
      Thay đổi quyền lợi sẽ được lưu thành phiên bản mới để không ảnh hưởng user cũ đang duy trì gói.
    </p>

    <!-- Timeline stack -->
    <div class="timeline-container">
      <div
        v-for="(item, idx) in getVisibleAudits()"
        :key="item.id || idx"
        class="timeline-item"
        :class="{ 'is-archived': item.status === 'archived' }"
      >
        <!-- Timeline node circle -->
        <div class="timeline-node" :class="{ 'node-active': item.status === 'active', 'node-archived': item.status === 'archived' }">
          <div v-if="item.status === 'active'" class="node-inner-dot"></div>
        </div>

        <!-- Content -->
        <div class="timeline-content">
          <div class="item-header-row">
            <h4 class="item-title" :class="{ 'strike-text': item.status === 'archived' }">
              {{ item.planName }} {{ item.version }}
            </h4>
            <span
              class="status-pill"
              :class="{ 'pill-active': item.status === 'active', 'pill-archived': item.status === 'archived' }"
            >
              {{ item.status === 'active' ? 'Active' : 'Archived' }}
            </span>
          </div>

          <p class="item-meta">
            {{ item.updatedAt }} • Cập nhật bởi {{ item.updatedBy }}
            <span v-if="item.activeSubscribersCount > 0" class="subscribers-count">
              ({{ item.activeSubscribersCount }} users active)
            </span>
          </p>

          <div v-if="item.changes && item.changes.length" class="changes-box">
            <div v-for="(change, cIdx) in item.changes" :key="cIdx" class="change-line">
              + {{ change }}
            </div>
          </div>

          <p v-if="item.note" class="archived-note">
            {{ item.note }}
          </p>
        </div>
      </div>
    </div>

    <button
      v-if="audits.length > 3"
      type="button"
      class="btn-toggle-expand"
      @click="isExpanded = !isExpanded"
    >
      {{ isExpanded ? 'Thu gọn lịch sử' : 'Xem tất cả lịch sử' }}
    </button>
  </div>
</template>

<style scoped>
.version-timeline-card {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 20px;
  padding: 24px;
  border: 1px solid var(--outline-variant, #e1e3e4);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  position: sticky;
  top: 24px;
}

:global(html.dark) .version-timeline-card {
  background: #1e2025;
  border-color: #353942;
}

.card-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.card-title {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .card-title {
  color: #ffffff;
}

.header-icon {
  color: var(--on-surface-variant, #4f4446);
  font-size: 22px;
}

.card-description {
  margin: 0 0 24px 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--on-surface-variant, #4f4446);
}

.timeline-container {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.timeline-container::before {
  content: '';
  position: absolute;
  top: 10px;
  bottom: 10px;
  left: 10px;
  width: 2px;
  background: var(--surface-container-high, #e7e8e9);
  z-index: 1;
}

:global(html.dark) .timeline-container::before {
  background: #353942;
}

.timeline-item {
  position: relative;
  display: flex;
  gap: 16px;
  z-index: 2;
}

.timeline-item.is-archived {
  opacity: 0.7;
}

.timeline-node {
  width: 22px;
  height: 22px;
  border-radius: 9999px;
  background: var(--surface-container-lowest, #ffffff);
  border: 3px solid var(--surface-container-high, #e7e8e9);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 2px;
}

:global(html.dark) .timeline-node {
  background: #1e2025;
  border-color: #353942;
}

.timeline-node.node-active {
  border-color: var(--primary-container, #ffd1dc);
  background: var(--primary-container, #ffd1dc);
}

.node-inner-dot {
  width: 8px;
  height: 8px;
  border-radius: 9999px;
  background: var(--primary, #78555e);
}

.timeline-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.item-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.item-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .item-title {
  color: #ffffff;
}

.strike-text {
  text-decoration: line-through;
  color: var(--on-surface-variant, #4f4446);
}

.status-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}

.pill-active {
  background: var(--primary-fixed, #ffd9e2);
  color: var(--primary, #78555e);
}

.pill-archived {
  background: var(--surface-container, #edeeef);
  color: var(--on-surface-variant, #4f4446);
}

:global(html.dark) .pill-archived {
  background: #2b2e36;
  color: #a0aec0;
}

.item-meta {
  margin: 0 0 6px 0;
  font-size: 11px;
  color: var(--on-surface-variant, #4f4446);
}

.subscribers-count {
  font-weight: 600;
  color: var(--primary, #78555e);
}

:global(html.dark) .subscribers-count {
  color: #f8b4c4;
}

.changes-box {
  background: var(--surface-container-low, #f3f4f5);
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 12px;
  line-height: 1.5;
  color: var(--on-surface, #191c1d);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

:global(html.dark) .changes-box {
  background: #262930;
  color: #e2e8f0;
}

.archived-note {
  margin: 4px 0 0 0;
  font-size: 11px;
  font-style: italic;
  color: var(--on-surface-variant, #4f4446);
}

.btn-toggle-expand {
  width: 100%;
  margin-top: 20px;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  background: transparent;
  color: var(--on-surface-variant, #4f4446);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-toggle-expand:hover {
  background: var(--surface-container-low, #f3f4f5);
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .btn-toggle-expand:hover {
  background: #2b2e36;
  color: #ffffff;
}
</style>
