<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { HostKyc, KycAuditLog } from '../../types/kyc'
import { AuraButton } from '@/shared/components'

defineProps<{
  show: boolean
  item: HostKyc | null
  logs: KycAuditLog[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const { t } = useI18n()

function getActionBadgeClass(action: string): string {
  switch (action) {
    case 'approved':
      return 'badge-success'
    case 'rejected':
      return 'badge-danger'
    case 'needs_supplement':
      return 'badge-warning'
    default:
      return 'badge-info'
  }
}
</script>

<template>
  <teleport to="body">
    <transition name="modal-fade">
      <div v-if="show && item" class="history-modal-overlay" @click.self="emit('close')">
        <div class="history-modal-container" role="dialog" aria-modal="true">
          <!-- Header -->
          <div class="modal-header">
            <div class="header-info">
              <div class="header-icon-box">
                <span class="material-symbols-outlined icon-22">history</span>
              </div>
              <div>
                <h3 class="modal-title">{{ t('admin.kyc.historyModalTitle') }}</h3>
                <span class="modal-subtitle">{{ item.kyc_code }} • {{ item.business_name }}</span>
              </div>
            </div>
            <AuraButton variant="secondary" size="sm" @click="emit('close')">
              <span class="material-symbols-outlined icon-18">close</span>
              <span>{{ t('admin.kyc.closeBtn') }}</span>
            </AuraButton>
          </div>

          <!-- Body / Timeline Table -->
          <div class="modal-body">
            <div v-if="logs.length === 0" class="empty-state">
              <span class="material-symbols-outlined icon-36">history_toggle_off</span>
              <p>{{ t('admin.kyc.historyEmpty') }}</p>
            </div>

            <div v-else class="timeline-list">
              <div v-for="log in logs" :key="String(log.id)" class="timeline-item">
                <div class="timeline-marker" :class="getActionBadgeClass(log.decision)"></div>
                <div class="timeline-content">
                  <div class="timeline-header">
                    <span class="action-badge" :class="getActionBadgeClass(log.decision)">
                      {{ log.action }}
                    </span>
                    <span class="timeline-time">{{ log.created_at }}</span>
                  </div>

                  <div class="timeline-author">
                    <span class="author-label">{{ t('admin.kyc.colReviewer') }}:</span>
                    <span class="author-name">{{ log.reviewer_name }}</span>
                  </div>

                  <p v-if="log.notes" class="timeline-notes">"{{ log.notes }}"</p>

                  <div v-if="log.rejection_reason" class="timeline-reason">
                    <span class="material-symbols-outlined icon-16">warning</span>
                    <span>{{ log.rejection_reason }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.history-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.history-modal-container {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 20px;
  width: 100%;
  max-width: 680px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
  background: var(--surface, #ffffff);
}

.header-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon-box {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.modal-subtitle {
  font-size: 12px;
  color: var(--muted, #817476);
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
  max-height: calc(85vh - 90px);
  scrollbar-width: thin;
  scrollbar-color: var(--outline, rgba(140, 140, 140, 0.25)) transparent;
}

.modal-body::-webkit-scrollbar {
  width: 6px;
}

.modal-body::-webkit-scrollbar-track {
  background: transparent;
}

.modal-body::-webkit-scrollbar-thumb {
  background: var(--outline, #e1e3e4);
  border-radius: 999px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
  background: var(--muted, #817476);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 16px;
  color: var(--muted, #817476);
  gap: 8px;
  text-align: center;
  font-size: 13px;
}

.icon-36 {
  font-size: 36px;
}
.icon-22 {
  font-size: 22px;
}
.icon-18 {
  font-size: 18px;
}
.icon-16 {
  font-size: 16px;
}

.timeline-list {
  display: flex;
  flex-direction: column;
  gap: 18px;
  position: relative;
  padding-left: 20px;
  border-left: 2px solid var(--outline, #e2e8f0);
}

.timeline-item {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.timeline-marker {
  position: absolute;
  left: -27px;
  top: 4px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--outline, #e2e8f0);
  border: 2px solid var(--surface, #ffffff);
}

.timeline-marker.badge-success {
  background: #10b981;
}
.timeline-marker.badge-danger {
  background: #ef4444;
}
.timeline-marker.badge-warning {
  background: #f59e0b;
}
.timeline-marker.badge-info {
  background: #3b82f6;
}

.timeline-content {
  background: var(--surface-container-low, #f8f9fa);
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 12px;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.action-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
}

.action-badge.badge-success {
  background: #d1fae5;
  color: #059669;
}
.action-badge.badge-danger {
  background: #fee2e2;
  color: #dc2626;
}
.action-badge.badge-warning {
  background: #fef3c7;
  color: #d97706;
}
.action-badge.badge-info {
  background: #e0f2fe;
  color: #0284c7;
}

.timeline-time {
  font-size: 11px;
  color: var(--muted, #817476);
}

.timeline-author {
  font-size: 12px;
  display: flex;
  gap: 4px;
}

.author-label {
  color: var(--muted, #817476);
}
.author-name {
  font-weight: 700;
  color: var(--ink, #191c1d);
}

.timeline-notes {
  font-size: 13px;
  font-style: italic;
  color: var(--ink, #191c1d);
  margin: 4px 0 0;
  background: var(--surface, #ffffff);
  padding: 8px 12px;
  border-radius: 8px;
  border-left: 3px solid var(--primary, #78555e);
}

.timeline-reason {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #b91c1c;
  background: #fef2f2;
  padding: 6px 10px;
  border-radius: 6px;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.25s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
