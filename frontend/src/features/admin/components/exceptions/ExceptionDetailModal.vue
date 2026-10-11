<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { FinancialException } from '../../types/exceptions'

defineProps<{
  show: boolean
  item: FinancialException | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'proceed-override', item: FinancialException): void
}>()

const { t, te } = useI18n()

function formatDateTime(isoString?: string): string {
  if (!isoString) return '--'
  try {
    const d = new Date(isoString)
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    const hours = String(d.getHours()).padStart(2, '0')
    const mins = String(d.getMinutes()).padStart(2, '0')
    return `${day}/${month}/${year} ${hours}:${mins}`
  } catch {
    return isoString
  }
}

function getUserInitials(name?: string): string {
  if (!name || typeof name !== 'string') return 'A'
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return 'A'
  if (parts.length === 1 && parts[0]) return parts[0].substring(0, 2).toUpperCase()
  const first = parts[0]?.[0] || 'A'
  const last = parts[parts.length - 1]?.[0] || ''
  return (first + last).toUpperCase()
}

function localizeDescription(item: FinancialException): string {
  if (item.type && te(`admin.exceptions.descriptions.${item.type}`)) {
    return t(`admin.exceptions.descriptions.${item.type}`)
  }
  const desc = resolveLocalizedText(item.description)
  if (desc.includes('Vượt hạn mức') || desc.includes('Deposit limit'))
    return t('admin.exceptions.descriptions.limit_exceeded')
  if (desc.includes('Lệch đối soát') || desc.includes('settlement mismatch'))
    return t('admin.exceptions.descriptions.settlement_mismatch')
  if (desc.includes('Hoàn tiền hủy') || desc.includes('cancellation refund'))
    return t('admin.exceptions.descriptions.late_refund')
  if (desc.includes('săn voucher') || desc.includes('voucher hunting'))
    return t('admin.exceptions.descriptions.voucher_hunting')
  if (desc.includes('Lệch cọc') || desc.includes('deposit hold mismatch'))
    return t('admin.exceptions.descriptions.deposit_hold_mismatch')
  if (desc.includes('Trùng lặp') || desc.includes('Duplicate QR'))
    return t('admin.exceptions.descriptions.duplicate_charge')
  if (desc.includes('ngập sân') || desc.includes('court flooding'))
    return t('admin.exceptions.descriptions.court_flooded_refund')
  return desc
}

function localizeShift(shift?: string, type?: string): string {
  if (!shift && !type) return '--'
  const text = shift || type || ''
  if (text.includes('Sáng') || text.includes('Morning')) return t('admin.exceptions.shifts.morning')
  if (text.includes('Chiều') || text.includes('Afternoon'))
    return t('admin.exceptions.shifts.afternoon')
  if (text.includes('Tối') || text.includes('Evening')) return t('admin.exceptions.shifts.evening')
  if (type && te(`admin.exceptions.descriptions.${type}`))
    return t(`admin.exceptions.descriptions.${type}`)
  return text
}

function localizeMemberTier(tier?: string): string {
  if (!tier) return ''
  if (tier.includes('Vàng') || tier.includes('Gold')) return t('admin.exceptions.memberTiers.gold')
  if (tier.includes('Kim Cương') || tier.includes('Diamond'))
    return t('admin.exceptions.memberTiers.diamond')
  if (tier.includes('Bạc') || tier.includes('Silver'))
    return t('admin.exceptions.memberTiers.silver')
  if (tier.includes('vãng lai') || tier.includes('Guest'))
    return t('admin.exceptions.memberTiers.guest')
  if (tier.includes('mới') || tier.includes('New')) return t('admin.exceptions.memberTiers.new')
  return tier
}
</script>

