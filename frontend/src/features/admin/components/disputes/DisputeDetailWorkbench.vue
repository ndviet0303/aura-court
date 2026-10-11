<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DisputeCase } from '../../types/dispute'

defineProps<{
  item: DisputeCase
  activeEvidenceTab: 'logs' | 'chat' | 'map'
}>()

const emit = defineEmits<{
  (e: 'update:activeEvidenceTab', tab: 'logs' | 'chat' | 'map'): void
  (e: 'print'): void
}>()

const { t } = useI18n()

// Drag-to-scroll for evidence tabs
const evidenceTabsRef = ref<HTMLElement | null>(null)
const isMouseDown = ref(false)
const hasDragged = ref(false)
const startX = ref(0)
const scrollStart = ref(0)

function onMouseDown(e: MouseEvent) {
  if (!evidenceTabsRef.value) return
  isMouseDown.value = true
  hasDragged.value = false
  startX.value = e.clientX
  scrollStart.value = evidenceTabsRef.value.scrollLeft

  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', onMouseUp)
}

function onMouseMove(e: MouseEvent) {
  if (!isMouseDown.value || !evidenceTabsRef.value) return
  const currentX = e.clientX
  const walk = (currentX - startX.value) * 1.5
  if (Math.abs(walk) > 3) {
    hasDragged.value = true
  }
  evidenceTabsRef.value.scrollLeft = scrollStart.value - walk
}

function onMouseUp() {
  isMouseDown.value = false
  window.removeEventListener('mousemove', onMouseMove)
  window.removeEventListener('mouseup', onMouseUp)
}

function handleTabClick(tab: 'logs' | 'chat' | 'map', e: MouseEvent) {
  if (hasDragged.value) {
    e.preventDefault()
    e.stopPropagation()
    return
  }
  emit('update:activeEvidenceTab', tab)
}

function onWheel(e: WheelEvent) {
  if (!evidenceTabsRef.value) return
  if (e.deltaY !== 0 && e.deltaX === 0) {
    evidenceTabsRef.value.scrollLeft += e.deltaY
    e.preventDefault()
  }
}
</script>

