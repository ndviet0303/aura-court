<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useAdminMembership } from '../composables/useAdminMembership'

import MembershipKpiBar from '../components/membership/MembershipKpiBar.vue'
import MembershipPlansGrid from '../components/membership/MembershipPlansGrid.vue'
import LoyaltyPointsConfigCard from '../components/membership/LoyaltyPointsConfigCard.vue'
import MembershipVersionTimeline from '../components/membership/MembershipVersionTimeline.vue'
import MembershipPlanModal from '../components/membership/MembershipPlanModal.vue'
import MultiplierRuleModal from '../components/membership/MultiplierRuleModal.vue'
import VersionManageModal from '../components/membership/VersionManageModal.vue'

const { t } = useI18n()

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
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">card_membership</span>
          <h1 class="page-title">{{ t('admin.membership.title') }}</h1>
        </div>
        <p class="page-subtitle">{{ t('admin.membership.subtitle') }}</p>
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
          <span>{{ t('admin.membership.createPlanBtn') }}</span>
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
  color: var(--ink);
}

.membership-header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  color: var(--ink);
}

.page-subtitle {
  margin: 6px 0 0;
  font-size: 14px;
  color: var(--muted);
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
  padding: 10px 20px;
  border-radius: 12px;
  border: none;
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-create-plan:hover {
  opacity: 0.9;
  transform: translateY(-1px);
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

.icon-18 {
  font-size: 18px;
}

@media (max-width: 1100px) {
  .membership-content-grid {
    grid-template-columns: 1fr;
  }
}
</style>