<template>
  <div>
    <!-- Backdrop -->
    <transition name="fade">
      <div v-if="show" class="modal-backdrop" @click="emit('close')"></div>
    </transition>

    <!-- Modal Box -->
    <transition name="modal-pop">
      <div v-if="show && item" class="modal-wrapper">
        <div class="detail-modal">
          <!-- Header -->
          <div class="modal-header">
            <div class="header-content">
              <div class="title-with-pill">
                <span class="material-symbols-outlined icon-22 text-primary">receipt_long</span>
                <h3 class="modal-title">{{ t('admin.exceptions.detailModalTitle') }}</h3>
                <span
                  class="risk-badge"
                  :class="{
                    'risk-critical': item.severity === 'critical',
                    'risk-medium': item.severity === 'medium',
                    'risk-low': item.severity === 'low',
                  }"
                >
                  <span class="material-symbols-outlined icon-14">
                    {{
                      item.severity === 'critical'
                        ? 'warning'
                        : item.severity === 'medium'
                          ? 'priority_high'
                          : 'info'
                    }}
                  </span>
                  <span>
                    {{
                      item.severity === 'critical'
                        ? t('admin.exceptions.riskHigh')
                        : item.severity === 'medium'
                          ? t('admin.exceptions.riskMedium')
                          : t('admin.exceptions.riskLow')
                    }}
                  </span>
                </span>
              </div>
              <p class="modal-sub">{{ t('admin.exceptions.detailModalSub') }}</p>
            </div>
            <button class="btn-close-modal" type="button" @click="emit('close')">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Body -->
          <div class="modal-body">
            <!-- 1. General Reference Box -->
            <section class="section-box">
              <h4 class="section-title">
                <span class="material-symbols-outlined icon-16 text-primary">info</span>
                <span>{{ t('admin.exceptions.generalInfo') }}</span>
              </h4>
              <div class="grid-2col">
                <div class="meta-item">
                  <span class="meta-label">{{ t('admin.exceptions.hdrTxnCode') }}</span>
                  <span class="meta-value font-mono font-bold text-ink">{{ item.reference }}</span>
                </div>
                <div class="meta-item">
                  <span class="meta-label">{{ t('admin.exceptions.filterStatus') }}</span>
                  <span class="meta-value">
                    <span
                      class="status-pill"
                      :class="item.status === 'resolved' ? 'status-resolved' : 'status-open'"
                    >
                      <span class="material-symbols-outlined icon-12">
                        {{ item.status === 'resolved' ? 'done_all' : 'pending_actions' }}
                      </span>
                      <span>
                        {{
                          item.status === 'resolved'
                            ? t('admin.exceptions.statusDone')
                            : t('admin.exceptions.statusPending')
                        }}
                      </span>
                    </span>
                  </span>
                </div>
                <div class="meta-item">
                  <span class="meta-label">{{ t('admin.exceptions.filterDate') }}</span>
                  <span class="meta-value font-mono">{{ formatDateTime(item.detected_at) }}</span>
                </div>
                <div class="meta-item">
                  <span class="meta-label">{{ t('admin.exceptions.hdrTimeAndShift') }}</span>
                  <span class="meta-value">{{
                    localizeShift(item.metadata?.shift, item.type)
                  }}</span>
                </div>
              </div>
            </section>

            <!-- 2. Variance & Discrepancy Breakdown -->
            <section class="section-box">
              <h4 class="section-title">
                <span class="material-symbols-outlined icon-16 text-primary">query_stats</span>
                <span>{{ t('admin.exceptions.anomalyAnalysis') }}</span>
              </h4>
              <div class="discrepancy-card">
                <div class="amount-compare-grid">
                  <div class="amount-cell-box">
                    <span class="amount-lbl">{{ t('admin.exceptions.expectedAmount') }}</span>
                    <span class="amount-val font-mono">
                      {{ formatCurrency(item.metadata?.expected_amount ?? item.amount) }}
                    </span>
                  </div>
                  <div class="delta-arrow-box">
                    <span class="material-symbols-outlined text-muted">arrow_forward</span>
                  </div>
                  <div class="amount-cell-box highlight-critical">
                    <span class="amount-lbl">{{ t('admin.exceptions.actualAmount') }}</span>
                    <span class="amount-val font-mono text-critical">
                      {{ formatCurrency(item.metadata?.actual_amount ?? item.amount) }}
                    </span>
                  </div>
                </div>

                <div class="reason-desc-box">
                  <span class="reason-hdr">{{ t('admin.exceptions.alertReasonLabel') }}:</span>
                  <span class="reason-text">{{ localizeDescription(item) }}</span>
                </div>

                <div class="grid-2col pt-2 border-top">
                  <div class="meta-item">
                    <span class="meta-label">{{ t('admin.exceptions.paymentChannel') }}</span>
                    <span class="meta-value font-mono font-medium">{{
                      item.metadata?.gateway || 'VietQR Pro'
                    }}</span>
                  </div>
                  <div class="meta-item">
                    <span class="meta-label">{{ t('admin.exceptions.courtVenue') }}</span>
                    <span class="meta-value">{{
                      item.metadata?.court || 'Cụm Sân Thể Thao Aura'
                    }}</span>
                  </div>
                </div>
              </div>
            </section>

            <!-- 3. Customer KYC Profile -->
            <section class="section-box">
              <h4 class="section-title">
                <span class="material-symbols-outlined icon-16 text-primary">person</span>
                <span>{{ t('admin.exceptions.entityLabel') }}</span>
              </h4>
              <div class="user-profile-box">
                <div class="user-avatar-initial">
                  {{
                    getUserInitials(
                      item.metadata?.user?.name ||
                        item.resolver?.name ||
                        t('admin.schedule.systemUser'),
                    )
                  }}
                </div>
                <div class="user-profile-info">
                  <div class="user-title-row">
                    <span class="user-fullname">
                      {{
                        resolveLocalizedText(
                          item.metadata?.user?.name ||
                            item.resolver?.name ||
                            t('admin.schedule.systemUser'),
                        )
                      }}
                    </span>
                    <span v-if="item.metadata?.user?.tier" class="tier-badge">
                      {{ localizeMemberTier(item.metadata.user.tier) }}
                    </span>
                  </div>
                  <div class="user-meta-sub font-mono">
                    <span>{{
                      item.metadata?.user?.id ||
                      (item.resolver?.id ? `UID-${item.resolver.id}` : 'UID-442')
                    }}</span>
                    <span>·</span>
                    <span>{{ item.resolver?.email || 'customer.account@aura.vn' }}</span>
                  </div>
                </div>
              </div>
            </section>

            <!-- 4. Resolution Dossier (If already resolved) -->
            <section v-if="item.status === 'resolved'" class="section-box resolution-box">
              <h4 class="section-title text-success">
                <span class="material-symbols-outlined icon-16">verified</span>
                <span>{{ t('admin.exceptions.statusDone') }}</span>
              </h4>
              <p class="resolution-note font-mono">
                {{
                  item.resolution ||
                  t('admin.exceptions.overrideSuccessMsg', { ref: item.reference })
                }}
              </p>
              <div class="resolution-meta font-mono">
                <span
                  >{{ t('admin.exceptions.hdrTimeAndShift') }}:
                  {{ formatDateTime(item.resolved_at) }}</span
                >
              </div>
            </section>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <AuraButton variant="outline" @click="emit('close')">
              <span>{{ t('admin.exceptions.closeModal') }}</span>
            </AuraButton>
            <AuraButton
              v-if="item.status !== 'resolved'"
              variant="primary"
              @click="emit('proceed-override', item)"
            >
              <span class="material-symbols-outlined icon-16">gavel</span>
              <span>{{ t('admin.exceptions.proceedOverride') }}</span>
            </AuraButton>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  z-index: 120;
}

