<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/stores/auth'
import { newPasswordError } from '@/core/utils/accountPassword'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref(String(route.query.email || ''))
const token = ref(String(route.query.token || ''))
const password = ref('')
const confirmation = ref('')
const showPassword = ref(false)
const showConfirmation = ref(false)
const error = ref('')
const loading = ref(false)
const showManualFields = ref(false)

const hasToken = computed(() => !!token.value.trim())
const hasEmail = computed(() => !!email.value.trim())
const isValidLink = computed(() => hasToken.value && hasEmail.value)

const passwordsMatch = computed(() => {
  if (!confirmation.value) return null
  return password.value === confirmation.value
})

async function submit() {
  if (!email.value.trim() || !token.value.trim()) {
    error.value = t('auth.invalidResetLink')
    return
  }

  const invalid = newPasswordError(password.value, confirmation.value)
  if (invalid) {
    error.value = t(invalid)
    return
  }

  loading.value = true
  error.value = ''
  try {
    await auth.resetPassword(
      email.value.trim(),
      token.value.trim(),
      password.value,
      confirmation.value,
    )
    router.push('/login')
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page-wrapper">
    <div class="aura-glow-bg" aria-hidden="true"></div>

    <div class="container auth-container">
      <div class="auth-card-single">
        <div class="card-icon-header">
          <div class="icon-circle">
            <span class="material-symbols-outlined">key</span>
          </div>
        </div>

        <div class="form-header text-center">
          <h1 class="form-title">{{ t('auth.resetPassword') }}</h1>
          <p class="form-subtitle">{{ t('auth.loginSubtitle') }}</p>
        </div>

        <!-- Invalid / Missing Token Warning -->
        <div v-if="!isValidLink && !showManualFields" class="missing-params-box">
          <span class="material-symbols-outlined warn-icon">link_off</span>
          <div class="missing-params-content">
            <h3>{{ t('auth.invalidResetLink') }}</h3>
            <p>{{ t('auth.invalidResetLinkDesc') }}</p>
          </div>
          <div class="missing-params-actions">
            <RouterLink to="/forgot-password" class="auth-submit-btn link-action-btn">
              <span class="material-symbols-outlined">mail</span>
              <span>{{ t('auth.requestNewLink') }}</span>
            </RouterLink>
            <button type="button" class="manual-toggle-btn" @click="showManualFields = true">
              {{ t('auth.enterTokenManually') }}
            </button>
          </div>
        </div>

        <template v-else>
          <!-- Feedback Alert Messages -->
          <Transition name="fade-slide">
            <div v-if="error" class="alert-banner error-banner" role="alert">
              <span class="material-symbols-outlined alert-icon">error</span>
              <span class="alert-text">{{ error }}</span>
              <button type="button" class="alert-close-btn" @click="error = ''">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
          </Transition>

          <!-- Verified Account Pill Badge -->
          <div v-if="hasEmail && !showManualFields" class="account-pill-card">
            <div class="pill-avatar">
              <span class="material-symbols-outlined">alternate_email</span>
            </div>
            <div class="pill-details">
              <span class="pill-label">{{ t('auth.resetPasswordFor') }}</span>
              <span class="pill-email">{{ email }}</span>
            </div>
            <div class="pill-badge" :title="t('auth.validAccountBadge')">
              <span class="material-symbols-outlined check-icon">verified_user</span>
            </div>
          </div>

          <form class="auth-form" @submit.prevent="submit" novalidate>
            <!-- Manual Fields (Only shown if toggled or URL lacks parameters) -->
            <template v-if="showManualFields">
              <div class="input-group">
                <label for="reset-email" class="input-label">
                  {{ t('auth.email') }} <span class="required">*</span>
                </label>
                <div class="input-wrapper">
                  <span class="material-symbols-outlined input-icon">alternate_email</span>
                  <input
                    id="reset-email"
                    name="email"
                    v-model="email"
                    type="email"
                    class="custom-input"
                    :placeholder="t('auth.emailPlaceholder')"
                    autocomplete="email"
                    required
                  />
                </div>
              </div>

              <div class="input-group">
                <label for="reset-token" class="input-label">
                  Token <span class="required">*</span>
                </label>
                <div class="input-wrapper">
                  <span class="material-symbols-outlined input-icon">pin</span>
                  <input
                    id="reset-token"
                    name="token"
                    v-model="token"
                    type="text"
                    class="custom-input"
                    :placeholder="t('auth.tokenPlaceholder')"
                    required
                  />
                </div>
              </div>
            </template>

            <!-- Password Inputs -->
            <div class="input-group">
              <label for="reset-password" class="input-label">
                {{ t('auth.newPassword') }} <span class="required">*</span>
              </label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">lock</span>
                <input
                  id="reset-password"
                  name="password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  class="custom-input with-toggle"
                  :placeholder="t('auth.passwordPlaceholder')"
                  autocomplete="new-password"
                  maxlength="64"
                  required
                />
                <button
                  type="button"
                  class="pwd-toggle-btn"
                  @click="showPassword = !showPassword"
                  :aria-label="showPassword ? t('auth.hidePassword') : t('auth.showPassword')"
                >
                  <span class="material-symbols-outlined">
                    {{ showPassword ? 'visibility_off' : 'visibility' }}
                  </span>
                </button>
              </div>
            </div>

            <div class="input-group">
              <label for="reset-password-confirm" class="input-label">
                {{ t('auth.newPasswordConfirm') }} <span class="required">*</span>
              </label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">lock_reset</span>
                <input
                  id="reset-password-confirm"
                  name="password_confirmation"
                  v-model="confirmation"
                  :type="showConfirmation ? 'text' : 'password'"
                  class="custom-input with-toggle"
                  :placeholder="t('auth.passwordConfirmPlaceholder')"
                  autocomplete="new-password"
                  maxlength="64"
                  required
                />
                <button
                  type="button"
                  class="pwd-toggle-btn"
                  @click="showConfirmation = !showConfirmation"
                  :aria-label="showConfirmation ? t('auth.hidePassword') : t('auth.showPassword')"
                >
                  <span class="material-symbols-outlined">
                    {{ showConfirmation ? 'visibility_off' : 'visibility' }}
                  </span>
                </button>
              </div>

              <div
                v-if="confirmation"
                class="match-status"
                :class="passwordsMatch ? 'match-ok' : 'match-error'"
              >
                <span class="material-symbols-outlined match-icon">
                  {{ passwordsMatch ? 'check_circle' : 'cancel' }}
                </span>
                <span>{{
                  passwordsMatch ? t('auth.passwordMatch') : t('auth.passwordsDoNotMatch')
                }}</span>
              </div>
            </div>

            <button type="submit" class="auth-submit-btn" :disabled="loading">
              <span v-if="loading" class="spinner" aria-hidden="true"></span>
              <span v-else class="material-symbols-outlined submit-icon">check</span>
              <span>
                {{ loading ? t('common.submitting') : t('auth.resetPassword') }}
              </span>
            </button>
          </form>
        </template>

        <div class="auth-card-footer">
          <RouterLink to="/login" class="back-link">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>{{ t('auth.alreadyHaveAccount') }}</span>
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.auth-page-wrapper {
  position: relative;
  min-height: calc(100vh - 78px);
  padding: 40px 0 80px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.aura-glow-bg {
  position: absolute;
  top: 15%;
  left: 50%;
  transform: translateX(-50%);
  width: 700px;
  max-width: 90vw;
  height: 400px;
  background: radial-gradient(
    circle,
    rgba(201, 161, 90, 0.25) 0%,
    rgba(107, 45, 63, 0.12) 50%,
    transparent 70%
  );
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}

html.dark .aura-glow-bg {
  background: radial-gradient(
    circle,
    rgba(223, 186, 114, 0.18) 0%,
    rgba(107, 45, 63, 0.25) 50%,
    transparent 70%
  );
}

.auth-container {
  position: relative;
  z-index: 1;
  max-width: 480px;
  margin: 0 auto;
  width: 100%;
  padding: 0 16px;
}

.auth-card-single {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e5dfd7);
  border-radius: 24px;
  padding: 40px 36px;
  box-shadow: 0 20px 50px -15px rgba(107, 45, 63, 0.08);
}

html.dark .auth-card-single {
  background: #1e191c;
  border-color: #382f35;
  box-shadow: 0 24px 60px -15px rgba(0, 0, 0, 0.6);
}

.card-icon-header {
  display: flex;
  justify-content: center;
  margin-bottom: 16px;
}

.icon-circle {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(223, 186, 114, 0.2) 0%, rgba(107, 45, 63, 0.12) 100%);
  color: #6b2d3f;
  border: 1px solid rgba(201, 161, 90, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.12);
}

html.dark .icon-circle {
  background: linear-gradient(135deg, rgba(223, 186, 114, 0.15) 0%, rgba(107, 45, 63, 0.3) 100%);
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.3);
}

