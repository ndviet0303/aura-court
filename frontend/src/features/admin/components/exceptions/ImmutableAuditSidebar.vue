<script setup lang="ts">
import type { AuditLog } from '../../types/exceptions'

defineProps<{
  logs: AuditLog[]
}>()
</script>

<template>
  <aside class="audit-trail-sidebar">
    <div class="sidebar-header-box">
      <div class="audit-title-row">
        <h2 class="sidebar-title">Nhật Ký Kiểm Toán Bất Biến</h2>
        <span class="sha-badge">SHA-256</span>
      </div>
      <p class="audit-sub">Ghi nhận toàn bộ thao tác can thiệp và cảnh báo hệ thống.</p>
    </div>

    <div class="timeline-container">
      <div v-for="log in logs" :key="log.id" class="timeline-item">
        <div class="timeline-dot" :class="`dot-${log.type}`"></div>
        <div class="timeline-content">
          <div class="timeline-top">
            <span class="timeline-author">{{ log.author }}</span>
            <span class="timeline-time">{{ log.time }}</span>
          </div>
          <div class="timeline-action">{{ log.action }}</div>
          <div class="timeline-details">{{ log.details }}</div>
          <div class="timeline-hash">Hash: #{{ log.hash }}</div>
        </div>
      </div>
    </div>

    <div class="sidebar-footer-box">
      <button class="btn-download-logs" type="button">
        <span class="material-symbols-outlined">verified</span>
        Tải Log Kiểm Toán Có Chữ Ký CA
      </button>
    </div>
  </aside>
</template>

<style scoped>
.audit-trail-sidebar {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.sidebar-header-box {
  padding: 16px;
  border-bottom: 1px solid #e1e3e4;
}

.audit-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.sidebar-title {
  font-size: 15px;
  font-weight: 700;
  margin: 0;
  color: #191c1d;
}

.sha-badge {
  font-size: 10px;
  font-family: monospace;
  background: #f3f4f5;
  border: 1px solid #e1e3e4;
  padding: 2px 6px;
  border-radius: 4px;
  color: #5d5f5f;
}

.audit-sub {
  font-size: 12px;
  color: #817476;
  margin: 4px 0 0;
}

.timeline-container {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 520px;
  overflow-y: auto;
  position: relative;
}

.timeline-item {
  position: relative;
  padding-left: 20px;
}

.timeline-dot {
  position: absolute;
  left: 0;
  top: 4px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.dot-override { background: #78555e; }
.dot-flag { background: #f59e0b; }
.dot-block { background: #ba1a1a; }
.dot-unlock { background: #10b981; }

.timeline-content {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 8px;
  padding: 10px;
}

.timeline-top {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 4px;
}

.timeline-author { color: #191c1d; }
.timeline-time { color: #817476; font-size: 11px; }

.timeline-action {
  font-size: 12px;
  font-weight: 600;
  color: #78555e;
  margin-bottom: 2px;
}

.timeline-details {
  font-size: 12px;
  color: #4f4446;
  line-height: 1.4;
}

.timeline-hash {
  font-size: 10px;
  font-family: monospace;
  color: #817476;
  margin-top: 6px;
}

.sidebar-footer-box {
  padding: 12px 16px;
  border-top: 1px solid #e1e3e4;
  background: #f8f9fa;
}

.btn-download-logs {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px;
  border-radius: 8px;
  border: 1px solid #e1e3e4;
  background: #ffffff;
  font-size: 12px;
  font-weight: 600;
  color: #191c1d;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-download-logs:hover {
  background: #edeeef;
}
</style>
