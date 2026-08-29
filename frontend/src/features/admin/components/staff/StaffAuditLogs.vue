<script setup lang="ts">
import type { AuditLogItem } from '../../composables/useAdminStaff'

defineProps<{
  logs: AuditLogItem[]
}>()
</script>

<template>
  <div class="audit-logs-container">
    <div class="audit-header">
      <h3 class="audit-heading">Nhật ký Hoạt động (Audit & Security Logs)</h3>
      <p class="audit-sub">Ghi lại toàn bộ hành động thay đổi cấu hình hệ thống, duyệt tiền và đăng nhập.</p>
    </div>

    <div class="audit-items-list">
      <div
        v-for="item in logs"
        :key="item.id"
        class="audit-item-row"
        :class="`status-${item.status}`"
      >
        <div class="log-actor-col">
          <strong>{{ item.actorName }}</strong>
          <span class="actor-role-tag">{{ item.actorRole }}</span>
        </div>

        <div class="log-action-col">
          <span class="action-title">{{ item.action }}</span>
          <span class="target-desc">Mục tiêu: {{ item.target }}</span>
        </div>

        <div class="log-meta-col">
          <span class="timestamp">{{ item.timestamp }}</span>
          <span class="ip-addr font-mono">IP: {{ item.ip }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.audit-logs-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 20px;
}

.audit-heading {
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 700;
}

.audit-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.audit-items-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.audit-item-row {
  display: grid;
  grid-template-columns: 200px 1fr 180px;
  padding: 12px 16px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  align-items: center;
  gap: 12px;
}

.log-actor-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.actor-role-tag {
  font-size: 10px;
  color: var(--muted);
}

.log-action-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.action-title {
  font-weight: 600;
}

.target-desc {
  font-size: 11px;
  color: var(--muted);
}

.log-meta-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  font-size: 11px;
  color: var(--muted);
}

.status-warning {
  border-left: 4px solid #f59e0b;
}

.status-danger {
  border-left: 4px solid #ef4444;
}

.status-success {
  border-left: 4px solid #10b981;
}
</style>
