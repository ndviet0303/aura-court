<script setup lang="ts">
import type { HostKyc, KycStatus } from '../../types/kyc'

const props = defineProps<{
  items: HostKyc[]
  activeTab: KycStatus | 'all'
  selectedId?: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: KycStatus | 'all'): void
  (e: 'select', item: HostKyc): void
}>()

const tabs: { key: KycStatus | 'all'; label: string; countSuffix?: string }[] = [
  { key: 'pending', label: 'Chờ duyệt' },
  { key: 'needs_supplement', label: 'Cần bổ sung' },
  { key: 'flagged', label: 'Nghi vấn' },
  { key: 'approved', label: 'Đã duyệt' },
]

function getProgressPercent(item: HostKyc): number {
  if (item.status === 'approved') return 100
  let count = 0
  if (item.idCardFrontUrl) count += 25
  if (item.businessLicenseUrl) count += 25
  if (item.napasMatchStatus === 'matched') count += 25
  if (item.facilityImages?.length || item.courtScale > 0) count += 25
  return count
}
</script>

<template>
  <div class="kyc-queue-column">
    <!-- Tab Filter -->
    <div class="queue-tabs">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === t.key }"
        @click="emit('update:activeTab', t.key)"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- Cards Scroll Area -->
    <div class="queue-card-list">
      <div v-if="loading" class="queue-loading">
        <span class="material-symbols-outlined spin">sync</span>
        <span>Đang tải danh sách hồ sơ...</span>
      </div>

      <div v-else-if="items.length === 0" class="queue-empty">
        <span class="material-symbols-outlined">task_alt</span>
        <p>Không có hồ sơ nào trong mục này.</p>
      </div>

      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        class="kyc-card"
        :class="{
          'is-active': selectedId === item.id,
          'is-flagged': item.status === 'flagged',
        }"
        @click="emit('select', item)"
      >
        <!-- Top row: Code & Status Badge -->
        <div class="card-header-row">
          <div class="card-meta">
            <span class="kyc-code">{{ item.kycCode }}</span>
            <span class="business-type-badge">{{ item.businessType === 'enterprise' ? 'Doanh nghiệp' : item.businessType === 'business_household' ? 'Hộ kinh doanh' : 'Cá nhân' }}</span>
          </div>
          <span
            class="status-pill"
            :class="{
              'pill-pending': item.status === 'pending',
              'pill-flagged': item.status === 'flagged',
              'pill-needs-supplement': item.status === 'needs_supplement',
              'pill-approved': item.status === 'approved',
            }"
          >
            <span v-if="item.status === 'pending'" class="pulse-dot"></span>
            {{ item.status === 'pending' ? 'Chờ duyệt' : item.status === 'flagged' ? 'Cảnh báo' : item.status === 'needs_supplement' ? 'Cần bổ sung' : 'Đã duyệt' }}
          </span>
        </div>

        <!-- Business Name -->
        <div class="card-name">{{ item.businessName }}</div>

        <!-- Mini document verification badges -->
        <div class="doc-badges-row">
          <span class="doc-badge success" title="CCCD người đại diện">
            <span class="material-symbols-outlined doc-icon">check</span> CCCD
          </span>
          <span
            class="doc-badge"
            :class="item.status === 'flagged' || item.id === 1 ? 'warning' : 'success'"
            title="Giấy phép ĐKKD"
          >
            <span class="material-symbols-outlined doc-icon">{{ item.status === 'flagged' || item.id === 1 ? 'warning' : 'check' }}</span> GPKD
          </span>
          <span class="doc-badge success" title="Xác thực NAPAS">
            <span class="material-symbols-outlined doc-icon">check</span> NAPAS
          </span>
          <span class="doc-badge success" title="Mặt bằng sân">
            <span class="material-symbols-outlined doc-icon">check</span> Mặt bằng
          </span>
        </div>

        <!-- Progress bar -->
        <div class="progress-container">
          <div class="progress-bar-bg">
            <div class="progress-fill" :style="{ width: `${getProgressPercent(item)}%` }"></div>
          </div>
          <span class="progress-text">{{ getProgressPercent(item) }}%</span>
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.kyc-queue-column {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.queue-tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid #e1e3e4;
  padding-bottom: 4px;
  overflow-x: auto;
}

.tab-btn {
  border: none;
  background: transparent;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #5d5f5f;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: #191c1d;
  background: #edeeef;
}

.tab-btn.active {
  color: #78555e;
  background: #ffd1dc;
}

.queue-card-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: calc(100vh - 270px);
  overflow-y: auto;
  padding-right: 4px;
}

.queue-loading,
.queue-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 16px;
  color: #817476;
  gap: 8px;
  text-align: center;
  font-size: 13px;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}

.kyc-card {
  border: 1px solid #e1e3e4;
  background: #ffffff;
  border-radius: 16px;
  padding: 14px 16px;
  text-align: left;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: all 0.2s ease;
  position: relative;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.kyc-card:hover {
  border-color: #817476;
  transform: translateY(-1px);
}

.kyc-card.is-active {
  border-color: #78555e;
  background: #fffbfa;
  box-shadow: 0 0 0 2px #ffd1dc, 0 4px 12px rgba(120, 85, 94, 0.08);
}

.kyc-card.is-flagged {
  border-color: #ffdad6;
  background: #fff8f7;
}

.card-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.card-meta {
  display: flex;
  align-items: center;
  gap: 6px;
}

.kyc-code {
  font-size: 11px;
  font-weight: 700;
  color: #78555e;
  letter-spacing: 0.04em;
}

.business-type-badge {
  font-size: 10px;
  color: #5d5f5f;
  background: #edeeef;
  padding: 2px 6px;
  border-radius: 4px;
}

.status-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.pill-pending {
  background: #fef3c7;
  color: #d97706;
}

.pill-flagged {
  background: #fee2e2;
  color: #dc2626;
}

.pill-needs-supplement {
  background: #e0f2fe;
  color: #0284c7;
}

.pill-approved {
  background: #d1fae5;
  color: #059669;
}

.pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #d97706;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(1.3); }
}

.card-name {
  font-size: 15px;
  font-weight: 700;
  color: #191c1d;
  line-height: 1.3;
}

.doc-badges-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 4px;
}

.doc-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 3px 4px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2px;
}

.doc-badge.success {
  background: #d1fae5;
  color: #059669;
}

.doc-badge.warning {
  background: #fef3c7;
  color: #d97706;
}

.doc-icon {
  font-size: 12px;
}

.progress-container {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 2px;
}

.progress-bar-bg {
  flex: 1;
  height: 5px;
  background: #e1e3e4;
  border-radius: 999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #78555e;
  border-radius: 999px;
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 10px;
  font-weight: 700;
  color: #78555e;
}
</style>
