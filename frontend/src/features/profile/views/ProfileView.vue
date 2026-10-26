<script setup lang="ts">
import { useUserProfile } from '../composables/useUserProfile'

import ProfileAthleteHeader from '../components/ProfileAthleteHeader.vue'
import ProfileAddEmailForm from '../components/ProfileAddEmailForm.vue'
import ProfileGeneralTab from '../components/ProfileGeneralTab.vue'
import ProfileSecurityTab from '../components/ProfileSecurityTab.vue'
import ProfileMembershipTab from '../components/ProfileMembershipTab.vue'
import ProfileShortcutsTab from '../components/ProfileShortcutsTab.vue'
import ProfileVenueCreditTab from '../components/ProfileVenueCreditTab.vue'

const {
  auth,
  activeTab,
  notice,
  error,
  savingProfile,
  savingPassword,
  resendingVerification,
  submittingEmail,
  membership,
  name,
  phone,
  playLevel,
  favoriteSports,
  avatarUrl,
  currentPassword,
  newPassword,
  confirmPassword,
  userInitials,
  displayAvatar,
  isEmailVerified,
  hasPasswordSet,
  needsEmail,
  saveProfile,
  updatePassword,
  resendVerification,
  submitEmail,
} = useUserProfile()
</script>

<template>
  <div class="profile-view-page">
    <!-- Feedback Toast Alerts -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill alert-pill-success" role="alert">
        <span class="material-symbols-outlined alert-ico">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="alert-pill alert-pill-error" role="alert">
        <span class="material-symbols-outlined alert-ico">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Athlete Hero Header & Navigation Tabs -->
    <ProfileAthleteHeader
      :user="auth.user"
      :initials="userInitials"
      :avatar-url="displayAvatar"
      :active-tab="activeTab"
      :is-email-verified="isEmailVerified"
      :needs-email="needsEmail"
      :resending-verification="resendingVerification"
      @update:active-tab="activeTab = $event"
      @resend-verification="resendVerification"
    />
    <ProfileAddEmailForm v-if="needsEmail" :saving="submittingEmail" @submit="submitEmail" />

    <!-- Main Content Tab Panels -->
    <main class="profile-main-content">
      <ProfileGeneralTab
        v-if="activeTab === 'general'"
        :name="name"
        :phone="phone"
        :play-level="playLevel"
        :favorite-sports="favoriteSports"
        :avatar-url="avatarUrl"
        :saving="savingProfile"
        @update:name="name = $event"
        @update:phone="phone = $event"
        @update:play-level="playLevel = $event"
        @update:favorite-sports="favoriteSports = $event"
        @update:avatar-url="avatarUrl = $event"
        @save="saveProfile"
      />

      <ProfileSecurityTab
        v-else-if="activeTab === 'security'"
        :user="auth.user"
        :current-password="currentPassword"
        :new-password="newPassword"
        :confirm-password="confirmPassword"
        :has-password-set="hasPasswordSet"
        :saving="savingPassword"
        @update:current-password="currentPassword = $event"
        @update:new-password="newPassword = $event"
        @update:confirm-password="confirmPassword = $event"
        @save="updatePassword"
      />

      <ProfileMembershipTab v-else-if="activeTab === 'membership'" :membership="membership" />

      <ProfileVenueCreditTab v-else-if="activeTab === 'credit'" />

      <ProfileShortcutsTab v-else-if="activeTab === 'shortcuts'" />
    </main>
  </div>
</template>

<style scoped>
.profile-view-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  width: 100%;
  max-width: 1040px;
  margin: 0 auto;
  padding: 32px 20px 64px;
  color: var(--ink, #1c1618);
  box-sizing: border-box;
  min-width: 0;
}

.alert-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: 12px;
  font-size: 13.5px;
  font-weight: 650;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.alert-pill-success {
  background: #059669;
  color: #ffffff;
}

.alert-pill-error {
  background: #dc2626;
  color: #ffffff;
}

.alert-ico {
  font-size: 20px;
  flex-shrink: 0;
}

.profile-main-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.fade-enter-active,
.fade-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