<template>
  <div class="dispute-workbench">
    <!-- Workbench Header -->
    <div class="workbench-header">
      <div class="header-main-info">
        <div class="tags-row">
          <span
            class="status-badge"
            :class="{
              'status-critical': item.severity === 'critical',
              'status-resolved': item.status === 'resolved',
            }"
          >
            {{
              item.status === 'resolved'
                ? t('admin.disputes.badgeArbitrated')
                : t('admin.disputes.badgeInvestigating')
            }}
          </span>
          <span class="reference-tag">
            {{
              t('admin.disputes.dossierRef', {
                code: item.disputeCode,
                ref: item.bookingId ? `BK-${item.bookingId}` : 'BK-99482',
              })
            }}
          </span>
        </div>
        <h2 class="dispute-title">{{ item.title }}</h2>
      </div>

      <div class="header-tools">
        <button
          type="button"
          class="icon-tool-btn"
          :title="t('admin.disputes.printDossier')"
          @click="emit('print')"
        >
          <span class="material-symbols-outlined">print</span>
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
          <span class="party-role">{{ t('admin.disputes.roleComplainant') }}</span>
          <p class="party-name">{{ item.complainant.name }}</p>
          <span class="party-badge text-gold">
            {{ item.complainant.tier || 'Hạng Vàng' }} •
            {{
              t('admin.disputes.reputationScore', { score: item.complainant.reputationScore || 95 })
            }}
          </span>
        </div>
      </div>

      <!-- Respondent -->
      <div class="party-card">
        <div class="avatar-circle avatar-respondent">
          <span class="material-symbols-outlined">sports_kabaddi</span>
        </div>
        <div class="party-details">
          <span class="party-role">{{ t('admin.disputes.roleRespondent') }}</span>
          <p class="party-name">{{ item.respondent.name }}</p>
          <span class="party-badge text-diamond">
            {{ item.respondent.tier || 'Hạng Kim Cương' }} •
            {{
              t('admin.disputes.reputationScore', { score: item.respondent.reputationScore || 90 })
            }}
          </span>
        </div>
      </div>

      <!-- Facility -->
      <div class="party-card">
        <div class="avatar-circle avatar-facility">
          <span class="material-symbols-outlined">stadium</span>
        </div>
        <div class="party-details">
          <span class="party-role">{{ t('admin.disputes.roleFacility') }}</span>
          <p class="party-name">{{ item.facility.name }}</p>
          <span class="party-badge text-facility">
            {{ item.facility.operatorName || t('admin.disputes.facilityApiActive') }}
          </span>
        </div>
      </div>
    </div>

    <!-- Evidence Tabs & Content -->
    <div class="evidence-section">
      <div
        ref="evidenceTabsRef"
        class="evidence-nav-tabs"
        :class="{ 'is-dragging': isMouseDown }"
        style="scrollbar-width: none"
        @mousedown="onMouseDown"
        @mousemove="onMouseMove"
        @mouseup="onMouseUp"
        @mouseleave="onMouseUp"
        @wheel="onWheel"
      >
        <button
          type="button"
          class="evidence-tab-btn"
          :class="{ active: activeEvidenceTab === 'logs' }"
          @click="handleTabClick('logs', $event)"
        >
          <span class="material-symbols-outlined tab-ico">timeline</span>
          <span>{{ t('admin.disputes.tabLogs') }}</span>
        </button>
        <button
          type="button"
          class="evidence-tab-btn"
          :class="{ active: activeEvidenceTab === 'chat' }"
          @click="handleTabClick('chat', $event)"
        >
          <span class="material-symbols-outlined tab-ico">chat</span>
          <span>{{ t('admin.disputes.tabChat') }}</span>
        </button>
        <button
          type="button"
          class="evidence-tab-btn"
          :class="{ active: activeEvidenceTab === 'map' }"
          @click="handleTabClick('map', $event)"
        >
          <span class="material-symbols-outlined tab-ico">grid_view</span>
          <span>{{ t('admin.disputes.tabMap') }}</span>
        </button>
      </div>

      <!-- Tab Content: Timeline Logs -->
      <div v-if="activeEvidenceTab === 'logs'" class="tab-pane">
        <h4 class="pane-subtitle">{{ t('admin.disputes.logsTitle') }}</h4>
        <div v-if="item.timelineEvents.length === 0" class="empty-pane">
          <span class="material-symbols-outlined">info</span>
          <span>Chưa có sự kiện kỹ thuật nào được ghi nhận.</span>
        </div>
        <div v-else class="timeline-container">
          <div v-for="(event, idx) in item.timelineEvents" :key="idx" class="timeline-item">
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
        <h4 class="pane-subtitle">{{ t('admin.disputes.chatTitle') }}</h4>
        <div v-if="item.chatMessages.length === 0" class="empty-pane">
          <span class="material-symbols-outlined">forum</span>
          <span>Chưa có tin nhắn đối chất giữa các bên.</span>
        </div>
        <div v-else class="chat-container" style="scrollbar-width: none">
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
              {{ msg.avatar || msg.sender.slice(0, 2).toUpperCase() }}
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
        <h4 class="pane-subtitle">{{ t('admin.disputes.mapTitle') }}</h4>
        <div v-if="item.courtMapContext.length === 0" class="empty-pane">
          <span class="material-symbols-outlined">grid_off</span>
          <span>Không có dữ liệu mặt bằng sân cho vụ việc này.</span>
        </div>
        <div v-else class="court-map-grid">
          <div
            v-for="(court, idx) in item.courtMapContext"
            :key="idx"
            class="court-node"
            :class="'node-' + court.status"
          >
            <span class="court-node-name">{{ court.name }}</span>
            <span v-if="court.status === 'conflict'" class="material-symbols-outlined node-icon"
              >warning</span
            >
            <span class="court-node-label">{{ court.label }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Analysis & Arbitration Proposal Card -->
    <div v-if="item.aiAnalysis" class="analysis-card">
      <div class="analysis-card-header">
        <span class="material-symbols-outlined analysis-icon">insights</span>
        <h4 class="analysis-title">{{ t('admin.disputes.analysisTitle') }}</h4>
      </div>
      <div class="analysis-card-body">
        <div class="fault-row">
          <span class="fault-badge badge-facility">
            {{ t('admin.disputes.faultFacility', { rate: item.aiAnalysis.faultRateFacility }) }}
          </span>
          <span class="fault-badge badge-user">
            {{ t('admin.disputes.faultUser', { rate: item.aiAnalysis.faultRateUser }) }}
          </span>
        </div>
        <p class="analysis-root-cause">
          <strong>{{ t('admin.disputes.rootCauseLabel') }}</strong> {{ item.aiAnalysis.rootCause }}
        </p>
        <div class="analysis-recommendation">
          <strong>{{ t('admin.disputes.recommendationLabel') }}</strong>
          {{ item.aiAnalysis.recommendationSummary }}
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dispute-workbench {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 16px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.04));
  color: var(--ink);
}

.workbench-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid var(--outline);
  padding-bottom: 16px;
  gap: 16px;
}

.tags-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
  flex-wrap: wrap;
}

.status-badge {
  font-size: 11px;
  font-weight: 800;
  padding: 3px 10px;
  border-radius: 6px;
  letter-spacing: 0.04em;
  background: var(--error-bg, rgba(198, 40, 40, 0.2));
  color: var(--error-text, #f87171);
}

.status-resolved {
  background: var(--success-bg, rgba(46, 125, 50, 0.25));
  color: var(--success-text, #4ade80);
}

.reference-tag {
  font-size: 12px;
  color: var(--muted);
  font-weight: 600;
}

.dispute-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink);
  margin: 0;
  letter-spacing: -0.01em;
}

