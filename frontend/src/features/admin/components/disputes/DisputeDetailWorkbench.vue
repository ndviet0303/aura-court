<script setup lang="ts">
import type { DisputeCase } from '../../types/dispute'

defineProps<{
  item: DisputeCase
  activeEvidenceTab: 'logs' | 'chat' | 'map'
}>()

const emit = defineEmits<{
  (e: 'update:activeEvidenceTab', tab: 'logs' | 'chat' | 'map'): void
}>()
</script>

<template>
  <div class="dispute-workbench">
    <!-- Workbench Header -->
    <div class="workbench-header">
      <div class="header-main-info">
        <div class="tags-row">
          <span class="status-badge" :class="{ 'status-critical': item.severity === 'critical', 'status-resolved': item.status === 'resolved' }">
            {{ item.status === 'resolved' ? 'ĐÃ PHÁN QUYẾT' : 'ĐANG ĐIỀU TRA KHẨN CẤP' }}
          </span>
          <span class="reference-tag">
            Hồ sơ #{{ item.disputeCode }} • Tham chiếu #BK-{{ item.bookingId || '99482' }}
          </span>
        </div>
        <h2 class="dispute-title">{{ item.title }}</h2>
      </div>

      <div class="header-tools">
        <button type="button" class="icon-tool-btn" title="In hồ sơ vụ việc">
          <span class="material-symbols-outlined">print</span>
        </button>
        <button type="button" class="icon-tool-btn" title="Tùy chọn khác">
          <span class="material-symbols-outlined">more_vert</span>
        </button>
      </div>
    </div>

    <!-- Tri-Party Info Cards -->
    <div class="tri-party-grid">
      <!-- Complainant -->
      <div class="party-card">
        <div class="avatar-circle avatar-complainant">
          <span class="material-symbols-outlined">person</span>
        </div>
        <div class="party-details">
          <span class="party-role">NGUYÊN ĐƠN</span>
          <p class="party-name">{{ item.complainant.name }}</p>
          <span class="party-badge text-gold">
            {{ item.complainant.tier || 'Hạng Vàng' }} • Uy tín: {{ item.complainant.reputationScore || 98 }}/100
          </span>
        </div>
      </div>

      <!-- Respondent -->
      <div class="party-card">
        <div class="avatar-circle avatar-respondent">
          <span class="material-symbols-outlined">sports_kabaddi</span>
        </div>
        <div class="party-details">
          <span class="party-role">BỊ ĐƠN</span>
          <p class="party-name">{{ item.respondent.name }}</p>
          <span class="party-badge text-diamond">
            {{ item.respondent.tier || 'Hạng Kim Cương' }} • Uy tín: {{ item.respondent.reputationScore || 96 }}/100
          </span>
        </div>
      </div>

      <!-- Facility -->
      <div class="party-card">
        <div class="avatar-circle avatar-facility">
          <span class="material-symbols-outlined">stadium</span>
        </div>
        <div class="party-details">
          <span class="party-role">CƠ SỞ THỂ THAO</span>
          <p class="party-name">{{ item.facility.name }}</p>
          <span class="party-badge text-facility">
            {{ item.facility.operatorName || 'Xác thực API đang hoạt động' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Evidence Tabs & Content -->
    <div class="evidence-section">
      <div class="evidence-nav-tabs">
        <button
          type="button"
          class="evidence-tab-btn"
          :class="{ active: activeEvidenceTab === 'logs' }"
          @click="emit('update:activeEvidenceTab', 'logs')"
        >
          <span class="material-symbols-outlined tab-ico">timeline</span>
          Nhật ký Hệ thống
        </button>
        <button
          type="button"
          class="evidence-tab-btn"
          :class="{ active: activeEvidenceTab === 'chat' }"
          @click="emit('update:activeEvidenceTab', 'chat')"
        >
          <span class="material-symbols-outlined tab-ico">chat</span>
          Đoạn Chat Đối Chất
        </button>
        <button
          type="button"
          class="evidence-tab-btn"
          :class="{ active: activeEvidenceTab === 'map' }"
          @click="emit('update:activeEvidenceTab', 'map')"
        >
          <span class="material-symbols-outlined tab-ico">grid_view</span>
          Sơ Đồ &amp; Camera Sân
        </button>
      </div>

      <!-- Tab Content: Timeline Logs -->
      <div v-if="activeEvidenceTab === 'logs'" class="tab-pane">
        <h4 class="pane-subtitle">Chuỗi Sự Kiện Bằng Chứng Kỹ Thuật</h4>
        <div class="timeline-container">
          <div
            v-for="(event, idx) in item.timelineEvents"
            :key="idx"
            class="timeline-item"
          >
            <div class="timeline-dot" :class="'dot-' + event.type"></div>
            <div class="timeline-content">
              <div class="event-title-row">
                <span class="event-title">{{ event.title }}</span>
                <span class="event-time">{{ event.time }}</span>
              </div>
              <p class="event-desc">{{ event.description }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Content: Live Chat -->
      <div v-else-if="activeEvidenceTab === 'chat'" class="tab-pane">
        <h4 class="pane-subtitle">Lịch Sử Trao Đổi 3 Bên</h4>
        <div class="chat-container" style="scrollbar-width: none;">
          <div
            v-for="(msg, idx) in item.chatMessages"
            :key="idx"
            class="chat-bubble-row"
            :class="{
              'msg-self': msg.role === 'facility',
              'msg-system': msg.role === 'system',
            }"
          >
            <div v-if="msg.role !== 'system'" class="msg-avatar">
              {{ msg.avatar || 'U' }}
            </div>
            <div class="msg-bubble">
              <div class="msg-header">
                <span class="msg-sender">{{ msg.sender }}</span>
                <span class="msg-time">{{ msg.time }}</span>
              </div>
              <p class="msg-text">{{ msg.message }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Content: Court Map Context -->
      <div v-else-if="activeEvidenceTab === 'map'" class="tab-pane">
        <h4 class="pane-subtitle">Mặt Bằng Cụm Sân - Trạng Thái Thời Gian Thực</h4>
        <div class="court-map-grid">
          <div
            v-for="(court, idx) in item.courtMapContext"
            :key="idx"
            class="court-node"
            :class="'node-' + court.status"
          >
            <span class="court-node-name">{{ court.name }}</span>
            <span v-if="court.status === 'conflict'" class="material-symbols-outlined node-icon">warning</span>
            <span class="court-node-label">{{ court.label }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- AI Decision Assistant Card -->
    <div v-if="item.aiAnalysis" class="ai-assistant-card">
      <div class="ai-card-header">
        <span class="material-symbols-outlined ai-icon">smart_toy</span>
        <h4 class="ai-title">Trợ Lý AI Phân Tích &amp; Gợi Ý Phán Quyết</h4>
      </div>
      <div class="ai-card-body">
        <div class="ai-fault-row">
          <span class="fault-badge">
            Lỗi Cơ Sở: <strong>{{ item.aiAnalysis.faultRateFacility }}%</strong>
          </span>
          <span class="fault-badge">
            Lỗi Khách Hàng: <strong>{{ item.aiAnalysis.faultRateUser }}%</strong>
          </span>
        </div>
        <p class="ai-root-cause">
          <strong>Nguyên nhân gốc rễ:</strong> {{ item.aiAnalysis.rootCause }}
        </p>
        <div class="ai-recommendation">
          <strong>Đề xuất giải quyết:</strong> {{ item.aiAnalysis.recommendationSummary }}
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dispute-workbench {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 16px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.workbench-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid #edeeef;
  padding-bottom: 16px;
}

.tags-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.status-badge {
  font-size: 11px;
  font-weight: 800;
  padding: 3px 10px;
  border-radius: 6px;
  letter-spacing: 0.04em;
  background: #ffdad6;
  color: #ba1a1a;
}

.status-resolved {
  background: #dcfce7;
  color: #15803d;
}

.reference-tag {
  font-size: 12px;
  color: #5d5f5f;
  font-weight: 600;
}

.dispute-title {
  font-size: 20px;
  font-weight: 800;
  color: #191c1d;
  margin: 0;
}

.header-tools {
  display: flex;
  gap: 8px;
}

.icon-tool-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid #e1e3e4;
  background: #f8f9fa;
  color: #5d5f5f;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.icon-tool-btn:hover {
  background: #edeeef;
  color: #191c1d;
}

.tri-party-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.party-card {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar-circle {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.avatar-complainant {
  background: var(--primary-container, #F8E8EC);
  color: var(--on-primary-container, #4e1827);
}

.avatar-respondent {
  background: #e0f2fe;
  color: #0369a1;
}

.avatar-facility {
  background: #edeeef;
  color: #5d5f5f;
}

.party-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow: hidden;
}

.party-role {
  font-size: 10px;
  font-weight: 700;
  color: #817476;
  letter-spacing: 0.05em;
}

.party-name {
  font-size: 14px;
  font-weight: 700;
  color: #191c1d;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.party-badge {
  font-size: 11px;
  font-weight: 600;
}

.text-gold {
  color: #b45309;
}

.text-diamond {
  color: #0288d1;
}

.text-facility {
  color: #5d5f5f;
}

.evidence-section {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 14px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.evidence-nav-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid #e1e3e4;
  padding-bottom: 8px;
}

.evidence-tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: none;
  font-size: 13px;
  font-weight: 600;
  color: #5d5f5f;
  padding: 6px 14px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.evidence-tab-btn:hover {
  background: #edeeef;
  color: #191c1d;
}

.evidence-tab-btn.active {
  background: var(--primary-container, #F8E8EC);
  color: var(--primary, #6B2D3F);
}

.tab-ico {
  font-size: 18px;
}

.pane-subtitle {
  font-size: 13px;
  font-weight: 700;
  color: #5d5f5f;
  margin: 0 0 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.timeline-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.timeline-item {
  display: flex;
  gap: 12px;
  position: relative;
}

.timeline-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-top: 5px;
  flex-shrink: 0;
}

.dot-primary { background: var(--primary, #6B2D3F); }
.dot-warning { background: #f59e0b; }
.dot-error { background: #ba1a1a; }
.dot-success { background: #16a34a; }

.timeline-content {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 10px;
  padding: 10px 14px;
  flex: 1;
}

.event-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.event-title {
  font-size: 13px;
  font-weight: 700;
  color: #191c1d;
}

.event-time {
  font-size: 11px;
  color: #817476;
  font-family: monospace;
}

.event-desc {
  font-size: 12px;
  color: #5d5f5f;
  margin: 0;
}

.chat-container {
  max-height: 280px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.chat-bubble-row {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.chat-bubble-row.msg-self {
  flex-direction: row-reverse;
}

.chat-bubble-row.msg-system {
  justify-content: center;
}

.msg-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #edeeef;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  color: #191c1d;
  flex-shrink: 0;
}

.msg-bubble {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 12px;
  padding: 8px 12px;
  max-width: 75%;
}

.msg-self .msg-bubble {
  background: var(--primary-container, #F8E8EC);
  border-color: var(--border, #e8e1d7);
  color: var(--on-primary-container, #4e1827);
}

.msg-system .msg-bubble {
  background: #f1f5f9;
  border-color: #cbd5e1;
  text-align: center;
  max-width: 90%;
}

.msg-header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 2px;
}

.msg-sender {
  font-size: 11px;
  font-weight: 700;
  color: #78555e;
}

.msg-time {
  font-size: 10px;
  color: #817476;
}

.msg-text {
  font-size: 13px;
  color: #191c1d;
  margin: 0;
}

.court-map-grid {
  display: flex;
  gap: 16px;
  justify-content: center;
  padding: 16px;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e1e3e4;
}

.court-node {
  width: 130px;
  height: 170px;
  border-radius: 12px;
  border: 2px solid #e1e3e4;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px;
}

.node-occupied {
  background: #f8f9fa;
  opacity: 0.6;
}

.node-conflict {
  background: #ffdad6;
  border-color: #ba1a1a;
  box-shadow: 0 0 15px rgba(186, 26, 26, 0.2);
  animation: pulseConflict 1.5s infinite ease-in-out;
}

@keyframes pulseConflict {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.02); }
}

.node-available {
  background: #dcfce7;
  border-color: #16a34a;
}

.court-node-name {
  font-size: 16px;
  font-weight: 800;
}

.node-conflict .court-node-name { color: #ba1a1a; }
.node-available .court-node-name { color: #16a34a; }

.node-icon {
  font-size: 28px;
  color: #ba1a1a;
}

.court-node-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  text-align: center;
}

.ai-assistant-card {
  background: #fdf4ff;
  border: 1px solid #f0abfc;
  border-radius: 14px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ai-card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #86198f;
}

.ai-icon {
  font-size: 22px;
}

.ai-title {
  font-size: 13px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0;
}

.ai-card-body {
  background: #ffffff;
  border: 1px solid #fae8ff;
  border-radius: 10px;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  color: #191c1d;
}

.ai-fault-row {
  display: flex;
  gap: 10px;
}

.fault-badge {
  font-size: 12px;
  background: #fdf4ff;
  color: #701a75;
  padding: 2px 8px;
  border-radius: 6px;
}

.ai-root-cause {
  margin: 0;
  color: #5d5f5f;
}

.ai-recommendation {
  background: #f5f3ff;
  border-left: 3px solid #8b5cf6;
  padding: 6px 10px;
  border-radius: 4px;
  font-size: 13px;
  color: #4c1d95;
}
</style>
