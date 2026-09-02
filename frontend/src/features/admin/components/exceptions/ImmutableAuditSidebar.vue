<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { SkeletonLoader } from '@/shared/components'
import type { AuditLog } from '../../types/exceptions'

defineProps<{
  logs: AuditLog[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'download-ca'): void
}>()

const { t, te } = useI18n()

function getIconForType(type: string): string {
  switch (type) {
    case 'override':
      return 'person'
    case 'flag':
      return 'flag'
    case 'block':
      return 'block'
    case 'unlock':
      return 'lock_open'
    default:
      return 'shield'
  }
}

function localizeAuditAction(action: string): string {
  if (!action) return ''
  const norm = action.toLowerCase().replace(/[\s.-]+/g, '_')
  const key = `admin.exceptions.auditActions.${norm}`
  if (te(key)) {
    return t(key)
  }
  if (norm.includes('invoice_paid') || norm.includes('payment')) return t('admin.exceptions.auditActions.contract_invoice_paid')
  if (norm.includes('contract_created') || norm.includes('contract')) return t('admin.exceptions.auditActions.contract_created')
  if (norm.includes('venue_updated') || norm.includes('venue')) return t('admin.exceptions.auditActions.venue_updated')
  if (norm.includes('price_deleted') || norm.includes('price')) return t('admin.exceptions.auditActions.price_deleted')
  if (norm.includes('price_updated')) return t('admin.exceptions.auditActions.price_updated')
  if (norm.includes('resolve') || norm.includes('override') || norm.includes('duyệt') || norm.includes('hoàn_tiền')) return t('admin.exceptions.auditActions.finance_exception_resolve')
  if (norm.includes('flag') || norm.includes('cảnh_báo')) return t('admin.exceptions.auditActions.flag')
  if (norm.includes('block') || norm.includes('chặn')) return t('admin.exceptions.auditActions.block')
  if (norm.includes('unlock') || norm.includes('mở_khóa')) return t('admin.exceptions.auditActions.unlock')
  return action
}

function localizeAuditDetails(details: string, log: AuditLog): string {
  if (!details) return ''
  const norm = details.toLowerCase().replace(/[\s.-]+/g, '_')
  const key = `admin.exceptions.auditDetails.${norm}`
  if (te(key)) {
    return t(key)
  }
  if (norm.includes('contract_invoice_paid') || norm.includes('invoice')) return t('admin.exceptions.auditDetails.contract_invoice_paid')
  if (norm.includes('venue_updated') || norm.includes('venue')) return t('admin.exceptions.auditDetails.venue_updated')
  if (norm.includes('price_deleted') || norm.includes('price')) return t('admin.exceptions.auditDetails.price_deleted')
  if (norm.includes('contract_created') || norm.includes('contract')) return t('admin.exceptions.auditDetails.contract_created')
  if (norm.includes('vượt_hạn_mức') || norm.includes('limit')) return t('admin.exceptions.auditDetails.limit_blocked')
  if (norm.includes('hoàn_cọc') || norm.includes('deposit')) return t('admin.exceptions.auditDetails.deposit_unlocked')
  if (norm.includes('voucher') || norm.includes('săn')) return t('admin.exceptions.auditDetails.flag_voucher')
  return details
}
</script>

<template>
  <aside class="audit-trail-sidebar">
    <div class="sidebar-header-box">
      <div class="audit-title-row">
        <h2 class="sidebar-title">{{ t('admin.exceptions.auditSidebarTitle') }}</h2>
        <span class="sha-badge">
          <span class="material-symbols-outlined icon-12">shield</span>
          <span>SHA-256</span>
        </span>
      </div>
      <p class="audit-sub">{{ t('admin.exceptions.auditSidebarSub') }}</p>
    </div>

    <!-- Timeline list -->
    <div class="timeline-container">
      <div class="timeline-track-line"></div>

      <!-- Skeleton Loading State -->
      <template v-if="loading">
        <div v-for="i in 3" :key="`log-skel-${i}`" class="timeline-item">
          <div class="timeline-dot dot-override"></div>
          <div class="timeline-content">
            <div class="timeline-top">
              <SkeletonLoader height="12px" width="70px" borderRadius="4px" />
              <SkeletonLoader height="10px" width="40px" borderRadius="4px" />
            </div>
            <SkeletonLoader height="14px" width="110px" borderRadius="4px" class="my-1" />
            <SkeletonLoader height="28px" width="100%" borderRadius="4px" />
          </div>
        </div>
      </template>

      <!-- Real Log Items -->
      <template v-else>
        <div v-for="log in logs" :key="log.id" class="timeline-item">
          <div class="timeline-dot" :class="`dot-${log.type}`">
            <span class="material-symbols-outlined icon-10">
              {{ getIconForType(log.type) }}
            </span>
          </div>
          <div class="timeline-content">
            <div class="timeline-top">
              <span class="timeline-author font-mono">{{ log.author }}</span>
              <span class="timeline-time font-mono">{{ log.time }}</span>
            </div>
            <div class="timeline-action" :class="`text-${log.type}`">
              {{ localizeAuditAction(log.action) }}
            </div>
            <div class="timeline-details">
              {{ localizeAuditDetails(log.details, log) }}
            </div>
            <div class="timeline-hash font-mono">
              <span class="text-muted">{{ t('admin.exceptions.hashLabel') }}</span>
              <span class="hash-tag">#{{ log.hash }}</span>
            </div>
          </div>
        </div>

        <div v-if="logs.length === 0" class="empty-logs">
          <span class="material-symbols-outlined icon-24">history</span>
          <p>{{ t('admin.exceptions.noExceptionsFound') }}</p>
        </div>
      </template>
    </div>

    <!-- Footer Action -->
    <div class="sidebar-footer-box">
      <button class="btn-download-logs" type="button" @click="emit('download-ca')">
        <span class="material-symbols-outlined icon-16">file_download</span>
        <span>{{ t('admin.exceptions.downloadCaBtn') }}</span>
      </button>
    </div>
  </aside>
