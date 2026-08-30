<script setup lang="ts">
import { ref } from 'vue'
import type { Participant } from '../../types/tournamentAdmin'

const props = defineProps<{
  athletes: Participant[]
  filterStatus: 'all' | 'pending' | 'checked_in' | 'no_show'
  searchQuery: string
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

const showQrScannerModal = ref(false)
const showPassModal = ref(false)
const selectedPassAthlete = ref<Participant | null>(null)
const scanCodeInput = ref('')
const scannedAthlete = ref<Participant | null>(null)

function openPassModal(ath: Participant) {
  selectedPassAthlete.value = ath
  showPassModal.value = true
}

function handleScanCode() {
  const code = scanCodeInput.value.trim().toLowerCase()
  if (!code) return
  const found = props.athletes.find(
    (p: Participant) => p.qrCode?.toLowerCase() === code || p.name.toLowerCase().includes(code)
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
      <div>
        <h3 class="panel-headline">📋 Điểm Danh Vận Động Viên</h3>
        <p class="panel-sub">Quản lý trạng thái có mặt, kiểm tra vợt và phát thẻ VĐV số.</p>
      </div>

      <div class="checkin-actions-bar">
        <button class="btn-scan-qr" @click="showQrScannerModal = true; scannedAthlete = null">
          📷 Quét mã QR
        </button>
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
          <text x="18" y="20.35" class="percentage">{{ metrics.percentage }}%</text>
        </svg>
        <span class="gauge-label">Tỷ Lệ Điểm Danh</span>
      </div>

      <div class="metric-counters-grid">
        <div class="cnt-card">
          <span class="cnt-label">Tổng VĐV</span>
          <strong class="cnt-num">{{ metrics.total }}</strong>
        </div>
        <div class="cnt-card text-green">
          <span class="cnt-label">Đã Có Mặt</span>
          <strong class="cnt-num">{{ metrics.checkedInCount }}</strong>
        </div>
        <div class="cnt-card text-yellow">
          <span class="cnt-label">Chưa Đến</span>
          <strong class="cnt-num">{{ metrics.pending_count }}</strong>
        </div>
      </div>
    </div>

    <!-- Search & Filter Tabs -->
    <div class="checkin-filter-toolbar">
      <div class="filter-pills-bar">
        <button
          class="filter-pill-item"
          :class="{ active: filterStatus === 'all' }"
          @click="emit('update:filterStatus', 'all')"
        >
          Tất cả ({{ metrics.total }})
        </button>
        <button
          class="filter-pill-item"
          :class="{ active: filterStatus === 'pending' }"
          @click="emit('update:filterStatus', 'pending')"
        >
          Chưa đến ({{ metrics.pending_count }})
        </button>
        <button
          class="filter-pill-item"
          :class="{ active: filterStatus === 'checked_in' }"
          @click="emit('update:filterStatus', 'checked_in')"
        >
          Đã điểm danh ({{ metrics.checkedInCount }})
        </button>
        <button
          class="filter-pill-item"
          :class="{ active: filterStatus === 'no_show' }"
          @click="emit('update:filterStatus', 'no_show')"
        >
          Vắng mặt (0)
        </button>
      </div>

      <div class="search-field-wrap">
        <input
          :value="searchQuery"
          type="text"
          class="search-input-field"
          placeholder="Tìm tên, CLB, mã thẻ..."
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
      </div>
    </div>

    <!-- Athletes Feed List -->
    <div class="athletes-feed-container">
      <div
        v-for="ath in athletes"
        :key="ath.id"
        class="athlete-feed-card"
        :class="{ 'is-checked-in': ath.checkedIn || ath.status === 'checked_in' }"
      >
        <div class="ath-avatar-wrap">
          <img v-if="ath.avatar" :src="ath.avatar" :alt="ath.name" />
          <div v-else class="ath-initials">{{ ath.initials || 'ATH' }}</div>
        </div>

        <div class="ath-info-col">
          <div class="ath-title-row">
            <h4 class="ath-name">{{ ath.name }}</h4>
            <span v-if="ath.seed" class="ath-seed-tag">Hạt giống #{{ ath.seed }}</span>
            <span class="ath-category-tag">{{ ath.category || 'Đơn Nam' }}</span>
          </div>

          <div class="ath-meta-details">
            <span class="meta-club">🏢 {{ ath.club || 'Tự do' }}</span>
            <span class="meta-phone">📞 {{ ath.phone || '0901234567' }}</span>
            <span v-if="ath.checkInTime" class="meta-time">⏱️ Có mặt lúc: {{ ath.checkInTime }}</span>
          </div>
        </div>

        <div class="ath-actions-col">
          <button class="quick-pill-btn" @click="openPassModal(ath)">
            🪪 Thẻ VĐV
          </button>
          <button
            class="btn-round-checkin"
            :class="{ 'btn-checked': ath.checkedIn }"
            @click="emit('toggleCheckIn', ath)"
          >
            {{ ath.checkedIn ? '✓ Đã Có Mặt' : 'Điểm Danh' }}
          </button>
        </div>
      </div>
    </div>

    <!-- QR Code Scanner Modal -->
    <div v-if="showQrScannerModal" class="modal-backdrop" @click.self="showQrScannerModal = false">
      <div class="modal-card modal-qr-scanner-card">
        <div class="modal-header">
          <h3 class="modal-title">📷 Quét Mã QR Thẻ VĐV</h3>
          <button class="modal-close-btn" @click="showQrScannerModal = false">✕</button>
        </div>
        <div class="modal-body scanner-body">
          <div class="camera-preview-box">
            <div class="laser-scanline"></div>
            <div class="scanner-frame-target"></div>
          </div>
          <p class="scan-hint">Nhập hoặc quét mã QR trên thẻ vận động viên:</p>
          
          <div class="scan-input-row">
            <input
              v-model="scanCodeInput"
              type="text"
              placeholder="Nhập mã thẻ VĐV hoặc tên..."
              class="scan-code-field"
              @keyup.enter="handleScanCode"
            />
            <button class="btn-scan-submit" @click="handleScanCode">Xác nhận</button>
          </div>

          <div v-if="scannedAthlete" class="qr-scan-feedback-box">
            <span class="alert-pill">✓ Đã điểm danh thành công</span>
            <strong>{{ scannedAthlete.name }} ({{ scannedAthlete.club || 'Tự do' }})</strong>
            <p>Mã thẻ: {{ scannedAthlete.qrCode || scannedAthlete.id }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Digital Pass Modal -->
    <div v-if="showPassModal" class="modal-backdrop" @click.self="showPassModal = false">
      <div class="modal-card modal-pass-card modal-sm">
        <div class="modal-header">
          <h3 class="modal-title">🪪 Thẻ Vận Động Viên Số</h3>
          <button class="modal-close-btn" @click="showPassModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="digital-badge-container">
            <div class="pass-header-brand">AURA COURT CONTROL</div>
            <div class="pass-ath-name">{{ selectedPassAthlete?.name || '' }}</div>
            <div class="pass-ath-club">{{ selectedPassAthlete?.club || '' }}</div>
            <div class="pass-qr-code">
              <span class="qr-label">{{ selectedPassAthlete?.qrCode || '' }}</span>
            </div>
            <div class="pass-status-pill">ACCREDITED ATHLETE 2026</div>
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
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.btn-scan-qr {
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

.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }

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

.search-input-field {
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  font-size: 12px;
  width: 220px;
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
  gap: 12px;
  font-size: 12px;
  color: var(--muted);
}

.ath-actions-col {
  display: flex;
  gap: 8px;
}

.quick-pill-btn {
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

.digital-badge-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px;
  border-radius: 16px;
  background: linear-gradient(135deg, #1e1b4b 0%, #311042 100%);
  color: #fff;
  text-align: center;
}

.pass-header-brand {
  font-size: 12px;
  letter-spacing: 2px;
  font-weight: 800;
  color: #f472b6;
}

.pass-ath-name {
  font-size: 18px;
  font-weight: 800;
  margin: 12px 0 4px;
}

.pass-ath-club {
  font-size: 12px;
  opacity: 0.8;
}

.pass-qr-code {
  margin: 16px 0;
  padding: 12px;
  background: #fff;
  color: #000;
  border-radius: 8px;
  font-weight: 700;
  letter-spacing: 2px;
}

.pass-status-pill {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1px;
  padding: 4px 10px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.2);
}

@keyframes scan {
  0%, 100% { top: 10%; }
  50% { top: 85%; }
}
</style>
