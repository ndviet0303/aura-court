<script setup lang="ts">
import { useOrganizationOwner } from '../composables/useOrganizationOwner'

import OrgHeroHeader from '../components/org/OrgHeroHeader.vue'
import OrgProfileTab from '../components/org/OrgProfileTab.vue'
import OrgVenuesTab from '../components/org/OrgVenuesTab.vue'
import OrgMembersTab from '../components/org/OrgMembersTab.vue'
import OrgPoliciesTab from '../components/org/OrgPoliciesTab.vue'

const {
  loading,
  saving,
  notice,
  error,
  activeTab,
  currentOrg,
  brandColorOptions,
  saveProfile,
  savePolicies,
  inviteMember,
} = useOrganizationOwner()
</script>

<template>
  <div class="organization-owner-view">
    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Organization Hero Header -->
    <OrgHeroHeader
      :org="currentOrg"
      :active-tab="activeTab"
      @update:active-tab="activeTab = $event"
    />

    <!-- Main Tab Content -->
    <main class="org-content-area">
      <OrgProfileTab
        v-if="activeTab === 'profile'"
        :org="currentOrg"
        :brand-colors="brandColorOptions"
        @save="saveProfile"
      />

      <OrgVenuesTab
        v-else-if="activeTab === 'venues'"
        :venues="currentOrg.venues || []"
      />

      <OrgMembersTab
        v-else-if="activeTab === 'members'"
        :members="currentOrg.members || []"
        @invite="inviteMember"
      />

      <OrgPoliciesTab
        v-else-if="activeTab === 'policies'"
        :org="currentOrg"
        @save="savePolicies"
      />
    </main>
  </div>
</template>

<style scoped>
.organization-owner-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.org-content-area {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
</style>
