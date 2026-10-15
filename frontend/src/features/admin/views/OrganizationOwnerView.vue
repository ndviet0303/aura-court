<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useOrganizationOwner } from '../composables/useOrganizationOwner'

import OrgHeroHeader from '../components/org/OrgHeroHeader.vue'
import OrgKpiBar from '../components/org/OrgKpiBar.vue'
import OrgProfileTab from '../components/org/OrgProfileTab.vue'
import OrgVenuesTab from '../components/org/OrgVenuesTab.vue'
import OrgMembersTab from '../components/org/OrgMembersTab.vue'
import OrgPoliciesTab from '../components/org/OrgPoliciesTab.vue'
import OrgSkeleton from '../components/org/OrgSkeleton.vue'

const { t } = useI18n()

const {
  loading,
  orgSwitchLoading,
  saving,
  notice,
  error,
  activeTab,
  organizationsList,
  currentOrg,
  brandColorOptions,
  timezoneOptions,
  currencyOptions,
  totalVenuesCount,
  totalCourtsCount,
  totalMembersCount,
  ownersCount,
  staffCount,
  loadOrgData,
  switchOrganization,
  saveProfile,
  savePolicies,
} = useOrganizationOwner()
</script>

<template>
  <div class="organization-owner-view">
    <!-- Top Header Bar -->
    <header class="view-header">
      <div class="header-titles">
        <div class="title-row">
          <span class="material-symbols-outlined header-icon">corporate_fare</span>
          <h1 class="page-title">{{ t('admin.org.pageTitle') }}</h1>
        </div>
        <p class="page-subtitle">{{ t('admin.org.pageSubtitle') }}</p>
      </div>

      <div class="header-actions">
        <AuraButton
          variant="secondary"
          :disabled="loading || orgSwitchLoading"
          @click="loadOrgData(currentOrg.id)"
        >
          <span
            class="material-symbols-outlined icon-18"
            :class="{ 'spin-icon': loading || orgSwitchLoading }"
          >
            refresh
          </span>
          <span>{{ t('admin.org.refreshBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill alert-success">
      <span class="material-symbols-outlined icon-16">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="alert-pill alert-error">
      <span class="material-symbols-outlined icon-16">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- Skeleton Loading state -->
    <OrgSkeleton v-if="loading" />

    <!-- Loaded Organization View -->
    <div v-else class="org-content-container">
      <!-- Organization Hero Header -->
      <OrgHeroHeader
        :org="currentOrg"
        :organizations-list="organizationsList"
        :active-tab="activeTab"
        @update:active-tab="activeTab = $event"
        @switch-org="switchOrganization"
      />

      <!-- KPI Summary Bar -->
      <OrgKpiBar
        :total-venues="totalVenuesCount"
        :total-courts="totalCourtsCount"
        :total-members="totalMembersCount"
        :owners-count="ownersCount"
        :staff-count="staffCount"
        :auto-approve="currentOrg.auto_approve_booking ?? true"
        :resale-fee="currentOrg.resale_fee_percent ?? 5"
        :loading="orgSwitchLoading"
      />

      <!-- Main Tab Content -->
      <main class="org-content-area">
        <!-- Profile Tab -->
        <OrgProfileTab
          v-if="activeTab === 'profile'"
          :org="currentOrg"
          :brand-colors="brandColorOptions"
          :timezone-options="timezoneOptions"
          :currency-options="currencyOptions"
          :saving="saving"
          @save="saveProfile"
        />

        <!-- Venues Tab -->
        <OrgVenuesTab
          v-else-if="activeTab === 'venues'"
          :venues="currentOrg.venues || []"
          :loading="orgSwitchLoading"
        />

        <!-- Members Tab -->
        <OrgMembersTab
          v-else-if="activeTab === 'members'"
          :members="currentOrg.members || []"
          :loading="orgSwitchLoading"
        />

        <!-- Policies Tab -->
        <OrgPoliciesTab
          v-else-if="activeTab === 'policies'"
          :org="currentOrg"
          :saving="saving"
          @save="savePolicies"
        />
      </main>
    </div>
  </div>
</template>

<style scoped>
.organization-owner-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.view-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.title-row {
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
  letter-spacing: -0.02em;
  color: var(--ink);
}

.page-subtitle {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted);
}

.alert-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  font-size: 13.5px;
  font-weight: 600;
  animation: slide-in 0.25s ease forwards;
}

.alert-success {
  background: rgba(16, 185, 129, 0.15);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.alert-error {
  background: rgba(239, 68, 68, 0.15);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.org-content-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.org-content-area {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}

@keyframes slide-in {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
