<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  name: string
  email: string
  password: string
  passwordConfirmation: string
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'update:name', val: string): void
  (e: 'update:email', val: string): void
  (e: 'update:password', val: string): void
  (e: 'update:passwordConfirmation', val: string): void
  (e: 'submit'): void
}>()

const { t } = useI18n()
const showPassword = ref(false)
const showConfirmation = ref(false)
const acceptTerms = ref(true)

// Password strength calculation
const passwordStrength = computed(() => {
  const pwd = props.password
  if (!pwd) return { score: 0, label: '', percent: 0, colorClass: '' }
  let score = 0
  if (pwd.length >= 8) score++
  if (pwd.length >= 10) score++
  if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++
  if (/[0-9]/.test(pwd) || /[^A-Za-z0-9]/.test(pwd)) score++

  if (score <= 1) {
    return { score: 1, label: t('auth.passwordStrength.weak'), percent: 33, colorClass: 'strength-weak' }
  } else if (score <= 3) {
    return { score: 2, label: t('auth.passwordStrength.medium'), percent: 66, colorClass: 'strength-medium' }
  } else {
    return { score: 3, label: t('auth.passwordStrength.strong'), percent: 100, colorClass: 'strength-strong' }
  }
})

// Passwords match validation
const passwordsMatch = computed(() => {
  if (!props.passwordConfirmation) return null
  return props.password === props.passwordConfirmation
})
</script>

<template>
  <form class="auth-form" @submit.prevent="emit('submit')" novalidate>
    <!-- Full Name -->
    <div class="input-group">
      <label for="reg-name" class="input-label">
        {{ t('auth.name') }} <span class="required">*</span>
      </label>
      <div class="input-wrapper">
        <span class="material-symbols-outlined input-icon">person</span>
        <input
          id="reg-name"
          name="name"
          :value="name"
          type="text"
          class="custom-input"
          :placeholder="t('auth.namePlaceholder')"
          autocomplete="name"
          required
          @input="emit('update:name', ($event.target as HTMLInputElement).value)"
        />
      </div>
    </div>

    <!-- Email Address -->
    <div class="input-group">
      <label for="reg-email" class="input-label">
        {{ t('auth.email') }} <span class="required">*</span>
      </label>
      <div class="input-wrapper">
        <span class="material-symbols-outlined input-icon">alternate_email</span>
        <input
          id="reg-email"
          name="email"
          :value="email"
          type="email"
          class="custom-input"
          :placeholder="t('auth.emailPlaceholder')"
          autocomplete="email"
          required
          @input="emit('update:email', ($event.target as HTMLInputElement).value)"
        />
      </div>
    </div>

    <!-- Password -->
    <div class="input-group">
      <label for="reg-password" class="input-label">
        {{ t('auth.password') }} <span class="required">*</span>
      </label>
      <div class="input-wrapper">
        <span class="material-symbols-outlined input-icon">lock</span>
        <input
          id="reg-password"
          name="password"
          :value="password"
          :type="showPassword ? 'text' : 'password'"
          class="custom-input with-toggle"
          :placeholder="t('auth.passwordPlaceholder')"
          autocomplete="new-password"
          minlength="8"
          required
          @input="emit('update:password', ($event.target as HTMLInputElement).value)"
        />
        <button
          type="button"
          class="pwd-toggle-btn"
          @click="showPassword = !showPassword"
          :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
        >
          <span class="material-symbols-outlined">
            {{ showPassword ? 'visibility_off' : 'visibility' }}
          </span>
        </button>
      </div>

      <!-- Password strength meter -->
      <div v-if="password" class="password-strength-bar">
        <div class="strength-info">
          <span class="strength-title">{{ t('auth.passwordStrength.title') }}</span>
          <span class="strength-status" :class="passwordStrength.colorClass">
            {{ passwordStrength.label }}
          </span>
        </div>
        <div class="strength-progress-track">
          <div
            class="strength-progress-fill"
            :class="passwordStrength.colorClass"
            :style="{ width: `${passwordStrength.percent}%` }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Confirm Password -->
    <div class="input-group">
      <label for="reg-password-confirmation" class="input-label">
        {{ t('auth.passwordConfirm') }} <span class="required">*</span>
      </label>
      <div class="input-wrapper">
        <span class="material-symbols-outlined input-icon">lock_reset</span>
        <input
          id="reg-password-confirmation"
          name="password_confirmation"
          :value="passwordConfirmation"
          :type="showConfirmation ? 'text' : 'password'"
          class="custom-input with-toggle"
          :placeholder="t('auth.passwordConfirmPlaceholder')"
          autocomplete="new-password"
          minlength="8"
          required
          @input="emit('update:passwordConfirmation', ($event.target as HTMLInputElement).value)"
        />
        <button
          type="button"
          class="pwd-toggle-btn"
          @click="showConfirmation = !showConfirmation"
          :aria-label="showConfirmation ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
        >
          <span class="material-symbols-outlined">
            {{ showConfirmation ? 'visibility_off' : 'visibility' }}
          </span>
        </button>
      </div>

      <!-- Password match status indicator -->
      <div v-if="passwordConfirmation" class="match-status" :class="passwordsMatch ? 'match-ok' : 'match-error'">
        <span class="material-symbols-outlined match-icon">
          {{ passwordsMatch ? 'check_circle' : 'cancel' }}
        </span>
        <span>{{ passwordsMatch ? t('auth.passwordMatch') : t('auth.passwordsDoNotMatch') }}</span>
      </div>
    </div>

    <!-- Terms Agreement -->
    <div class="terms-agreement">
      <label class="custom-checkbox-label">
        <input type="checkbox" v-model="acceptTerms" class="custom-checkbox" required />
        <span class="checkbox-text">
          {{ t('auth.termsNotice') }}
          <RouterLink to="/terms" target="_blank" class="terms-link">{{ t('auth.termsOfService') }}</RouterLink>
          {{ t('auth.and') }}
          <RouterLink to="/privacy" target="_blank" class="terms-link">{{ t('auth.privacyPolicy') }}</RouterLink>.
        </span>
      </label>
    </div>

    <!-- Submit Button -->
    <button type="submit" class="auth-submit-btn" :disabled="loading || !acceptTerms">
      <span v-if="loading" class="spinner" aria-hidden="true"></span>
      <span v-else class="material-symbols-outlined submit-icon">person_add</span>
      <span>{{ loading ? 'Đang tạo tài khoản...' : t('auth.registerBtn') }}</span>
    </button>
  </form>