.icon-circle .material-symbols-outlined {
  font-size: 28px;
}

.text-center {
  text-align: center;
}

.form-header {
  margin-bottom: 24px;
}

.form-title {
  font-size: 24px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0 0 6px;
  letter-spacing: -0.02em;
}

.form-subtitle {
  font-size: 13.5px;
  color: var(--muted, #6e6668);
  margin: 0;
  line-height: 1.5;
}

/* Verified Account Pill Card */
.account-pill-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: #faf7f3;
  border: 1px solid #ebdccb;
  border-radius: 14px;
  margin-bottom: 20px;
}

html.dark .account-pill-card {
  background: rgba(223, 186, 114, 0.06);
  border-color: rgba(223, 186, 114, 0.2);
}

.pill-avatar {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: #ffffff;
  border: 1px solid #ebdccb;
  color: #6b2d3f;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

html.dark .pill-avatar {
  background: #2a2226;
  border-color: #3d3137;
  color: #dfba72;
}

.pill-avatar .material-symbols-outlined {
  font-size: 20px;
}

.pill-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.pill-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted, #6e6668);
  line-height: 1.2;
}

.pill-email {
  font-size: 14px;
  font-weight: 750;
  color: var(--ink, #191c1d);
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pill-badge {
  color: #16a34a;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

html.dark .pill-badge {
  color: #4ade80;
}

.pill-badge .check-icon {
  font-size: 20px;
}

/* Missing Params Box */
.missing-params-box {
  background: #fff8f8;
  border: 1px solid #fecaca;
  border-radius: 16px;
  padding: 24px 20px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

html.dark .missing-params-box {
  background: rgba(186, 26, 26, 0.1);
  border-color: rgba(255, 180, 171, 0.25);
}

.warn-icon {
  font-size: 36px;
  color: #dc2626;
}

.missing-params-content h3 {
  font-size: 15.5px;
  font-weight: 750;
  color: var(--ink, #191c1d);
  margin: 0 0 6px;
}

.missing-params-content p {
  font-size: 13px;
  color: var(--muted, #6e6668);
  margin: 0;
  line-height: 1.55;
}

.missing-params-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  margin-top: 8px;
}

.link-action-btn {
  text-decoration: none;
  width: 100%;
}

.manual-toggle-btn {
  background: transparent;
  border: none;
  font-size: 12.5px;
  color: var(--muted, #6e6668);
  text-decoration: underline;
  cursor: pointer;
  padding: 4px;
}

.manual-toggle-btn:hover {
  color: #6b2d3f;
}

html.dark .manual-toggle-btn:hover {
  color: #dfba72;
}

/* Alert Banners */
.alert-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 12px;
  font-size: 13px;
  margin-bottom: 18px;
}

.error-banner {
  background: rgba(186, 26, 26, 0.08);
  border: 1px solid rgba(186, 26, 26, 0.25);
  color: #ba1a1a;
  animation: shake 0.4s ease;
}

html.dark .error-banner {
  background: rgba(255, 218, 214, 0.12);
  border-color: rgba(255, 218, 214, 0.25);
  color: #ffb4ab;
}

.alert-icon {
  font-size: 20px;
  flex-shrink: 0;
}

.alert-text {
  flex: 1;
}

.alert-close-btn {
  background: transparent;
  border: none;
  color: inherit;
  cursor: pointer;
  display: flex;
  padding: 2px;
}

@keyframes shake {
  0%,
  100% {
    transform: translateX(0);
  }
  20%,
  60% {
    transform: translateX(-5px);
  }
  40%,
  80% {
    transform: translateX(5px);
  }
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.input-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.required {
  color: #ba1a1a;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 14px;
  color: var(--muted, #6e6668);
  font-size: 20px;
  pointer-events: none;
}

.custom-input {
  width: 100%;
  height: 48px;
  padding: 0 16px 0 46px;
  font-size: 14px;
  color: var(--ink, #191c1d);
  background: var(--input-bg, var(--surface, #ffffff));
  border: 1px solid var(--border, #e5dfd7);
  border-radius: 12px;
  outline: none;
  transition: all 0.2s ease;
}

html.dark .custom-input {
  background: #251f23;
  border-color: #3d343a;
  color: #ffffff;
}

.custom-input.with-toggle {
  padding-right: 46px;
}

.custom-input:focus {
  border-color: #6b2d3f;
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.15);
}

html.dark .custom-input:focus {
  border-color: #dfba72;
  box-shadow: 0 0 0 3px rgba(223, 186, 114, 0.2);
}

.pwd-toggle-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  color: var(--muted, #6e6668);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  cursor: pointer;
  border-radius: 6px;
}

.pwd-toggle-btn .material-symbols-outlined {
  font-size: 20px;
}

.match-status {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  margin-top: 4px;
}

.match-status.match-ok {
  color: #16a34a;
}

html.dark .match-status.match-ok {
  color: #4ade80;
}

.match-status.match-error {
  color: #ba1a1a;
}

.match-icon {
  font-size: 16px;
}

.auth-submit-btn {
  margin-top: 6px;
  height: 48px;
  background: linear-gradient(135deg, #6b2d3f 0%, #52212f 100%);
  color: #ffffff;
  border: none;
  border-radius: 12px;
  font-size: 14.5px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 6px 20px rgba(107, 45, 63, 0.35);
}

.auth-submit-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(107, 45, 63, 0.45);
  filter: brightness(1.08);
}

.auth-submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.submit-icon {
  font-size: 18px;
}

.spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: #ffffff;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.auth-card-footer {
  margin-top: 24px;
  text-align: center;
  padding-top: 20px;
  border-top: 1px solid var(--border, #e5dfd7);
}

html.dark .auth-card-footer {
  border-color: #382f35;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  font-weight: 600;
  color: #6b2d3f;
  text-decoration: none;
  transition: opacity 0.15s ease;
}

html.dark .back-link {
  color: #dfba72;
}

.back-link:hover {
  text-decoration: underline;
}

.back-link .material-symbols-outlined {
  font-size: 18px;
}
</style>
