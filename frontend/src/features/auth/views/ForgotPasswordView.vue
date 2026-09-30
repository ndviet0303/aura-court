<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const email = ref('')
const error = ref('')
const notice = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  error.value = ''
  notice.value = ''
  try {
    await auth.forgotPassword(email.value.trim())
    notice.value = t('auth.sendResetLink')
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
            <span class="material-symbols-outlined">lock_reset</span>
          </div>
        </div>

        <div class="form-header text-center">
          <h1 class="form-title">{{ t('auth.forgotPassword') }}</h1>
          <p class="form-subtitle">{{ t('auth.loginSubtitle') }}</p>
        </div>

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

        <Transition name="fade-slide">
          <div v-if="notice" class="alert-banner success-banner" role="status">
            <span class="material-symbols-outlined alert-icon">check_circle</span>
            <span class="alert-text">{{ notice }}</span>
          </div>
        </Transition>

        <form class="auth-form" @submit.prevent="submit" novalidate>
          <div class="input-group">
            <label for="forgot-email" class="input-label">
              {{ t('auth.email') }} <span class="required">*</span>
            </label>
            <div class="input-wrapper">
              <span class="material-symbols-outlined input-icon">alternate_email</span>
              <input
                id="forgot-email"
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

          <button
            type="submit"
            class="auth-submit-btn"
            :disabled="loading"
          >
            <span v-if="loading" class="spinner" aria-hidden="true"></span>
            <span v-else class="material-symbols-outlined submit-icon">send</span>
            <span>
              {{ loading ? t('common.submitting') : t('auth.sendResetLink') }}
            </span>
          </button>
        </form>

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
  background: radial-gradient(circle, rgba(201, 161, 90, 0.22) 0%, rgba(107, 45, 63, 0.12) 50%, transparent 70%);
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}

html.dark .aura-glow-bg {
  background: radial-gradient(circle, rgba(107, 45, 63, 0.4) 0%, rgba(223, 186, 114, 0.15) 50%, transparent 70%);
}

.auth-container {
  position: relative;
  z-index: 1;
  max-width: 480px;
  margin: 0 auto;
}

.auth-card-single {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 40px 36px;
  box-shadow: 0 20px 50px -15px rgba(107, 45, 63, 0.08);
}

html.dark .auth-card-single {
  background: #1e181c;
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
  background: #fbe8ec;
  color: #6B2D3F;
  border: 1px solid rgba(107, 45, 63, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.12);
}

html.dark .icon-circle {
  background: rgba(107, 45, 63, 0.35);
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.25);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
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
  color: var(--ink);
  margin: 0 0 6px;
  letter-spacing: -0.02em;
}

.form-subtitle {
  font-size: 13.5px;
  color: var(--muted);
  margin: 0;
  line-height: 1.5;
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

.success-banner {
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #0e6b50;
}

html.dark .success-banner {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
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
  0%, 100% { transform: translateX(0); }
  20%, 60% { transform: translateX(-5px); }
  40%, 80% { transform: translateX(5px); }
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.input-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
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
  color: var(--muted);
  font-size: 20px;
  pointer-events: none;
}

.custom-input {
  width: 100%;
  height: 48px;
  padding: 0 16px 0 46px;
  font-size: 14px;
  color: var(--ink);
  background: var(--input-bg, var(--surface));
  border: 1px solid var(--border);
  border-radius: 12px;
  outline: none;
  transition: all 0.2s ease;
}

html.dark .custom-input {
  background: #251f23;
  border-color: #3d343a;
  color: #ffffff;
}

.custom-input:focus {
  border-color: #6B2D3F;
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.15);
}

html.dark .custom-input:focus {
  border-color: #dfba72;
  box-shadow: 0 0 0 3px rgba(223, 186, 114, 0.2);
}

.auth-submit-btn {
  height: 48px;
  background: linear-gradient(135deg, #6B2D3F 0%, #52212F 100%);
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
  to { transform: rotate(360deg); }
}

.auth-card-footer {
  margin-top: 24px;
  text-align: center;
  padding-top: 20px;
  border-top: 1px solid var(--border);
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  font-weight: 600;
  color: #6B2D3F;
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
