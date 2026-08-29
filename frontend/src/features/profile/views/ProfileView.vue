<script setup lang="ts">
import { useUserProfile } from '../composables/useUserProfile'

import ProfileAthleteHeader from '../components/ProfileAthleteHeader.vue'
import ProfileGeneralTab from '../components/ProfileGeneralTab.vue'
import ProfileSecurityTab from '../components/ProfileSecurityTab.vue'
import ProfileMembershipTab from '../components/ProfileMembershipTab.vue'
import ProfileShortcutsTab from '../components/ProfileShortcutsTab.vue'

const {
  auth,
  activeTab,
  notice,
  error,
  name,
  phone,
  playLevel,
  currentPassword,
  newPassword,
  confirmPassword,
  userInitials,
  saveProfile,
  updatePassword,
} = useUserProfile()
</script>

<template>
  <div class="profile-view-page">
    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Athlete Header & Tabs Navigation -->
    <ProfileAthleteHeader
      :user="auth.user"
      :initials="userInitials"
      :active-tab="activeTab"
      @update:active-tab="activeTab = $event"
    />

    <!-- Tab Panels -->
    <main class="profile-main-content">
      <ProfileGeneralTab
        v-if="activeTab === 'general'"
        :name="name"
        :phone="phone"
        :play-level="playLevel"
        @update:name="name = $event"
        @update:phone="phone = $event"
        @update:play-level="playLevel = $event"
        @save="saveProfile"
      />

      <ProfileSecurityTab
        v-else-if="activeTab === 'security'"
        :user="auth.user"
        :current-password="currentPassword"
        :new-password="newPassword"
        :confirm-password="confirmPassword"
        @update:current-password="currentPassword = $event"
        @update:new-password="newPassword = $event"
        @update:confirm-password="confirmPassword = $event"
        @save="updatePassword"
      />

      <ProfileMembershipTab
        v-else-if="activeTab === 'membership'"
        :user="auth.user"
      />

      <ProfileShortcutsTab
        v-else-if="activeTab === 'shortcuts'"
      />
    </main>
  </div>
</template>

<style scoped>
.profile-view-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1000px;
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

.profile-main-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
</style>
