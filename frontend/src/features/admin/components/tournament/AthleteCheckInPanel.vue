<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { Participant } from '../../types/tournamentAdmin'
import { AuraButton, SkeletonLoader } from '@/shared/components'

const props = defineProps<{
  athletes: Participant[]
  filterStatus: 'all' | 'pending' | 'checked_in' | 'no_show'
  searchQuery: string
  loading?: boolean
  metrics: {
    total: number
    checkedInCount: number
    pending_count: number
    noShowCount: number
    percentage: number
  }
}>()

const emit = defineEmits<{
  (e: 'update:filterStatus', status: 'all' | 'pending' | 'checked_in' | 'no_show'): void
  (e: 'update:searchQuery', q: string): void
  (e: 'toggleCheckIn', ath: Participant): void
}>()

const { t } = useI18n()

const showQrScannerModal = ref(false)
const showPassModal = ref(false)
const selectedPassAthlete = ref<Participant | null>(null)
const scanCodeInput = ref('')
const scannedAthlete = ref<Participant | null>(null)

function openPassModal(ath: Participant) {
  selectedPassAthlete.value = ath
  showPassModal.value = true
}

function openQrScanner() {
  showQrScannerModal.value = true
  scannedAthlete.value = null
}

function handleScanCode() {
  const code = scanCodeInput.value.trim().toLowerCase()
  if (!code) return
  const found = props.athletes.find(
    (p: Participant) => p.qrCode?.toLowerCase() === code || p.name.toLowerCase().includes(code),
  )
  if (found) {
    scannedAthlete.value = found
    emit('toggleCheckIn', found)
  }
}
</script>

