<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'

defineProps<{
  email: string
  password: string
  rememberMe: boolean
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'update:email', val: string): void
  (e: 'update:password', val: string): void
  (e: 'update:rememberMe', val: boolean): void
  (e: 'submit'): void
}>()

const { t } = useI18n()
const showPassword = ref(false)
</script>

<template>
  <form class="auth-form" @submit.prevent="emit('submit')" novalidate>
    <!-- Email Address -->
    <div class="input-group">
      <label for="login-email" class="input-label">
        {{ t('auth.email') }} <span class="required">*</span>
      </label>
      <div class="input-wrapper">
        <span class="material-symbols-outlined input-icon">alternate_email</span>
        <input
          id="login-email"
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
      <div class="input-label-row">
        <label for="login-password" class="input-label">
          {{ t('auth.password') }} <span class="required">*</span>
        </label>
        <RouterLink to="/forgot-password" class="forgot-link">
          {{ t('auth.forgotPassword') }}
        </RouterLink>
      </div>
      <div class="input-wrapper">
        <span class="material-symbols-outlined input-icon">lock</span>
        <input
          id="login-password"
          name="password"
          :value="password"
          :type="showPassword ? 'text' : 'password'"
          class="custom-input with-toggle"
          :placeholder="t('auth.passwordPlaceholder')"
          autocomplete="current-password"
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
    </div>

    <!-- Remember me checkbox -->
    <div class="form-options">
      <label class="custom-checkbox-label">
        <input
          type="checkbox"
          :checked="rememberMe"
          class="custom-checkbox"
          @change="emit('update:rememberMe', ($event.target as HTMLInputElement).checked)"
        />
        <span class="checkbox-text">{{ t('auth.rememberMe') }}</span>
      </label>
    </div>

    <!-- Submit Button -->
    <button type="submit" class="auth-submit-btn" :disabled="loading">
      <span v-if="loading" class="spinner" aria-hidden="true"></span>
      <span v-else class="material-symbols-outlined submit-icon">arrow_forward</span>
      <span>{{ loading ? t('auth.loggingIn') : t('auth.loginBtn') }}</span>
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

.input-label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.input-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #0f172a);
}

.required {
  color: #ba1a1a;
}

.forgot-link {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--pink-dark, #db2777);
  text-decoration: none;
  transition: opacity 0.15s ease;
}

html.dark .forgot-link {
  color: #f8b4c4;
}

.forgot-link:hover {
  text-decoration: underline;
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

.form-options {
  display: flex;
  align-items: center;
}

.custom-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  user-select: none;
}

.custom-checkbox {
  width: 16px;
  height: 16px;
  accent-color: var(--pink-dark, #db2777);
  cursor: pointer;
}

.checkbox-text {
  font-size: 13px;
  color: var(--muted, #64748b);
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
