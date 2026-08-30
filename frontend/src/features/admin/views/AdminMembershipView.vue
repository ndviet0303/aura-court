<script setup lang="ts">
import { useAdminMembership } from '../composables/useAdminMembership'

import MembershipKpiBar from '../components/membership/MembershipKpiBar.vue'
import MembershipPlansGrid from '../components/membership/MembershipPlansGrid.vue'
import LoyaltyPointsConfigCard from '../components/membership/LoyaltyPointsConfigCard.vue'
import MembershipVersionTimeline from '../components/membership/MembershipVersionTimeline.vue'
import MembershipPlanModal from '../components/membership/MembershipPlanModal.vue'
import MultiplierRuleModal from '../components/membership/MultiplierRuleModal.vue'
import VersionManageModal from '../components/membership/VersionManageModal.vue'

const {
  notice,
  error,
  activePlans,
  loyaltyConfig,
  multiplierRules,
  versionHistory,
  stats,
  isPlanModalOpen,
  isRuleModalOpen,
  isVersionModalOpen,
  editingPlan,
  editingRule,
  targetPlanForVersion,
  openCreatePlanModal,
  openEditPlanModal,
  openManageVersionModal,
  savePlan,
  openCreateRuleModal,
  openEditRuleModal,
  toggleRuleActive,
  saveRule,
  deleteRule,
  updateLoyaltyRates,
} = useAdminMembership()
</script>

<template>
  <div class="admin-membership-view">
    <!-- Top Header -->
    <header class="membership-header-section">
      <div class="header-titles">
        <h1 class="page-title">Quản lý Gói hội viên & Điểm thưởng</h1>
        <p class="page-subtitle">Thiết kế các gói đặc quyền và cấu hình hệ thống tích điểm thông minh.</p>
      </div>

      <div class="header-actions-area">
        <div v-if="notice" class="alert-pill-notice">{{ notice }}</div>
        <div v-if="error" class="alert-pill-error">{{ error }}</div>

        <button
          type="button"
          class="btn-create-plan"
          @click="openCreatePlanModal"
        >
          <span class="material-symbols-outlined icon-18">add</span>
          <span>Tạo Gói Mới</span>
        </button>
      </div>
    </header>

    <!-- 4 KPI Stat Cards -->
    <MembershipKpiBar :stats="stats" />

    <!-- 12-Column Grid Layout matching Stitch Design System -->
    <div class="membership-content-grid">
      <!-- Left 8-Column Zone: Plans & Loyalty Settings -->
      <div class="main-column-stack">
        <MembershipPlansGrid
          :plans="activePlans"
          @edit-plan="openEditPlanModal"
          @manage-version="openManageVersionModal"
        />

        <LoyaltyPointsConfigCard
          :config="loyaltyConfig"
          :rules="multiplierRules"
          @open-create-rule="openCreateRuleModal"
          @edit-rule="openEditRuleModal"
          @toggle-rule="toggleRuleActive"
          @delete-rule="deleteRule"
          @update-rates="updateLoyaltyRates"
        />
      </div>

      <!-- Right 4-Column Zone: Version Audit Timeline -->
      <div class="sidebar-column-stack">
        <MembershipVersionTimeline :audits="versionHistory" />
      </div>
    </div>

    <!-- Modals -->
    <MembershipPlanModal
      :show="isPlanModalOpen"
      :plan="editingPlan"
      @close="isPlanModalOpen = false"
      @save="savePlan"
    />

    <MultiplierRuleModal
      :show="isRuleModalOpen"
      :rule="editingRule"
      @close="isRuleModalOpen = false"
      @save="saveRule"
    />

    <VersionManageModal
      :show="isVersionModalOpen"
      :plan="targetPlanForVersion"
      @close="isVersionModalOpen = false"
    />
  </div>
</template>

<style scoped>
.admin-membership-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1440px;
  margin: 0 auto;
  padding: 28px 24px 60px;
  color: var(--on-surface, #191c1d);
}

.membership-header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.page-title {
  margin: 0 0 6px;
  font-size: 28px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  letter-spacing: -0.02em;
}

:global(html.dark) .page-title {
  color: #ffffff;
}

.page-subtitle {
  margin: 0;
  font-size: 15px;
  color: var(--on-surface-variant, #4f4446);
}

.header-actions-area {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.alert-pill-notice {
  padding: 8px 16px;
  border-radius: 10px;
  background: #10b981;
  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 10px;
  background: #ef4444;
  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
}

.btn-create-plan {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 22px;
  border-radius: 12px;
  border: none;
  background: var(--primary, #6B2D3F);
  color: var(--on-primary, #ffffff);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.25);
  transition: all 0.2s ease;
}

.btn-create-plan:hover {
  background: var(--primary-hover, #562332);
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(107, 45, 63, 0.35);
}

:global(html.dark) .btn-create-plan {
  background: var(--primary, #E58E9F);
  color: var(--on-primary, #1c0a10);
  font-weight: 800;
  box-shadow: 0 4px 14px rgba(229, 142, 159, 0.3);
}

:global(html.dark) .btn-create-plan:hover {
  background: #f0a3b2;
}

.membership-content-grid {
  display: grid;
  grid-template-columns: 8fr 4fr;
  gap: 24px;
  align-items: start;
}

.main-column-stack {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.sidebar-column-stack {
  display: flex;
  flex-direction: column;
}

@media (max-width: 1100px) {
  .membership-content-grid {
    grid-template-columns: 1fr;
  }
}
</style>