</template>

<style scoped>
.audit-trail-sidebar {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.sidebar-header-box {
  padding: 16px;
  border-bottom: 1px solid var(--outline, #e1e3e4);
  background: var(--surface-container-low, #f8f9fa);
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
  color: var(--ink, #191c1d);
}

.sha-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 10px;
  font-weight: 700;
  font-family: monospace;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  padding: 2px 8px;
  border-radius: 6px;
  color: var(--primary, #78555e);
}

.audit-sub {
  font-size: 12px;
  color: var(--muted, #817476);
  margin: 4px 0 0;
}

.timeline-container {
  padding: 18px 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 540px;
  overflow-y: auto;
  position: relative;
  scrollbar-width: thin;
  scrollbar-color: var(--outline, rgba(140, 140, 140, 0.25)) transparent;
}

.timeline-container::-webkit-scrollbar {
  width: 5px;
}

.timeline-container::-webkit-scrollbar-track {
  background: transparent;
}

.timeline-container::-webkit-scrollbar-thumb {
  background: var(--outline, rgba(140, 140, 140, 0.25));
  border-radius: 999px;
}

.timeline-container::-webkit-scrollbar-thumb:hover {
  background: var(--muted, rgba(140, 140, 140, 0.45));
}

.timeline-track-line {
  position: absolute;
  left: 27px;
  top: 24px;
  bottom: 24px;
  width: 1px;
  background: var(--outline-variant, #e1e3e4);
  z-index: 0;
}

.timeline-item {
  position: relative;
  padding-left: 32px;
  z-index: 1;
}

.timeline-dot {
  position: absolute;
  left: 0;
  top: 4px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--surface, #ffffff);
  box-shadow: 0 0 0 1px var(--outline, #e1e3e4);
}

.dot-override {
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}
.dot-flag {
  background: rgba(245, 158, 11, 0.2);
  color: #f59e0b;
}
.dot-block {
  background: rgba(239, 68, 68, 0.2);
  color: #ef4444;
}
.dot-unlock {
  background: rgba(16, 185, 129, 0.2);
  color: #10b981;
}

.timeline-content {
  background: var(--surface-container-low, #f8f9fa);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 10px;
  padding: 12px;
  transition: background 0.2s;
}

.timeline-content:hover {
  background: var(--surface-container, #edeeef);
}

.timeline-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 4px;
}

.timeline-author {
  color: var(--ink, #191c1d);
}

.timeline-time {
  color: var(--muted, #817476);
  font-size: 11px;
}

.timeline-action {
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 3px;
}

.text-override { color: var(--primary, #78555e); }
.text-flag { color: #d97706; }
.text-block { color: #ba1a1a; }
.text-unlock { color: #10b981; }

.timeline-details {
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
  line-height: 1.4;
}

.timeline-hash {
  font-size: 10px;
  color: var(--muted, #817476);
  margin-top: 6px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.hash-tag {
  color: var(--ink, #191c1d);
  font-weight: 600;
  background: var(--surface, #ffffff);
  padding: 1px 4px;
  border-radius: 4px;
  border: 1px solid var(--outline, #e1e3e4);
}

.sidebar-footer-box {
  padding: 14px 16px;
  border-top: 1px solid var(--outline, #e1e3e4);
  background: var(--surface-container-low, #f8f9fa);
}

.btn-download-logs {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px;
  border-radius: 8px;
  border: 1px solid var(--outline, #e1e3e4);
  background: var(--surface, #ffffff);
  font-size: 12px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-download-logs:hover {
  background: var(--surface-container-high, #e7e8e9);
  border-color: var(--primary, #78555e);
  color: var(--primary, #78555e);
}

.empty-logs {
  text-align: center;
  padding: 24px 0;
  color: var(--muted, #817476);
  font-size: 13px;
}

.icon-10 { font-size: 10px; }
.icon-12 { font-size: 12px; }
.icon-16 { font-size: 16px; }
.icon-24 { font-size: 24px; margin-bottom: 4px; }
.my-1 { margin: 4px 0; }
</style>

