<script setup lang="ts">
import type { User } from '@/core/types/common'
import { AuraButton } from '@/shared/components'
import { isAccountPassword } from '@/core/utils/accountPassword'

defineProps<{
  user: User | null
  currentPassword: string
  newPassword: string
  confirmPassword: string
  hasPasswordSet: boolean
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'update:currentPassword', val: string): void
  (e: 'update:newPassword', val: string): void
  (e: 'update:confirmPassword', val: string): void
  (e: 'save'): void
}>()
</script>

<template>
  <div class="profile-tab-card">
    <div class="section-title-row">
      <div class="title-with-icon">
        <span class="material-symbols-outlined title-icon">shield_lock</span>
        <div>
          <h2 class="section-heading">{{ $t('profile.tabSecurity') }}</h2>
          <p class="section-sub">{{ $t('profile.passwordSubtitle') }}</p>
        </div>
      </div>
    </div>

    <!-- Social Login Informational Notice -->
    <div v-if="user?.social_provider" class="social-notice-card">
      <span class="material-symbols-outlined social-notice-ico">info</span>
      <div class="social-notice-body">
        <strong
          >{{ $t('profile.socialLoginBadge') }} {{ user.social_provider.toUpperCase() }}</strong
        >
        <p>
          {{ $t('profile.setPasswordSubtitle', { provider: user.social_provider.toUpperCase() }) }}
        </p>
      </div>
    </div>

    <form class="security-edit-form" @submit.prevent="emit('save')">
      <template v-if="hasPasswordSet">
        <div class="form-group">
          <label class="form-label" for="sec-curr-pwd">
            {{ $t('profile.currentPassword') }} <span class="required">*</span>
          </label>
          <div class="input-with-icon">
            <span class="material-symbols-outlined input-icon">lock_open</span>
            <input
              id="sec-curr-pwd"
              :value="currentPassword"
              type="password"
              class="form-input"
              placeholder="••••••••"
              autocomplete="current-password"
              required
              @input="emit('update:currentPassword', ($event.target as HTMLInputElement).value)"
            />
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label" for="sec-new-pwd">
              {{ $t('profile.newPassword') }} <span class="required">*</span>
            </label>
            <div class="input-with-icon">
              <span class="material-symbols-outlined input-icon">lock</span>
              <input
                id="sec-new-pwd"
                :value="newPassword"
                type="password"
                class="form-input"
                :placeholder="$t('auth.passwordPlaceholder')"
                autocomplete="new-password"
                required
                maxlength="64"
                @input="emit('update:newPassword', ($event.target as HTMLInputElement).value)"
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="sec-conf-pwd">
              {{ $t('profile.confirmNewPassword') }} <span class="required">*</span>
            </label>
            <div class="input-with-icon">
              <span class="material-symbols-outlined input-icon">verified_user</span>
              <input
                id="sec-conf-pwd"
                :value="confirmPassword"
                type="password"
                class="form-input"
                :placeholder="$t('profile.confirmNewPassword')"
                autocomplete="new-password"
                required
                maxlength="64"
                @input="emit('update:confirmPassword', ($event.target as HTMLInputElement).value)"
              />
            </div>
          </div>
        </div>

        <!-- Security Checklist Box -->
        <div class="security-checklist">
          <span class="checklist-title font-mono">{{ $t('profile.passCriteriaTitle') }}</span>
          <ul class="checklist-items">
            <li :class="{ met: isAccountPassword(newPassword) }">
              <span class="material-symbols-outlined check-ico">
                {{ isAccountPassword(newPassword) ? 'check_circle' : 'radio_button_unchecked' }}
              </span>
              {{ $t('profile.passCriteria1') }}
            </li>
            <li :class="{ met: newPassword && newPassword === confirmPassword }">
              <span class="material-symbols-outlined check-ico">
                {{
                  newPassword && newPassword === confirmPassword
                    ? 'check_circle'
                    : 'radio_button_unchecked'
                }}
              </span>
              {{ $t('profile.passCriteria2') }}
            </li>
          </ul>
        </div>
      </template>

      <div class="form-actions-bar">
        <AuraButton
          type="submit"
          variant="primary"
          :loading="saving"
          :disabled="saving || (hasPasswordSet && !isAccountPassword(newPassword))"
        >
          <template #icon>
            <span class="material-symbols-outlined">lock_reset</span>
          </template>
          {{ hasPasswordSet ? $t('profile.passwordSection') : $t('profile.setPasswordBtn') }}
        </AuraButton>
      </div>
    </form>
  </div>