.modal-wrapper {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 121;
}

.detail-modal {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 20px;
  width: 640px;
  max-width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}

.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline, #e1e3e4);
  background: var(--surface-container-low, #f8f9fa);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.header-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.title-with-pill {
  display: flex;
  align-items: center;
  gap: 10px;
}

.modal-title {
  font-size: 18px;
  font-weight: 800;
  margin: 0;
  color: var(--ink, #191c1d);
}

.modal-sub {
  font-size: 12px;
  color: var(--muted, #817476);
  margin: 0;
}

.btn-close-modal {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--muted, #817476);
  padding: 6px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-close-modal:hover {
  background: var(--surface-container-high, #e7e8e9);
  color: var(--ink, #191c1d);
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: var(--outline, rgba(140, 140, 140, 0.25)) transparent;
}

.modal-body::-webkit-scrollbar {
  width: 5px;
}

.modal-body::-webkit-scrollbar-track {
  background: transparent;
}

.modal-body::-webkit-scrollbar-thumb {
  background: var(--outline, rgba(140, 140, 140, 0.25));
  border-radius: 999px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
  background: var(--muted, rgba(140, 140, 140, 0.45));
}

.section-box {
  background: var(--surface-container-low, #f8f9fa);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.grid-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.meta-label {
  font-size: 11px;
  color: var(--muted, #817476);
}

.meta-value {
  font-size: 13px;
  color: var(--ink, #191c1d);
}

.discrepancy-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.amount-compare-grid {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 10px;
  padding: 12px 16px;
}

.amount-cell-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.amount-lbl {
  font-size: 11px;
  color: var(--muted, #817476);
}

.amount-val {
  font-size: 16px;
  font-weight: 800;
  color: var(--ink, #191c1d);
}

.delta-arrow-box {
  display: flex;
  align-items: center;
  justify-content: center;
}

.reason-desc-box {
  font-size: 13px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 8px;
  padding: 10px 12px;
  display: flex;
  gap: 6px;
}

.reason-hdr {
  font-weight: 600;
  color: var(--muted, #5d5f5f);
}

.reason-text {
  color: var(--ink, #191c1d);
  font-weight: 500;
}

.border-top {
  border-top: 1px solid var(--outline, #e1e3e4);
}
.pt-2 {
  padding-top: 8px;
}

.user-profile-box {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 10px;
  padding: 12px 14px;
}

.user-avatar-initial {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--surface-container-high, #dfe0e0);
  color: var(--ink, #191c1d);
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
}

.user-profile-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.user-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-fullname {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

.tier-badge {
  font-size: 11px;
  font-weight: 700;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  padding: 2px 8px;
  border-radius: 999px;
}

.user-meta-sub {
  font-size: 12px;
  color: var(--muted, #817476);
  display: flex;
  align-items: center;
  gap: 6px;
}

.resolution-box {
  background: rgba(16, 185, 129, 0.1);
  border-color: rgba(16, 185, 129, 0.3);
}

.resolution-note {
  font-size: 13px;
  color: #10b981;
  margin: 0;
}

.resolution-meta {
  font-size: 11px;
  color: #10b981;
}

.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--outline, #e1e3e4);
  background: var(--surface-container-low, #f8f9fa);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.risk-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

.risk-critical {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}
.risk-medium {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
}
.risk-low {
  background: rgba(99, 102, 241, 0.15);
  color: #818cf8;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.status-open {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
}
.status-resolved {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}

.text-primary {
  color: var(--primary, #78555e);
}
.text-critical {
  color: #ba1a1a;
}
.text-success {
  color: #10b981;
}
.text-ink {
  color: var(--ink, #191c1d);
}
.text-muted {
  color: var(--muted, #817476);
}

.icon-12 {
  font-size: 12px;
}
.icon-14 {
  font-size: 14px;
}
.icon-16 {
  font-size: 16px;
}
.icon-22 {
  font-size: 22px;
}

/* Modal pop transition */
.modal-pop-enter-active,
.modal-pop-leave-active {
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-pop-enter-from,
.modal-pop-leave-to {
  transform: scale(0.95);
  opacity: 0;
}
</style>
