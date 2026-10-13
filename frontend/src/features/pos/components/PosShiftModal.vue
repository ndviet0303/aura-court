<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PosShift } from '../types/pos'
import { posApi } from '../api/posApi'
import { api } from '@/core/api/client'

const props = defineProps<{
  show: boolean
  activeShift: PosShift | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'openShift', openingCash: number): void
  (e: 'closeShift', shiftId: number, declaredCash: number, note: string): void
}>()

const { t } = useI18n()

const activeTab = ref<'current' | 'history'>('current')
const openingCash = ref(1000000)
const declaredCash = ref(0)
const shiftNote = ref('')

const shiftsHistory = ref<any[]>([])
const loadingHistory = ref(false)
const reviewingShiftId = ref<number | null>(null)
const reviewStatus = ref<'matched' | 'approved' | 'exception'>('approved')
const reviewNote = ref('')
const submittingReview = ref(false)

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

const expectedCash = computed(() => {
  if (!props.activeShift) return 0
  const opening = props.activeShift.opening_cash ?? props.activeShift.initial_cash ?? 0
  const cashSales = props.activeShift.cash_sales ?? 0
  return props.activeShift.expected_cash ?? opening + cashSales
})

const liveVariance = computed(() => {
  return declaredCash.value - expectedCash.value
})

watch(
  () => props.activeShift,
  (shift) => {
    if (shift) {
      declaredCash.value = expectedCash.value
    }
  },
  { immediate: true },
)

watch(
  () => props.show,
  (isOpen) => {
    if (isOpen && activeTab.value === 'history') {
      loadHistory()
    }
  },
)

async function loadHistory() {
  loadingHistory.value = true
  try {
    const data = await posApi.getShifts()
    shiftsHistory.value = Array.isArray(data) ? data : (data as any)?.data || []
  } catch (err) {
    console.error('Failed to load shifts history:', err)
  } finally {
    loadingHistory.value = false
  }
}

function showHistory() {
  activeTab.value = 'history'
  loadHistory()
}

function startReview(shiftId: number) {
  reviewingShiftId.value = shiftId
  reviewStatus.value = 'approved'
}

function handleOpen() {
  emit('openShift', openingCash.value)
}

function handleClose() {
  if (props.activeShift) {
    emit('closeShift', props.activeShift.id, declaredCash.value, shiftNote.value)
  }
}