</template>

<style scoped>
.profile-tab-card {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 28px;
  border-radius: 24px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface, #ffffff);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
  overflow: hidden;
}

html.dark .profile-tab-card {
  background: var(--surface, #1e191d);
  border-color: var(--outline, #352c32);
}

.section-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.title-with-icon {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.title-icon {
  font-size: 26px;
  color: var(--primary, #6b2d3f);
  margin-top: 2px;
}

html.dark .title-icon {
  color: #dfba72;
}

.section-heading {
  margin: 0;
  font-size: 18px;
  font-weight: 750;
  color: var(--ink, #1c1618);
}

html.dark .section-heading {
  color: #faf7f3;
}

.section-sub {
  margin: 4px 0 0;
  font-size: 13.5px;
  color: var(--muted, #6e6668);
}

.social-notice-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 14px;
  background: rgba(14, 165, 233, 0.08);
  border: 1px solid rgba(14, 165, 233, 0.25);
  color: #0369a1;
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
}

html.dark .social-notice-card {
  background: rgba(14, 165, 233, 0.12);
  border-color: rgba(14, 165, 233, 0.35);
  color: #7dd3fc;
}

.social-notice-ico {
  font-size: 20px;
  margin-top: 2px;
  flex-shrink: 0;
}

.social-notice-body strong {
  display: block;
  font-size: 13.5px;
  margin-bottom: 2px;
}

.social-notice-body p {
  margin: 0;
  font-size: 13px;
  line-height: 1.4;
}

.security-edit-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.form-label {
  font-size: 13.5px;
  font-weight: 650;
  color: var(--ink, #1c1618);
}

html.dark .form-label {
  color: #e5dde2;
}

.required {
  color: #dc2626;
}

.input-with-icon {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 14px;
  font-size: 20px;
  color: var(--muted, #6e6668);
  pointer-events: none;
}

.form-input {
  width: 100%;
  padding: 11px 14px 11px 42px;
  border-radius: 12px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface-container, #faf7f3);
  color: var(--ink, #1c1618);
  font-size: 14px;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

html.dark .form-input {
  background: #141113;
  border-color: #3b3238;
  color: #faf7f3;
}

.form-input:focus {
  outline: none;
  border-color: var(--primary, #6b2d3f);
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.12);
}

html.dark .form-input:focus {
  border-color: #dfba72;
  box-shadow: 0 0 0 3px rgba(223, 186, 114, 0.15);
}

.security-checklist {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 16px;
  border-radius: 14px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline, #e2dad0);
}

html.dark .security-checklist {
  background: rgba(255, 255, 255, 0.03);
  border-color: #352c32;
}

.checklist-title {
  font-size: 11px;
  font-weight: 750;
  color: var(--muted, #6e6668);
  letter-spacing: 0.05em;
}

.checklist-items {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.checklist-items li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: var(--muted, #6e6668);
  transition: color 0.2s ease;
}

.check-ico {
  font-size: 16px;
}

.checklist-items li.met {
  color: #059669;
  font-weight: 600;
}

html.dark .checklist-items li.met {
  color: #34d399;
}

.form-actions-bar {
  display: flex;
  justify-content: flex-start;
  padding-top: 10px;
  border-top: 1px solid var(--outline, #e2dad0);
}

@media (max-width: 640px) {
  .profile-tab-card {
    padding: 20px 16px;
  }
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
