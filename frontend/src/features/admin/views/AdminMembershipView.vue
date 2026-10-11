<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminMembership } from '../composables/useAdminMembership'

import MembershipKpiBar from '../components/membership/MembershipKpiBar.vue'
import MembershipPlansGrid from '../components/membership/MembershipPlansGrid.vue'
import LoyaltyPointsConfigCard from '../components/membership/LoyaltyPointsConfigCard.vue'
import MembershipVersionTimeline from '../components/membership/MembershipVersionTimeline.vue'
import MembershipPlanModal from '../components/membership/MembershipPlanModal.vue'
import MultiplierRuleModal from '../components/membership/MultiplierRuleModal.vue'
import VersionManageModal from '../components/membership/VersionManageModal.vue'

const { t } = useI18n()
const activeTab = ref<'plans' | 'loyalty' | 'history'>('plans')

const {
  loading,
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
  loadData,
  openCreatePlanModal,
  openEditPlanModal,
  openManageVersionModal,
  savePlan,
  archivePlan,
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
    <header class="membership-header">
      <div class="header-titles">
        <div class="title-with-badge">
          <div class="title-main">
            <span class="material-symbols-outlined header-icon">workspace_premium</span>
            <h1 class="page-title">{{ t('admin.membership.title') }}</h1>
          </div>
          <span class="live-pill"><span class="live-dot"></span>VIP & LOYALTY</span>
        </div>
        <p class="page-subtitle">{{ t('admin.membership.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <AuraButton variant="secondary" :disabled="loading" @click="loadData">
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': loading }"
            >refresh</span
          >
          <span>{{ t('admin.membership.refreshBtn') }}</span>
        </AuraButton>
        <AuraButton variant="primary" @click="openCreatePlanModal">
          <span class="material-symbols-outlined icon-18">add</span>
          <span>{{ t('admin.membership.createPlanBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <div v-if="notice" class="alert-pill-notice">
      <span class="material-symbols-outlined icon-18">verified</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="alert-pill-error">
      <span class="material-symbols-outlined icon-18">error</span>
      <span>{{ error }}</span>
    </div>

    <MembershipKpiBar :stats="stats" :loading="loading" />

    <nav class="membership-nav-tabs">
      <button
        type="button"
        class="nav-tab-btn"
        :class="{ active: activeTab === 'plans' }"
        @click="activeTab = 'plans'"
      >
        <span class="material-symbols-outlined icon-18">military_tech</span>
        <span>{{ t('admin.membership.tabPlans') }}</span>
        <span class="tab-badge">{{ activePlans.length }}</span>
      </button>
      <button
        type="button"
        class="nav-tab-btn"
        :class="{ active: activeTab === 'loyalty' }"
        @click="activeTab = 'loyalty'"
      >
        <span class="material-symbols-outlined icon-18">tune</span>
        <span>{{ t('admin.membership.tabLoyalty') }}</span>
        <span class="tab-badge">{{ multiplierRules.length }}</span>
      </button>
      <button
        type="button"
        class="nav-tab-btn"
        :class="{ active: activeTab === 'history' }"
        @click="activeTab = 'history'"
      >
        <span class="material-symbols-outlined icon-18">history</span>
        <span>{{ t('admin.membership.tabHistory') }}</span>
        <span class="tab-badge">{{ versionHistory.length }}</span>
      </button>
    </nav>

    <main class="tab-content-area">
      <section v-show="activeTab === 'plans'">
        <MembershipPlansGrid
          :plans="activePlans"
          :loading="loading"
          @edit-plan="openEditPlanModal"
          @manage-version="openManageVersionModal"
        />
      </section>
      <section v-show="activeTab === 'loyalty'">
        <LoyaltyPointsConfigCard
          :config="loyaltyConfig"
          :rules="multiplierRules"
          :loading="loading"
          @open-create-rule="openCreateRuleModal"
          @edit-rule="openEditRuleModal"
          @toggle-rule="toggleRuleActive"
          @delete-rule="deleteRule"
          @update-rates="updateLoyaltyRates"
        />
      </section>
      <section v-show="activeTab === 'history'">
        <MembershipVersionTimeline :audits="versionHistory" :loading="loading" />
      </section>
    </main>

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
      @archive-plan="archivePlan"
    />
  </div>
</template>

<style scoped>
.admin-membership-view {
  display: flex;
  flex-direction: column;
  gap: 22px;
  max-width: 1440px;
  margin: 0 auto;
  padding: 24px 24px 80px;
  color: var(--ink);
}
.membership-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
  padding-bottom: 4px;
}
.title-with-badge {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.title-main {
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
  letter-spacing: -0.02em;
}
.live-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 9999px;
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  letter-spacing: 0.05em;
  border: 1px solid rgba(219, 39, 119, 0.2);
}
.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 9999px;
  background: var(--pink-dark, #db2777);
  box-shadow: 0 0 8px rgba(219, 39, 119, 0.8);
}
.page-subtitle {
  margin: 6px 0 0;
  font-size: 13.5px;
  color: var(--muted);
}
.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.alert-pill-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 14px;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  font-size: 13px;
  font-weight: 600;
  border: 1px solid rgba(16, 185, 129, 0.25);
}
.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 14px;
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  font-size: 13px;
  font-weight: 600;
  border: 1px solid rgba(239, 68, 68, 0.25);
}
.membership-nav-tabs {
  display: flex;
  gap: 8px;
  padding: 6px;
  border-radius: 14px;
  background: var(--surface-container-high, #f1f2f4);
  border: 1px solid var(--outline);
  width: fit-content;
}
:global(html.dark) .membership-nav-tabs {
  background: #1e2129;
  border-color: #2e323e;
}
.nav-tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}
.nav-tab-btn:hover {
  color: var(--ink);
}
.nav-tab-btn.active {
  background: var(--surface);
  color: var(--pink-dark, #db2777);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  font-weight: 700;
}
:global(html.dark) .nav-tab-btn.active {
  background: #282c37;
  color: #f472b6;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}
.tab-badge {
  font-size: 11px;
  padding: 1px 7px;
  border-radius: 9999px;
  background: rgba(0, 0, 0, 0.06);
  color: inherit;
}
:global(html.dark) .tab-badge {
  background: rgba(255, 255, 255, 0.1);
}
.tab-content-area {
  margin-top: 4px;
}
.icon-18 {
  font-size: 18px;
}
.spin-icon {
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
@media (max-width: 768px) {
  .membership-nav-tabs {
    width: 100%;
    overflow-x: auto;
  }
}
</style>