async function handleReviewReconciliation(shiftId: number) {
  submittingReview.value = true
  try {
    await api.post(`/pos/shifts/${shiftId}/review`, {
      status: reviewStatus.value,
      note: reviewNote.value || 'Quản lý duyệt đối soát ca',
    })
    reviewingShiftId.value = null
    reviewNote.value = ''
    await loadHistory()
  } catch (err) {
    console.error('Failed to review reconciliation:', err)
  } finally {
    submittingReview.value = false
  }
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined modal-icon">point_of_sale</span>
          <h3 class="modal-title">{{ t('admin.pos.shiftModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Tab Switcher -->
      <div class="modal-tabs">
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'current' }"
          @click="activeTab = 'current'"
        >
          <span class="material-symbols-outlined icon-18">badge</span>
          <span>{{ t('admin.pos.tabCurrentShift') }}</span>
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'history' }"
          @click="showHistory"
        >
          <span class="material-symbols-outlined icon-18">history</span>
          <span>{{ t('admin.pos.tabShiftHistory') }}</span>
        </button>
      </div>

      <div class="modal-body">
        <!-- TAB 1: CURRENT SHIFT -->
        <template v-if="activeTab === 'current'">
          <!-- If Shift is Currently Open -->
          <div v-if="activeShift" class="shift-active-box">
            <div class="shift-status-badge">
              <span class="status-dot"></span>
              <span
                >{{ t('admin.pos.shiftActiveBadge', { id: activeShift.id }) }} ({{
                  t('admin.pos.shiftStatusOpen')
                }})</span
              >
            </div>

            <div class="shift-stats-grid">
              <div class="stat-card">
                <span class="stat-label">{{ t('admin.pos.shiftOpenedAt') }}</span>
                <strong class="stat-val">{{ activeShift.opened_at }}</strong>
              </div>
              <div class="stat-card">
                <span class="stat-label">{{ t('admin.pos.shiftOpeningCash') }}</span>
                <strong class="stat-val">{{
                  formatMoney(activeShift.initial_cash || activeShift.opening_cash || 0)
                }}</strong>
              </div>
              <div class="stat-card">
                <span class="stat-label">{{ t('admin.pos.shiftCashSales') }}</span>
                <strong class="stat-val text-green">{{
                  formatMoney(activeShift.cash_sales || 0)
                }}</strong>
              </div>
              <div class="stat-card">
                <span class="stat-label">{{ t('admin.pos.shiftQrWalletSales') }}</span>
                <strong class="stat-val text-blue">{{
                  formatMoney((activeShift.qr_sales || 0) + (activeShift.wallet_sales || 0))
                }}</strong>
              </div>
              <div class="stat-card highlight-expected">
                <span class="stat-label">{{ t('admin.pos.shiftExpectedCash') }}</span>
                <strong class="stat-val text-amber font-bold">{{
                  formatMoney(expectedCash)
                }}</strong>
              </div>
            </div>

            <div class="close-shift-form">
              <h4 class="form-section-title">
                <span class="material-symbols-outlined icon-18">lock</span>
                <span>{{ t('admin.pos.shiftCloseTitle') }}</span>
              </h4>

              <div class="form-group">
                <label class="form-label">
                  {{ t('admin.pos.shiftDeclaredCashLabel') }}
                  {{ t('admin.pos.shiftActualCashNote') }}
                </label>
                <input
                  v-model.number="declaredCash"
                  type="number"
                  step="10000"
                  class="form-input text-bold"
                  :placeholder="t('admin.pos.shiftDeclaredCashPlaceholder')"
                />
              </div>

              <!-- Live Variance Feedback -->
              <div
                class="variance-banner"
                :class="{
                  matched: liveVariance === 0,
                  deficit: liveVariance < 0,
                  surplus: liveVariance > 0,
                }"
              >
                <div class="variance-header">
                  <span class="material-symbols-outlined icon-18">
                    {{
                      liveVariance === 0
                        ? 'check_circle'
                        : liveVariance < 0
                          ? 'warning'
                          : 'add_circle'
                    }}
                  </span>
                  <span>{{ t('admin.pos.shiftVarianceLabel') }}</span>
                  <strong>{{ liveVariance > 0 ? '+' : '' }}{{ formatMoney(liveVariance) }}</strong>
                </div>
                <div class="variance-subtext">
                  <span v-if="liveVariance === 0">{{ t('admin.pos.shiftVarianceMatched') }}</span>
                  <span v-else-if="liveVariance < 0">{{
                    t('admin.pos.shiftVarianceDeficit')
                  }}</span>
                  <span v-else>{{ t('admin.pos.shiftVarianceSurplus') }}</span>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">{{ t('admin.pos.shiftNoteLabel') }}</label>
                <textarea
                  v-model="shiftNote"
                  rows="2"
                  class="form-textarea"
                  :placeholder="t('admin.pos.shiftNotePlaceholder')"
                ></textarea>
              </div>
            </div>
          </div>

          <!-- If No Shift is Open -->
          <div v-else class="shift-open-form">
            <div class="empty-shift-banner">
              <span class="material-symbols-outlined shift-banner-icon">lock_open</span>
              <h4>{{ t('admin.pos.noShiftOpenTitle') }}</h4>
              <p>{{ t('admin.pos.noShiftOpenSub') }}</p>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.pos.openingCashLabel') }}</label>
              <input
                v-model.number="openingCash"
                type="number"
                step="50000"
                class="form-input text-bold"
                :placeholder="t('admin.pos.openingCashPlaceholder')"
              />
            </div>
          </div>
        </template>

        <!-- TAB 2: SHIFTS HISTORY & RECONCILIATION -->
        <template v-else>
          <div class="history-container">
            <div v-if="loadingHistory" class="history-loading">
              <span class="material-symbols-outlined spin">progress_activity</span>
              <span>{{ t('admin.pos.loadingShifts') }}</span>
            </div>

            <div v-else-if="shiftsHistory.length === 0" class="history-empty">
              <span>{{ t('admin.pos.noShiftsRecorded') }}</span>
            </div>

            <div v-else class="shifts-table-wrapper">
              <table class="shifts-table">
                <thead>
                  <tr>
                    <th>{{ t('admin.pos.tblShift') }}</th>
                    <th>{{ t('admin.pos.tblCashier') }}</th>
                    <th>{{ t('admin.pos.tblTime') }}</th>
                    <th>{{ t('admin.pos.tblOpeningCash') }}</th>
                    <th>{{ t('admin.pos.tblExpected') }}</th>
                    <th>{{ t('admin.pos.tblActual') }}</th>
                    <th>{{ t('admin.pos.tblVariance') }}</th>
                    <th>{{ t('admin.pos.tblReconciliation') }}</th>
                    <th>{{ t('admin.pos.tblActions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="s in shiftsHistory" :key="s.id">
                    <td>
                      <strong>#{{ s.id }}</strong>
                    </td>
                    <td>{{ s.cashier?.name || t('admin.pos.tblCashier') }}</td>
                    <td>
                      <div class="time-cell">
                        <span>{{ s.opened_at }}</span>
                        <span v-if="s.closed_at" class="text-sub">→ {{ s.closed_at }}</span>
                        <span v-else class="status-open-pill">{{
                          t('admin.pos.shiftStatusOpen')
                        }}</span>
                      </div>
                    </td>
                    <td>{{ formatMoney(s.opening_cash) }}</td>
                    <td>{{ formatMoney(s.expected_cash) }}</td>
                    <td>{{ s.declared_cash !== null ? formatMoney(s.declared_cash) : '—' }}</td>
                    <td>
                      <span
                        v-if="s.variance !== null"
                        :class="{
                          'text-green font-bold': s.variance === 0,
                          'text-red font-bold': s.variance < 0,
                          'text-blue font-bold': s.variance > 0,
                        }"
                      >
                        {{ s.variance > 0 ? '+' : '' }}{{ formatMoney(s.variance) }}
                      </span>
                      <span v-else>—</span>
                    </td>
                    <td>
                      <span
                        v-if="s.status === 'closed'"
                        class="reconcile-pill"
                        :class="
                          s.reconciliation?.status || (s.variance === 0 ? 'matched' : 'exception')
                        "
                      >
                        {{
                          s.reconciliation?.status === 'approved'
                            ? t('admin.pos.statusApproved')
                            : s.variance === 0
                              ? t('admin.pos.shiftVarianceMatched')
                              : t('admin.pos.tblVariance')
                        }}
                      </span>
                      <span v-else class="reconcile-pill open">{{
                        t('admin.pos.statusNotClosed')
                      }}</span>
                    </td>
                    <td>
                      <button
                        v-if="s.status === 'closed' && s.reconciliation?.status !== 'approved'"
                        type="button"
                        class="btn-review"
                        @click="startReview(s.id)"
                      >
                        {{ t('admin.pos.btnReview') }}
                      </button>
                      <span
                        v-else-if="s.reconciliation?.status === 'approved'"
                        class="text-green text-sm"
                      >
                        {{ t('admin.pos.statusReviewed') }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- Review Drawer/Modal -->
              <div v-if="reviewingShiftId" class="review-box">
                <h4>{{ t('admin.pos.reviewTitle', { id: reviewingShiftId }) }}</h4>
                <div class="review-fields">
                  <select v-model="reviewStatus" class="form-select">
                    <option value="approved">{{ t('admin.pos.optApprove') }}</option>
                    <option value="exception">{{ t('admin.pos.optException') }}</option>
                    <option value="matched">{{ t('admin.pos.optMatched') }}</option>
                  </select>
                  <input
                    v-model="reviewNote"
                    type="text"
                    :placeholder="t('admin.pos.reviewNotePlaceholder')"
                    class="form-input"
                  />
                  <button
                    type="button"
                    class="btn-confirm-review"
                    :disabled="submittingReview"
                    @click="handleReviewReconciliation(reviewingShiftId)"
                  >
                    {{ t('admin.pos.saveReconciliation') }}
                  </button>
                  <button type="button" class="btn-cancel-review" @click="reviewingShiftId = null">
                    {{ t('admin.pos.close') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <div v-if="activeTab === 'current'" class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">
          {{ t('admin.pos.cancel') }}
        </button>

        <button
          v-if="activeShift"
          type="button"
          class="btn-danger"
          :disabled="loading"
          @click="handleClose"
        >
          <span class="material-symbols-outlined icon-18">lock</span>
          <span>{{ loading ? t('admin.pos.processing') : 'Xác nhận Đóng ca & Đối soát' }}</span>
        </button>

        <button
          v-else
          type="button"
          class="btn-primary"
          :disabled="loading || openingCash < 0"
          @click="handleOpen"
        >
          <span class="material-symbols-outlined icon-18">key</span>
          <span>{{ loading ? t('admin.pos.processing') : t('admin.pos.openShiftBtn') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 540px;
  background: #1e293b;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  color: #f8fafc;
  display: flex;
  flex-direction: column;
}

.modal-lg {
  max-width: 780px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  color: #10b981;
  font-size: 22px;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #f8fafc;
}

.modal-close-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
}

.modal-tabs {
  display: flex;
  background: rgba(15, 23, 42, 0.5);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 16px;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: #94a3b8;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}

.tab-btn.active {
  color: #38bdf8;
  border-bottom-color: #38bdf8;
  background: rgba(56, 189, 248, 0.06);
}

.modal-body {
  padding: 18px 20px;
  overflow-y: auto;
  max-height: 70vh;
}

.shift-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid rgba(16, 185, 129, 0.3);
  padding: 4px 10px;
  border-radius: 9999px;
  color: #34d399;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 14px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #34d399;
}

.shift-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}

.stat-card {
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.highlight-expected {
  border-color: rgba(245, 158, 11, 0.3);
  background: rgba(245, 158, 11, 0.05);
  grid-column: span 2;
}

.stat-label {
  font-size: 11px;
  color: #94a3b8;
  text-transform: uppercase;
}

.stat-val {
  font-size: 14px;
  color: #f1f5f9;
}

.text-green {
  color: #4ade80;
}
.text-blue {
  color: #38bdf8;
}
.text-amber {
  color: #fbbf24;
}
.text-red {
  color: #f87171;
}
.text-bold {
  font-weight: 700;
}
.font-bold {
  font-weight: 700;
}

.close-shift-form {
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  padding-top: 14px;
}

.form-section-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  margin: 0 0 12px 0;
  color: #e2e8f0;
}

.form-group {
  margin-bottom: 12px;
}

.form-label {
  display: block;
  font-size: 13px;
  color: #94a3b8;
  margin-bottom: 4px;
}

.form-input,
.form-textarea,
.form-select {
  width: 100%;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 6px;
  padding: 8px 12px;
  color: #f8fafc;
  font-size: 14px;
}

.form-input:focus,
.form-textarea:focus,
.form-select:focus {
  outline: none;
  border-color: #38bdf8;
}

.variance-banner {
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.variance-banner.matched {
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #34d399;
}

.variance-banner.deficit {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #f87171;
}

.variance-banner.surplus {
  background: rgba(56, 189, 248, 0.15);
  border: 1px solid rgba(56, 189, 248, 0.3);
  color: #38bdf8;
}

.variance-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
}

.variance-subtext {
  font-size: 12px;
  opacity: 0.9;
}

.empty-shift-banner {
  text-align: center;
  padding: 24px 16px;
  background: rgba(15, 23, 42, 0.4);
  border-radius: 8px;
  margin-bottom: 16px;
}

.shift-banner-icon {
  font-size: 40px;
  color: #38bdf8;
  margin-bottom: 8px;
}

.empty-shift-banner h4 {
  margin: 0 0 4px 0;
  font-size: 16px;
}

.empty-shift-banner p {
  margin: 0;
  font-size: 13px;
  color: #94a3b8;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 12px 18px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(15, 23, 42, 0.4);
}

.btn-cancel {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #94a3b8;
  padding: 8px 14px;
  border-radius: 6px;
  cursor: pointer;
}

.btn-primary {
  background: #0284c7;
  border: none;
  color: white;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-danger {
  background: #dc2626;
  border: none;
  color: white;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-primary:disabled,
.btn-danger:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.shifts-table-wrapper {
  overflow-x: auto;
}

.shifts-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.shifts-table th,
.shifts-table td {
  padding: 8px 10px;
  text-align: left;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.shifts-table th {
  color: #94a3b8;
  font-weight: 500;
}

.time-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
}

.text-sub {
  color: #94a3b8;
}

.status-open-pill {
  color: #34d399;
  font-weight: 600;
}

.reconcile-pill {
  padding: 2px 8px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 600;
}

.reconcile-pill.matched {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
}

.reconcile-pill.exception {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
}

.reconcile-pill.approved {
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8;
}

.reconcile-pill.open {
  background: rgba(255, 255, 255, 0.08);
  color: #94a3b8;
}

.btn-review {
  background: rgba(56, 189, 248, 0.15);
  border: 1px solid rgba(56, 189, 248, 0.3);
  color: #38bdf8;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;
}

.review-box {
  margin-top: 14px;
  padding: 12px 14px;
  background: #0f172a;
  border: 1px solid rgba(56, 189, 248, 0.3);
  border-radius: 8px;
}

.review-box h4 {
  margin: 0 0 8px 0;
  font-size: 13px;
  color: #38bdf8;
}

.review-fields {
  display: flex;
  gap: 8px;
  align-items: center;
}

.btn-confirm-review {
  background: #0284c7;
  border: none;
  color: white;
  padding: 6px 12px;
  border-radius: 4px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.btn-cancel-review {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #94a3b8;
  padding: 6px 10px;
  border-radius: 4px;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