<template>
  <div class="checkin-panel-container">
    <div class="checkin-header-row">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">badge</span>
        <div>
          <h3 class="panel-headline">{{ t('admin.tournaments.checkInHeadline') }}</h3>
          <p class="panel-sub">{{ t('admin.tournaments.checkInSub') }}</p>
        </div>
      </div>

      <div class="checkin-actions-bar">
        <AuraButton variant="secondary" @click="openQrScanner">
          <span class="material-symbols-outlined icon-16">qr_code_scanner</span>
          <span>{{ t('admin.tournaments.scanQrBtn') }}</span>
        </AuraButton>
      </div>
    </div>

    <!-- Metrics & Gauge Row -->
    <div class="checkin-metrics-banner">
      <div class="gauge-col">
        <svg class="radial-svg-gauge" viewBox="0 0 36 36">
          <path
            class="circle-bg"
            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
          />
          <path
            class="circle"
            :stroke-dasharray="`${metrics.percentage}, 100`"
            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
          />
          <text x="18" y="20.35" class="percentage font-mono">{{ metrics.percentage }}%</text>
        </svg>
        <span class="gauge-label">{{ t('admin.tournaments.attendanceRate') }}</span>
      </div>

      <div class="metric-counters-grid">
        <div class="cnt-card">
          <span class="cnt-label">{{ t('admin.tournaments.totalAthletes') }}</span>
          <strong class="cnt-num font-mono">{{ metrics.total }}</strong>
        </div>
        <div class="cnt-card text-green">
          <span class="cnt-label">{{ t('admin.tournaments.checkedInAthletes') }}</span>
          <strong class="cnt-num font-mono">{{ metrics.checkedInCount }}</strong>
        </div>
        <div class="cnt-card text-yellow">
          <span class="cnt-label">{{ t('admin.tournaments.pendingAthletes') }}</span>
          <strong class="cnt-num font-mono">{{ metrics.pending_count }}</strong>
        </div>
      </div>
    </div>

    <!-- Search & Filter Tabs -->
    <div class="checkin-filter-toolbar">
      <div class="filter-pills-bar">
        <button
          type="button"
          class="filter-pill-item"
          :class="{ active: filterStatus === 'all' }"
          @click="emit('update:filterStatus', 'all')"
        >
          {{ t('admin.tournaments.tabAllAthletes', { count: metrics.total }) }}
        </button>
        <button
          type="button"
          class="filter-pill-item"
          :class="{ active: filterStatus === 'pending' }"
          @click="emit('update:filterStatus', 'pending')"
        >
          {{ t('admin.tournaments.tabPendingAthletes', { count: metrics.pending_count }) }}
        </button>
        <button
          type="button"
          class="filter-pill-item"
          :class="{ active: filterStatus === 'checked_in' }"
          @click="emit('update:filterStatus', 'checked_in')"
        >
          {{ t('admin.tournaments.tabCheckedInAthletes', { count: metrics.checkedInCount }) }}
        </button>
      </div>

      <div class="search-field-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="search-input-field"
          :placeholder="t('admin.tournaments.searchAthletePlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="search-clear-btn"
          @click="emit('update:searchQuery', '')"
        >
          <span class="material-symbols-outlined icon-16">close</span>
        </button>
      </div>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="athletes-skeleton-grid">
      <div v-for="i in 4" :key="i" class="skeleton-athlete-card">
        <SkeletonLoader type="avatar" width="44px" height="44px" />
        <div class="skeleton-ath-info">
          <SkeletonLoader type="title" width="60%" height="16px" />
          <SkeletonLoader type="text" width="40%" height="12px" />
        </div>
        <SkeletonLoader type="rect" width="100px" height="32px" rounded="8px" />
      </div>
    </div>

    <!-- Athletes Feed List -->
    <div v-else-if="athletes.length > 0" class="athletes-feed-container">
      <div
        v-for="ath in athletes"
        :key="ath.id"
        class="athlete-feed-card"
        :class="{ 'is-checked-in': ath.checkedIn || ath.status === 'checked_in' }"
      >
        <div class="ath-avatar-wrap">
          <img v-if="ath.avatar" :src="ath.avatar" :alt="ath.name" />
          <div v-else class="ath-initials">{{ ath.name.charAt(0) }}</div>
        </div>

        <div class="ath-info-col">
          <div class="ath-title-row">
            <h4 class="ath-name">{{ resolveLocalizedText(ath.name) }}</h4>
            <span v-if="ath.seed" class="ath-seed-tag font-mono">{{
              t('admin.tournaments.seedLabel', { seed: ath.seed })
            }}</span>
            <span class="ath-category-tag">{{
              ath.category || t('admin.tournaments.defaultCategory')
            }}</span>
          </div>

          <div class="ath-meta-details">
            <span class="meta-club">
              <span class="material-symbols-outlined icon-14">corporate_fare</span>
              {{ ath.club || t('admin.tournaments.freeAgentClub') }}
            </span>
            <span class="meta-phone font-mono">
              <span class="material-symbols-outlined icon-14">call</span>
              {{ ath.phone || '0901234567' }}
            </span>
            <span v-if="ath.checkInTime" class="meta-time font-mono">
              <span class="material-symbols-outlined icon-14">schedule</span>
              {{ t('admin.tournaments.checkedInAt', { time: ath.checkInTime }) }}
            </span>
          </div>
        </div>

        <div class="ath-actions-col">
          <AuraButton variant="secondary" size="sm" @click="openPassModal(ath)">
            <span class="material-symbols-outlined icon-14">badge</span>
            <span>{{ t('admin.tournaments.digitalPassBtn') }}</span>
          </AuraButton>
          <AuraButton
            :variant="ath.checkedIn ? 'secondary' : 'primary'"
            size="sm"
            @click="emit('toggleCheckIn', ath)"
          >
            <span class="material-symbols-outlined icon-14">{{
              ath.checkedIn ? 'check' : 'how_to_reg'
            }}</span>
            <span>{{
              ath.checkedIn
                ? t('admin.tournaments.checkedInBadge')
                : t('admin.tournaments.checkInBtn')
            }}</span>
          </AuraButton>
        </div>
      </div>
    </div>

    <!-- Empty Athletes -->
    <div v-else class="athletes-empty-state">
      <span class="material-symbols-outlined empty-icon">groups</span>
      <h4>{{ t('admin.tournaments.noAthletesFound') }}</h4>
      <p>{{ t('admin.tournaments.noAthletesSub') }}</p>
    </div>

    <!-- QR Code Scanner Modal -->
    <div v-if="showQrScannerModal" class="modal-backdrop" @click.self="showQrScannerModal = false">
      <div class="modal-card modal-qr-scanner-card">
        <div class="modal-header">
          <div class="title-with-icon">
            <span class="material-symbols-outlined card-title-icon">qr_code_scanner</span>
            <h3 class="modal-title">{{ t('admin.tournaments.qrScannerModalTitle') }}</h3>
          </div>
          <button type="button" class="modal-close-btn" @click="showQrScannerModal = false">
            ✕
          </button>
        </div>
        <div class="modal-body scanner-body">
          <div class="camera-preview-box">
            <div class="laser-scanline"></div>
            <div class="scanner-frame-target"></div>
          </div>
          <p class="scan-hint">{{ t('admin.tournaments.scanHint') }}</p>

          <div class="scan-input-row">
            <input
              v-model="scanCodeInput"
              type="text"
              :placeholder="t('admin.tournaments.scanCodePlaceholder')"
              class="scan-code-field font-mono"
              @keyup.enter="handleScanCode"
            />
            <AuraButton variant="primary" @click="handleScanCode">
              {{ t('admin.tournaments.confirmScanBtn') }}
            </AuraButton>
          </div>

          <div v-if="scannedAthlete" class="qr-scan-feedback-box">
            <span class="alert-pill">✓ {{ t('admin.tournaments.scanSuccessAlert') }}</span>
            <strong
              >{{ resolveLocalizedText(scannedAthlete.name) }} ({{
                scannedAthlete.club || t('admin.tournaments.freeAgentClub')
              }})</strong
            >
            <p class="font-mono">
              {{
                t('admin.tournaments.passCodeLabel', {
                  code: scannedAthlete.qrCode || scannedAthlete.id,
                })
              }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Digital Pass Modal -->
    <div
      v-if="showPassModal && selectedPassAthlete"
      class="modal-backdrop"
      @click.self="showPassModal = false"
    >
      <div class="modal-card modal-pass-card">
        <div class="modal-header">
          <div class="title-with-icon">
            <span class="material-symbols-outlined card-title-icon">badge</span>
            <h3 class="modal-title">{{ t('admin.tournaments.digitalPassModalTitle') }}</h3>
          </div>
          <button type="button" class="modal-close-btn" @click="showPassModal = false">✕</button>
        </div>
        <div class="modal-body pass-body">
          <div class="pass-badge-visual">
            <div class="pass-top-glow"></div>

            <div class="pass-header-strip">
              <div class="pass-org-info">
                <span class="pass-org-name font-mono">AURA CIRCUIT 2026</span>
                <span class="pass-org-sub">OFFICIAL TOURNAMENT PASS</span>
              </div>
              <span class="pass-type-badge font-mono">ATHLETE</span>
            </div>

            <div class="pass-badge-content">
              <div class="pass-avatar-wrap">
                <img
                  v-if="selectedPassAthlete.avatar"
                  :src="selectedPassAthlete.avatar"
                  :alt="selectedPassAthlete.name"
                  class="pass-avatar-img"
                />
                <div v-else class="pass-avatar-placeholder">
                  {{ selectedPassAthlete.name.charAt(0) }}
                </div>
              </div>

              <div class="pass-name-section">
                <h3 class="pass-ath-name">{{ resolveLocalizedText(selectedPassAthlete.name) }}</h3>
                <div class="pass-tags-row">
                  <span v-if="selectedPassAthlete.seed" class="pass-seed-tag">
                    Hạt giống #{{ selectedPassAthlete.seed }}
                  </span>
                  <span class="pass-category-tag">
                    {{ selectedPassAthlete.category || 'Đơn Nam Nâng Cao' }}
                  </span>
                </div>
                <div class="pass-club-text">
                  <span class="material-symbols-outlined icon-14">corporate_fare</span>
                  <span>{{
                    selectedPassAthlete.club || t('admin.tournaments.freeAgentClub')
                  }}</span>
                </div>
              </div>

              <div class="pass-qr-card">
                <svg class="pass-qr-svg" viewBox="0 0 120 120" width="100" height="100">
                  <rect width="120" height="120" fill="#ffffff" rx="8" />
                  <rect x="10" y="10" width="30" height="30" fill="#0f172a" rx="4" />
                  <rect x="16" y="16" width="18" height="18" fill="#ffffff" rx="2" />
                  <rect x="21" y="21" width="8" height="8" fill="#0f172a" rx="1" />
                  <rect x="80" y="10" width="30" height="30" fill="#0f172a" rx="4" />
                  <rect x="86" y="16" width="18" height="18" fill="#ffffff" rx="2" />
                  <rect x="91" y="21" width="8" height="8" fill="#0f172a" rx="1" />
                  <rect x="10" y="80" width="30" height="30" fill="#0f172a" rx="4" />
                  <rect x="16" y="86" width="18" height="18" fill="#ffffff" rx="2" />
                  <rect x="21" y="91" width="8" height="8" fill="#0f172a" rx="1" />
                  <rect x="48" y="14" width="6" height="6" fill="#0f172a" />
                  <rect x="58" y="14" width="6" height="6" fill="#0f172a" />
                  <rect x="68" y="14" width="6" height="6" fill="#0f172a" />
                  <rect x="48" y="24" width="6" height="6" fill="#0f172a" />
                  <rect x="68" y="24" width="6" height="6" fill="#0f172a" />
                  <rect x="48" y="34" width="6" height="6" fill="#0f172a" />
                  <rect x="58" y="34" width="6" height="6" fill="#0f172a" />
                  <rect x="14" y="48" width="6" height="6" fill="#0f172a" />
                  <rect x="24" y="48" width="6" height="6" fill="#0f172a" />
                  <rect x="34" y="48" width="6" height="6" fill="#0f172a" />
                  <rect x="48" y="48" width="8" height="8" fill="#ec4899" rx="2" />
                  <rect x="64" y="48" width="6" height="6" fill="#0f172a" />
                  <rect x="78" y="48" width="6" height="6" fill="#0f172a" />
                  <rect x="92" y="48" width="6" height="6" fill="#0f172a" />
                  <rect x="102" y="48" width="6" height="6" fill="#0f172a" />
                  <rect x="14" y="58" width="6" height="6" fill="#0f172a" />
                  <rect x="34" y="58" width="6" height="6" fill="#0f172a" />
                  <rect x="58" y="58" width="6" height="6" fill="#0f172a" />
                  <rect x="78" y="58" width="6" height="6" fill="#0f172a" />
                  <rect x="98" y="58" width="6" height="6" fill="#0f172a" />
                  <rect x="14" y="68" width="6" height="6" fill="#0f172a" />
                  <rect x="24" y="68" width="6" height="6" fill="#0f172a" />
                  <rect x="48" y="68" width="6" height="6" fill="#0f172a" />
                  <rect x="68" y="68" width="6" height="6" fill="#0f172a" />
                  <rect x="88" y="68" width="6" height="6" fill="#0f172a" />
                  <rect x="48" y="80" width="6" height="6" fill="#0f172a" />
                  <rect x="60" y="80" width="6" height="6" fill="#0f172a" />
                  <rect x="80" y="80" width="6" height="6" fill="#0f172a" />
                  <rect x="94" y="80" width="6" height="6" fill="#0f172a" />
                  <rect x="104" y="80" width="6" height="6" fill="#0f172a" />
                  <rect x="48" y="92" width="6" height="6" fill="#0f172a" />
                  <rect x="68" y="92" width="6" height="6" fill="#0f172a" />
                  <rect x="80" y="92" width="6" height="6" fill="#0f172a" />
                  <rect x="100" y="92" width="6" height="6" fill="#0f172a" />
                  <rect x="48" y="104" width="6" height="6" fill="#0f172a" />
                  <rect x="58" y="104" width="6" height="6" fill="#0f172a" />
                  <rect x="78" y="104" width="6" height="6" fill="#0f172a" />
                  <rect x="90" y="104" width="6" height="6" fill="#0f172a" />
                  <rect x="104" y="104" width="6" height="6" fill="#0f172a" />
                </svg>
                <span class="pass-qr-code font-mono">{{
                  selectedPassAthlete.qrCode || `ATH-${selectedPassAthlete.id}`
                }}</span>
              </div>

              <div
                class="pass-status-pill"
                :class="{ 'is-checked': selectedPassAthlete.checkedIn }"
              >
                <span class="material-symbols-outlined icon-14">
                  {{ selectedPassAthlete.checkedIn ? 'verified' : 'pending_actions' }}
                </span>
                <span>{{
                  selectedPassAthlete.checkedIn
                    ? t('admin.tournaments.checkedInBadge')
                    : t('admin.tournaments.pendingAthletes')
                }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.checkin-panel-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 16px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 24px;
  color: var(--pink-dark, #db2777);
}

.checkin-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.panel-headline {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.panel-sub {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.btn-scan-qr {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}

.checkin-metrics-banner {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.gauge-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 80px;
}

.radial-svg-gauge {
  width: 60px;
  height: 60px;
}

.circle-bg {
  fill: none;
  stroke: var(--outline);
  stroke-width: 3.8;
}

.circle {
  fill: none;
  stroke: #10b981;
  stroke-width: 3.8;
  stroke-linecap: round;
  transform: rotate(-90deg);
  transform-origin: 50% 50%;
}

.percentage {
  fill: var(--ink);
  font-size: 9px;
  text-anchor: middle;
  font-weight: 700;
}

.gauge-label {
  font-size: 10px;
  color: var(--muted);
  margin-top: 4px;
}

.metric-counters-grid {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.cnt-card {
  display: flex;
  flex-direction: column;
  padding: 10px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.cnt-label {
  font-size: 11px;
  color: var(--muted);
}

.cnt-num {
  font-size: 18px;
}

.text-green {
  color: #10b981;
}
.text-yellow {
  color: #f59e0b;
}

.checkin-filter-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-pills-bar {
  display: flex;
  gap: 6px;
}

.filter-pill-item {
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.filter-pill-item.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.search-field-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  min-width: 260px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.search-field-wrap:focus-within {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15);
}

.search-icon {
  font-size: 18px;
  color: var(--muted);
  transition: color 0.2s;
  flex-shrink: 0;
}

.search-field-wrap:focus-within .search-icon {
  color: var(--pink-dark, #db2777);
}

.search-input-field {
  flex: 1;
  width: 100%;
  border: none;
  background: transparent;
  color: var(--ink);
  font-size: 13px;
  outline: none;
  padding: 0;
}

.search-input-field::placeholder {
  color: var(--muted);
  opacity: 0.8;
}

.search-clear-btn {
  background: transparent;
  border: none;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted);
  cursor: pointer;
  border-radius: 50%;
  transition: all 0.15s;
}

.search-clear-btn:hover {
  color: var(--ink);
}

.athletes-feed-container {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 480px;
  overflow-y: auto;
}

.athlete-feed-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.athlete-feed-card.is-checked-in {
  border-color: #10b981;
  background: rgba(16, 185, 129, 0.03);
}

.ath-avatar-wrap {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  display: flex;
  align-items: center;
  justify-content: center;
}

.ath-avatar-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ath-initials {
  font-weight: 700;
  font-size: 14px;
}

.ath-info-col {
  flex: 1;
}

.ath-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.ath-name {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
}

.ath-seed-tag {
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
}

.ath-category-tag {
  font-size: 11px;
  color: var(--muted);
}

.ath-meta-details {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 12px;
  color: var(--muted);
}

.meta-club,
.meta-phone,
.meta-time {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.ath-actions-col {
  display: flex;
  gap: 8px;
}

.quick-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.btn-round-checkin {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 14px;
  border-radius: 8px;
  border: 0;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.06));
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-round-checkin.btn-checked {
  background: #10b981;
  color: #fff;
}

.alert-pill {
  display: inline-block;
  background: #10b981;
  color: #fff;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.camera-preview-box {
  position: relative;
  width: 100%;
  height: 200px;
  background: #111;
  border-radius: 12px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.laser-scanline {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: #ef4444;
  box-shadow: 0 0 8px #ef4444;
  animation: scan 2s infinite ease-in-out;
}

.scanner-frame-target {
  width: 120px;
  height: 120px;
  border: 2px dashed #fff;
  border-radius: 8px;
}

.scan-hint {
  text-align: center;
  font-size: 12px;
  color: var(--muted);
  margin-top: 10px;
}

.scan-input-row {
  display: flex;
  gap: 8px;
  margin-top: 10px;
  width: 100%;
}

.scan-code-field {
  flex: 1;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.btn-scan-submit {
  padding: 8px 14px;
  border-radius: 8px;
  border: none;
  background: var(--primary);
  color: #fff;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
}

.qr-scan-feedback-box {
  margin-top: 12px;
  padding: 12px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid #10b981;
}

.modal-pass-card {
  max-width: 400px;
  background: var(--surface, #1e1e24);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.12));
  border-radius: 20px;
  overflow: hidden;
}

.pass-body {
  padding: 16px 20px 24px;
  display: flex;
  justify-content: center;
}

.pass-badge-visual {
  position: relative;
  width: 100%;
  background: linear-gradient(145deg, #18181b 0%, #09090b 100%);
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 18px;
  overflow: hidden;
  box-shadow:
    0 16px 36px rgba(0, 0, 0, 0.5),
    0 0 20px rgba(219, 39, 119, 0.12);
}

.pass-top-glow {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #db2777, #8b5cf6, #3b82f6);
}

.pass-header-strip {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px dashed rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.02);
}

.pass-org-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pass-org-name {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 1.5px;
  color: #f472b6;
}

.pass-org-sub {
  font-size: 9px;
  letter-spacing: 0.5px;
  color: #94a3b8;
  font-weight: 600;
}

.pass-type-badge {
  background: rgba(219, 39, 119, 0.2);
  border: 1px solid #db2777;
  color: #f472b6;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1px;
}

.pass-badge-content {
  padding: 20px 18px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 14px;
}

.pass-avatar-wrap {
  width: 76px;
  height: 76px;
  border-radius: 50%;
  border: 3px solid #db2777;
  box-shadow: 0 0 16px rgba(219, 39, 119, 0.35);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #27272a;
  flex-shrink: 0;
}

.pass-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pass-avatar-placeholder {
  font-size: 28px;
  font-weight: 800;
  color: #fff;
}

.pass-name-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  width: 100%;
}

.pass-ath-name {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: #ffffff;
  line-height: 1.3;
}

.pass-tags-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.pass-seed-tag {
  background: rgba(219, 39, 119, 0.2);
  color: #f472b6;
  border: 1px solid rgba(219, 39, 119, 0.4);
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}

.pass-category-tag {
  background: rgba(255, 255, 255, 0.08);
  color: #e2e8f0;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 6px;
}

.pass-club-text {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #94a3b8;
  font-weight: 500;
  margin-top: 2px;
}

.pass-qr-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #ffffff;
  border-radius: 14px;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
}

.pass-qr-svg {
  display: block;
}

.pass-qr-code {
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 2px;
  color: #0f172a;
}

.pass-status-pill {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.4);
}

.pass-status-pill.is-checked {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  border-color: rgba(16, 185, 129, 0.4);
}

.icon-16 {
  font-size: 16px;
}
.icon-14 {
  font-size: 14px;
}

@keyframes scan {
  0%,
  100% {
    top: 10%;
  }
  50% {
    top: 85%;
  }
}
</style>