.header-tools {
  display: flex;
  gap: 8px;
}

.icon-tool-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, var(--soft));
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.icon-tool-btn:hover {
  background: var(--soft);
  color: var(--ink);
}

.tri-party-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.party-card {
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
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
  background: var(--primary-container);
  color: var(--primary);
}

.avatar-respondent {
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8;
}

.avatar-facility {
  background: var(--soft);
  color: var(--muted);
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
  color: var(--muted);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.party-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
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
  color: var(--accent, #d8b46e);
}

.text-diamond {
  color: #38bdf8;
}

.text-facility {
  color: var(--muted);
}

.evidence-section {
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  border-radius: 14px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.evidence-nav-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--outline);
  padding-bottom: 8px;
  overflow-x: auto;
  cursor: grab;
  user-select: none;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.evidence-nav-tabs::-webkit-scrollbar {
  display: none;
}

.evidence-nav-tabs.is-dragging {
  cursor: grabbing;
  scroll-behavior: auto;
}

.evidence-tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: none;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
  padding: 6px 14px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  flex-shrink: 0;
  user-select: none;
}

.evidence-tab-btn:hover {
  background: var(--soft);
  color: var(--ink);
}

.evidence-tab-btn.active {
  background: var(--primary-container);
  color: var(--primary);
  font-weight: 700;
}

.tab-ico {
  font-size: 18px;
}

.pane-subtitle {
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  margin: 0 0 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.empty-pane {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--muted);
  padding: 24px;
  font-size: 13px;
  justify-content: center;
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

.dot-primary {
  background: var(--primary);
}
.dot-warning {
  background: var(--warning-text, #fb923c);
}
.dot-error {
  background: var(--error-text, #f87171);
}
.dot-success {
  background: var(--success-text, #4ade80);
}

.timeline-content {
  background: var(--surface);
  border: 1px solid var(--outline);
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
  color: var(--ink);
}

.event-time {
  font-size: 11px;
  color: var(--muted);
  font-family: monospace;
}

.event-desc {
  font-size: 12px;
  color: var(--muted);
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
  background: var(--soft);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  color: var(--ink);
  flex-shrink: 0;
}

.msg-bubble {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 12px;
  padding: 8px 12px;
  max-width: 75%;
}

.msg-self .msg-bubble {
  background: var(--primary-container);
  border-color: var(--primary);
  color: var(--ink);
}

.msg-system .msg-bubble {
  background: var(--surface-container);
  border-color: var(--outline);
  color: var(--muted);
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
  color: var(--primary);
}

.msg-time {
  font-size: 10px;
  color: var(--muted);
}

.msg-text {
  font-size: 13px;
  color: var(--ink);
  margin: 0;
}

.court-map-grid {
  display: flex;
  gap: 16px;
  justify-content: center;
  padding: 16px;
  background: var(--surface);
  border-radius: 12px;
  border: 1px solid var(--outline);
  flex-wrap: wrap;
}

.court-node {
  width: 130px;
  height: 160px;
  border-radius: 12px;
  border: 2px solid var(--outline);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px;
}

.node-occupied {
  background: var(--surface-container);
  opacity: 0.7;
}

.node-conflict {
  background: var(--error-bg, rgba(198, 40, 40, 0.2));
  border-color: var(--error-text, #f87171);
  box-shadow: 0 0 15px rgba(248, 113, 113, 0.25);
  animation: pulseConflict 1.5s infinite ease-in-out;
}

@keyframes pulseConflict {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.02);
  }
}

.node-available {
  background: var(--success-bg, rgba(46, 125, 50, 0.25));
  border-color: var(--success-text, #4ade80);
}

.court-node-name {
  font-size: 15px;
  font-weight: 800;
}

.node-conflict .court-node-name {
  color: var(--error-text, #f87171);
}
.node-available .court-node-name {
  color: var(--success-text, #4ade80);
}

.node-icon {
  font-size: 26px;
  color: var(--error-text, #f87171);
}

.court-node-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  text-align: center;
  color: var(--muted);
}

.analysis-card {
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  border-radius: 14px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.analysis-card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--primary);
}

.analysis-icon {
  font-size: 22px;
}

.analysis-title {
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0;
  color: var(--primary);
}

.analysis-card-body {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 10px;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: 13px;
  color: var(--ink);
}

.fault-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.fault-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 6px;
}

.badge-facility {
  background: var(--error-bg, rgba(198, 40, 40, 0.2));
  color: var(--error-text, #f87171);
}

.badge-user {
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8;
}

.analysis-root-cause {
  margin: 0;
  color: var(--muted);
}

.analysis-recommendation {
  background: var(--primary-container);
  border-left: 3px solid var(--primary);
  padding: 8px 12px;
  border-radius: 4px;
  font-size: 13px;
  color: var(--ink);
}
</style>
