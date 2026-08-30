<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  loading?: boolean
  socialLoading?: string | null
}>()

const emit = defineEmits<{
  (e: 'google'): void
  (e: 'facebook'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="social-login-section">
    <!-- Social Login Divider -->
    <div class="social-divider">
      <span class="divider-line"></span>
      <span class="divider-text">{{ t('auth.orDivider') }}</span>
      <span class="divider-line"></span>
    </div>

    <!-- Social Login Buttons -->
    <div class="social-buttons-grid">
      <button
        type="button"
        class="social-auth-btn google-btn"
        :disabled="loading || !!socialLoading"
        @click="emit('google')"
      >
        <span v-if="socialLoading === 'google'" class="social-spinner"></span>
        <svg v-else class="social-svg-icon" viewBox="0 0 24 24" width="19" height="19">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
        </svg>
        <span>{{ t('auth.loginWithGoogle') }}</span>
      </button>

      <button
        type="button"
        class="social-auth-btn facebook-btn"
        :disabled="loading || !!socialLoading"
        @click="emit('facebook')"
      >
        <span v-if="socialLoading === 'facebook'" class="social-spinner"></span>
        <svg v-else class="social-svg-icon" viewBox="0 0 24 24" width="19" height="19" fill="#1877F2">
          <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
        </svg>
        <span>{{ t('auth.loginWithFacebook') }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.social-login-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 18px;
}

.social-divider {
  display: flex;
  align-items: center;
  gap: 12px;
}

.divider-line {
  flex: 1;
  height: 1px;
  background: var(--border, #ebdccb);
}

html.dark .divider-line {
  background: #382f35;
}

.divider-text {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--muted, #6e6668);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}

.social-buttons-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.social-auth-btn {
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #ebdccb);
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

html.dark .social-auth-btn {
  background: #251f23;
  border-color: #382f35;
  color: #f5f2eb;
}

.social-auth-btn:hover:not(:disabled) {
  background: var(--surface-subtle, rgba(201, 161, 90, 0.06));
  border-color: #6B2D3F;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(107, 45, 63, 0.12);
}

html.dark .social-auth-btn:hover:not(:disabled) {
  background: #2d2429;
  border-color: #dfba72;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.social-auth-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.social-svg-icon {
  flex-shrink: 0;
}

.social-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid var(--border, #ebdccb);
  border-radius: 50%;
  border-top-color: #6B2D3F;
  animation: spin 0.7s linear infinite;
}

html.dark .social-spinner {
  border-color: #382f35;
  border-top-color: #dfba72;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 480px) {
  .social-buttons-grid {
    grid-template-columns: 1fr;
  }
}
</style>