</template>

<style scoped>
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
  color: var(--ink, #0f172a);
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
  color: var(--muted, #64748b);
  font-size: 20px;
  pointer-events: none;
  transition: color 0.2s ease;
}

.custom-input {
  width: 100%;
  height: 48px;
  padding: 0 16px 0 46px;
  font-size: 14px;
  color: var(--ink, #0f172a);
  background: var(--input-bg, var(--surface, #ffffff));
  border: 1px solid var(--border, var(--outline, #e2e8f0));
  border-radius: 12px;
  outline: none;
  transition: all 0.2s ease;
}

.custom-input.with-toggle {
  padding-right: 46px;
}

.custom-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15);
}

html.dark .custom-input:focus {
  border-color: #f8b4c4;
  box-shadow: 0 0 0 3px rgba(248, 180, 196, 0.15);
}

.custom-input:focus ~ .input-icon {
  color: var(--pink-dark, #db2777);
}

html.dark .custom-input:focus ~ .input-icon {
  color: #f8b4c4;
}

.pwd-toggle-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  color: var(--muted, #64748b);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  cursor: pointer;
  border-radius: 6px;
  transition: color 0.15s ease;
}

.pwd-toggle-btn:hover {
  color: var(--ink, #0f172a);
}

.pwd-toggle-btn .material-symbols-outlined {
  font-size: 20px;
}

/* Password Strength Bar */
.password-strength-bar {
  margin-top: 6px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.strength-info {
  display: flex;
  justify-content: space-between;
  font-size: 11.5px;
}

.strength-title {
  color: var(--muted, #64748b);
}

.strength-status {
  font-weight: 700;
}

.strength-status.strength-weak {
  color: #ba1a1a;
}

.strength-status.strength-medium {
  color: #c48800;
}

.strength-status.strength-strong {
  color: #0e6b50;
}

.strength-progress-track {
  height: 4px;
  background: var(--soft, #e2e8f0);
  border-radius: 999px;
  overflow: hidden;
}

.strength-progress-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.3s ease, background-color 0.3s ease;
}

.strength-progress-fill.strength-weak {
  background-color: #ba1a1a;
}

.strength-progress-fill.strength-medium {
  background-color: #f59e0b;
}

.strength-progress-fill.strength-strong {
  background-color: #10b981;
}

/* Password match indicator */
.match-status {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  margin-top: 4px;
}

.match-status.match-ok {
  color: #0e6b50;
}

.match-status.match-error {
  color: #ba1a1a;
}

.match-icon {
  font-size: 16px;
}

.terms-agreement {
  display: flex;
  align-items: center;
}

.custom-checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  cursor: pointer;
  user-select: none;
}

.custom-checkbox {
  margin-top: 3px;
  width: 16px;
  height: 16px;
  accent-color: var(--pink-dark, #db2777);
  cursor: pointer;
}

.checkbox-text {
  font-size: 12.5px;
  color: var(--muted, #64748b);
  line-height: 1.4;
}

.terms-link {
  color: var(--pink-dark, #db2777);
  text-decoration: underline;
}

html.dark .terms-link {
  color: #f8b4c4;
}

.auth-submit-btn {
  margin-top: 6px;
  height: 48px;
  background: var(--pink-dark, #db2777);
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
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 14px rgba(219, 39, 119, 0.28);
}

.auth-submit-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(219, 39, 119, 0.38);
  filter: brightness(1.06);
}

.auth-submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.submit-icon {
  font-size: 19px;
  transition: transform 0.2s ease;
}

.auth-submit-btn:hover:not(:disabled) .submit-icon {
  transform: translateX(3px);
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
</style>
