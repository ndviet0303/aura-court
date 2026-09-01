<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { AuraButton, SkeletonLoader } from '@/shared/components'
import { useGroupSplitBill } from '../composables/useGroupSplitBill'

import SplitBillSummaryCard from '../components/split/SplitBillSummaryCard.vue'
import SplitModeSelector from '../components/split/SplitModeSelector.vue'
import SplitItemizedManager from '../components/split/SplitItemizedManager.vue'
import SplitMembersList from '../components/split/SplitMembersList.vue'
import SplitQrModal from '../components/split/SplitQrModal.vue'
import SplitAddMemberModal from '../components/split/SplitAddMemberModal.vue'
import SplitAddServiceModal from '../components/split/SplitAddServiceModal.vue'
import SplitCoverShortfallModal from '../components/split/SplitCoverShortfallModal.vue'

const route = useRoute()
const codeParam = (route.params.code as string) || (route.query.code as string)

const {
  loading,
  processing,
  notice,
  error,
  showQrModal,
  selectedMemberForQr,
  showAddMemberModal,
  showAddServiceModal,
  showCoverShortfallModal,
  splitMode,
  bookingInfo,
  items,
  members,
  totalBill,
  totalPaid,
  remainingAmount,
  progressPercent,
  allocationDiff,
  isFullyPaid,
  unpaidMembersCount,
  calculateSplits,
  setCustomMemberAmount,
  setMemberMinutes,
  toggleItemAssignment,
  addMember,
  removeMember,
  addServiceItem,
  removeServiceItem,
  openQr,
  markAsPaid,
  coverShortfall,
  loadSplitGroup,
  copyShareLink,
} = useGroupSplitBill()

onMounted(() => {
  if (codeParam) {
    loadSplitGroup(codeParam)
  } else {
    calculateSplits()
  }
})
</script>

<template>
  <main class="group-split-bill-view">
    <!-- Top Navigation Bar / Breadcrumb -->
    <nav class="split-nav-bar" aria-label="Breadcrumb">
      <AuraButton
        variant="ghost"
        size="sm"
        icon="arrow_back"
        to="/booking"
      >
        <span>Lịch Sử Đặt Sân</span>
      </AuraButton>

      <div class="nav-actions">
        <AuraButton
          variant="outline"
          size="sm"
          icon="share"
          @click="copyShareLink"
        >
          <span>Chia Sẻ Link Bill</span>
        </AuraButton>

        <AuraButton
          v-if="!isFullyPaid && remainingAmount > 0"
          variant="gold"
          size="sm"
          icon="verified_user"
          @click="showCoverShortfallModal = true"
        >
          <span>Bảo Lãnh Tiền Thiếu</span>
        </AuraButton>
      </div>
    </nav>

    <!-- Header Section -->
    <header class="split-header">
      <div class="header-icon-box">
        <span class="material-symbols-outlined header-icon">payments</span>
      </div>
      <div class="header-titles">
        <h1 class="page-title">Chia Tiền Sân & Dịch Vụ Theo Nhóm (Aura Split Bill)</h1>
        <p class="page-sub">Tự động tính toán chia đều hóa đơn tiền sân, nước uống và tạo mã VietQR thanh toán riêng cho từng người.</p>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill-success">
        <span class="material-symbols-outlined alert-icon">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="alert-pill-error">
        <span class="material-symbols-outlined alert-icon">error_outline</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Loading Skeleton State -->
    <div v-if="loading" class="loading-skeleton-wrap">
      <SkeletonLoader type="rect" width="100%" height="220px" rounded="18px" />
      <SkeletonLoader type="rect" width="100%" height="100px" rounded="14px" />
      <SkeletonLoader type="rect" width="100%" height="300px" rounded="14px" />
    </div>

    <!-- Main Content -->
    <div v-else class="split-content-stack">
      <!-- Summary Card -->
      <SplitBillSummaryCard
        :booking="bookingInfo"
        :items="items"
        :total-bill="totalBill"
        :total-paid="totalPaid"
        :remaining-amount="remainingAmount"
        :progress-percent="progressPercent"
        :is-fully-paid="isFullyPaid"
        @add-service="showAddServiceModal = true"
        @remove-item="removeServiceItem"
      />

      <!-- Split Mode Selection -->
      <section class="mode-section">
        <div class="section-header">
          <span class="material-symbols-outlined sec-ico">calculate</span>
          <h3 class="section-title">Phương Thức Chia Tiền</h3>
        </div>
        <SplitModeSelector
          :mode="splitMode"
          @update:mode="splitMode = $event; calculateSplits()"
        />
      </section>

      <!-- Itemized Manager (Shown only in itemized mode) -->
      <SplitItemizedManager
        v-if="splitMode === 'itemized'"
        :items="items"
        :members="members"
        @toggle-assignment="toggleItemAssignment"
      />

      <!-- Members Split List -->
      <SplitMembersList
        :members="members"
        :mode="splitMode"
        :items="items"
        :allocation-diff="allocationDiff"
        @open-qr="openQr"
        @mark-paid="markAsPaid"
        @remove-member="removeMember"
        @update-custom-amount="setCustomMemberAmount"
        @update-minutes="setMemberMinutes"
        @add-member="showAddMemberModal = true"
      />
    </div>

    <!-- Modals -->
    <SplitQrModal
      :show="showQrModal"
      :member="selectedMemberForQr"
      :booking="bookingInfo"
      @close="showQrModal = false"
      @confirm-paid="markAsPaid"
    />

    <SplitAddMemberModal
      :show="showAddMemberModal"
      @close="showAddMemberModal = false"
      @submit="addMember"
    />

    <SplitAddServiceModal
      :show="showAddServiceModal"
      @close="showAddServiceModal = false"
      @submit="addServiceItem"
    />

    <SplitCoverShortfallModal
      :show="showCoverShortfallModal"
      :remaining-amount="remainingAmount"
      :unpaid-members="members.filter((m) => !m.paid)"
      :processing="processing"
      @close="showCoverShortfallModal = false"
      @confirm="coverShortfall"
    />
  </main>
</template>

<style scoped>
.group-split-bill-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1040px;
  margin: 0 auto;
  padding: 24px 20px 60px;
  color: var(--on-surface, #FAF7F3);
}

.split-nav-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.split-header {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: var(--surface-container, rgba(229, 142, 159, 0.12));
  border: 1px solid var(--border, var(--outline, #382f35));
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary, #E58E9F);
  flex-shrink: 0;
}

.header-icon {
  font-size: 26px;
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 850;
  color: var(--on-surface, #FAF7F3);
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted, #9e939a);
}

.alert-pill-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 12px;
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid rgba(16, 185, 129, 0.35);
  color: #10b981;
  font-size: 13.5px;
  font-weight: 650;
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 12px;
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.35);
  color: #ef4444;
  font-size: 13.5px;
  font-weight: 650;
}

.alert-icon {
  font-size: 18px;
}

.loading-skeleton-wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.split-content-stack {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.mode-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sec-ico {
  font-size: 20px;
  color: var(--primary, #E58E9F);
}

.section-title {
  margin: 0;
  font-size: 15px;
  font-weight: 750;
  color: var(--on-surface, #FAF7F3);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
