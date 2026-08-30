<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { api, errorMessage } from '@/lib/api'

import AuthBrandPanel from '../components/AuthBrandPanel.vue'
import AuthTabs from '../components/AuthTabs.vue'
import LoginForm from '../components/LoginForm.vue'
import RegisterForm from '../components/RegisterForm.vue'
import SocialLoginButtons from '../components/SocialLoginButtons.vue'

interface Props {
  initialMode?: 'login' | 'register'
}

const props = withDefaults(defineProps<Props>(), {
  initialMode: 'login',
})

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

// Mode management: login vs register
const mode = ref<'login' | 'register'>('login')

// Form states
const loginEmail = ref('')
const loginPassword = ref('')
const rememberMe = ref(true)

const regName = ref('')
const regEmail = ref('')
const regPassword = ref('')
const regPasswordConfirmation = ref('')

// Feedback & Loading
const error = ref('')
const loading = ref(false)
const socialLoading = ref<string | null>(null)

onMounted(() => {
  if (route.query.mode === 'register' || route.path === '/register' || props.initialMode === 'register') {
    mode.value = 'register'
  } else {
    mode.value = 'login'
  }

  if (route.query.error) {
    if (route.query.msg) {
      error.value = `Đăng nhập thất bại: ${decodeURIComponent(String(route.query.msg))}`
    } else {
      error.value = 'Đăng nhập mạng xã hội không thành công. Vui lòng thử lại.'
    }
  }
})

watch(
  () => [route.path, route.query.mode],
  () => {
    if (route.query.mode === 'register' || route.path === '/register') {
      mode.value = 'register'
    } else if (route.query.mode === 'login' || route.path === '/login') {
      mode.value = 'login'
    }
  }
)

function setMode(newMode: 'login' | 'register') {
  mode.value = newMode
  error.value = ''
  const redirect = route.query.redirect
  if (newMode === 'register') {
    router.replace({ path: '/register', query: redirect ? { redirect } : undefined })
  } else {
    router.replace({ path: '/login', query: redirect ? { redirect } : undefined })
  }
}

function handlePostAuthRedirect(user: { onboarded?: boolean; role?: string } | null) {
  if (user && !user.onboarded) {
    router.push('/onboarding')
    return
  }
  if (user?.role === 'admin') {
    router.push('/admin/organization')
  } else {
    const redirectUrl = String(route.query.redirect || '/')
    router.push(redirectUrl)
  }
}

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    const user = await auth.login(loginEmail.value.trim(), loginPassword.value)
    handlePostAuthRedirect(user)
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('auth.invalidCredentials'))
  } finally {
    loading.value = false
  }
}

async function handleRegister() {
  if (regPassword.value !== regPasswordConfirmation.value) {
    error.value = t('auth.passwordsDoNotMatch')
    return
  }

  loading.value = true
  error.value = ''
  try {
    const user = await auth.register(
      regName.value.trim(),
      regEmail.value.trim(),
      regPassword.value,
      regPasswordConfirmation.value
    )
    handlePostAuthRedirect(user)
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('auth.invalidCredentials'))
  } finally {
    loading.value = false
  }
}

async function handleSocialLogin(provider: 'google' | 'facebook') {
  socialLoading.value = provider
  error.value = ''
  try {
    const res = await api.get<{ url?: string | null; demo_mode?: boolean }>(`/auth/${provider}/redirect`).catch(() => null)
    if (res?.url) {
      window.location.href = res.url
      return
    }

    const defaultEmail = provider === 'google' ? 'user.google@gmail.com' : 'user.facebook@facebook.com'
    const defaultName = provider === 'google' ? 'Google Athlete' : 'Facebook Athlete'

    const user = await auth.socialLogin(
      provider,
      defaultEmail,
      defaultName,
      provider + '_' + Date.now()
    )

    handlePostAuthRedirect(user)
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('auth.invalidCredentials'))
  } finally {
    socialLoading.value = null
  }
}
</script>

<template>
  <div class="auth-page-wrapper">
    <!-- Ambient aura background effect -->
    <div class="aura-glow-bg" aria-hidden="true"></div>

    <div class="container auth-container">
      <div class="auth-split-card">
        
        <!-- Left Side: Brand Showcase Panel -->
        <AuthBrandPanel />

        <!-- Right Side: Interactive Auth Form -->
        <div class="form-panel">
          <!-- Segmented Tab Switcher -->
          <AuthTabs :mode="mode" @update:mode="setMode" />

          <!-- Form Header -->
          <div class="form-header">
            <h1 class="form-title">
              {{ mode === 'login' ? t('auth.loginTitle') : t('auth.registerTitle') }}
            </h1>
            <p class="form-subtitle">
              {{ mode === 'login' ? t('auth.loginSubtitle') : t('auth.registerSubtitle') }}
            </p>
          </div>

          <!-- Error Alert Banner -->
          <Transition name="fade-slide">
            <div v-if="error" class="error-banner" role="alert">
              <span class="material-symbols-outlined error-icon">error</span>
              <span class="error-text">{{ error }}</span>
              <button type="button" class="error-close-btn" @click="error = ''" aria-label="Đóng">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
          </Transition>

          <!-- Login Form -->
          <LoginForm
            v-if="mode === 'login'"
            :email="loginEmail"
            :password="loginPassword"
            :remember-me="rememberMe"
            :loading="loading"
            @update:email="loginEmail = $event"
            @update:password="loginPassword = $event"
            @update:remember-me="rememberMe = $event"
            @submit="handleLogin"
          />

          <!-- Register Form -->
          <RegisterForm
            v-else
            :name="regName"
            :email="regEmail"
            :password="regPassword"
            :password-confirmation="regPasswordConfirmation"
            :loading="loading"
            @update:name="regName = $event"
            @update:email="regEmail = $event"
            @update:password="regPassword = $event"
            @update:password-confirmation="regPasswordConfirmation = $event"
            @submit="handleRegister"
          />

          <!-- Social Login Buttons -->
          <SocialLoginButtons
            :loading="loading"
            :social-loading="socialLoading"
            @google="handleSocialLogin('google')"
            @facebook="handleSocialLogin('facebook')"
          />

          <!-- Toggle Bottom Link -->
          <div class="auth-switch-footer">
            <p class="switch-text">
              <span>{{ mode === 'login' ? t('auth.dontHaveAccount') : t('auth.alreadyHaveAccount') }}</span>
              <button
                type="button"
                class="switch-action-btn"
                @click="setMode(mode === 'login' ? 'register' : 'login')"
              >
                {{ mode === 'login' ? t('auth.registerBtn') : t('auth.loginBtn') }}
              </button>
            </p>
          </div>

        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.auth-page-wrapper {
  position: relative;
  min-height: calc(100vh - 78px);
  padding: 40px 16px 80px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Background glowing ambient light */
.aura-glow-bg {
  position: absolute;
  top: 10%;
  left: 50%;
  transform: translateX(-50%);
  width: 900px;
  max-width: 90vw;
  height: 500px;
  background: radial-gradient(circle, rgba(255, 209, 220, 0.45) 0%, rgba(120, 85, 94, 0.08) 50%, transparent 70%);
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}

html.dark .aura-glow-bg {
  background: radial-gradient(circle, rgba(120, 85, 94, 0.4) 0%, rgba(255, 209, 220, 0.05) 50%, transparent 70%);
}

.auth-container {
  position: relative;
  z-index: 1;
  max-width: 1050px;
  width: 100%;
  margin: 0 auto;
}

/* Split Card Layout */
.auth-split-card {
  display: grid;
  grid-template-columns: 1fr 1.15fr;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, var(--outline, #e2e8f0));
  border-radius: 28px;
  overflow: hidden;
  box-shadow: 0 20px 50px -15px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.02);
  transition: all 0.3s ease;
}

html.dark .auth-split-card {
  box-shadow: 0 24px 60px -15px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
}

/* Form Panel (Right) */
.form-panel {
  padding: 40px 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  background: var(--surface, #ffffff);
}

/* Form Header */
.form-header {
  margin-bottom: 18px;
}

.form-title {
  font-size: 24px;
  font-weight: 800;
  color: var(--ink, #0f172a);
  letter-spacing: -0.02em;
  margin: 0 0 4px 0;
}

.form-subtitle {
  font-size: 13.5px;
  color: var(--muted, #64748b);
  margin: 0;
}

/* Error banner */
.error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #ba1a1a;
  padding: 10px 14px;
  border-radius: 12px;
  margin-bottom: 16px;
  font-size: 13px;
  animation: shake 0.4s ease;
}

html.dark .error-banner {
  background: rgba(186, 26, 26, 0.15);
  border-color: rgba(186, 26, 26, 0.35);
  color: #ffb4ab;
}

.error-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.error-text {
  flex: 1;
  font-weight: 500;
}

.error-close-btn {
  background: transparent;
  border: none;
  color: inherit;
  cursor: pointer;
  display: flex;
  padding: 2px;
  border-radius: 4px;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  20%, 60% { transform: translateX(-5px); }
  40%, 80% { transform: translateX(5px); }
}

/* Switcher Footer */
.auth-switch-footer {
  margin-top: 20px;
  text-align: center;
  padding-top: 18px;
  border-top: 1px solid var(--border, var(--outline, #e2e8f0));
}

.switch-text {
  font-size: 13px;
  color: var(--muted, #64748b);
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  flex-wrap: wrap;
}

.switch-action-btn {
  background: transparent;
  border: none;
  color: var(--pink-dark, #db2777);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  text-decoration: underline;
  padding: 0;
  transition: opacity 0.15s ease;
}

html.dark .switch-action-btn {
  color: #f8b4c4;
}

.switch-action-btn:hover {
  opacity: 0.8;
}

/* Animations */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.25s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* Responsive adjustments */
@media (max-width: 900px) {
  .auth-split-card {
    grid-template-columns: 1fr;
    max-width: 520px;
    margin: 0 auto;
    border-radius: 20px;
  }

  .form-panel {
    padding: 32px 24px;
  }
}

@media (max-width: 480px) {
  .auth-page-wrapper {
    padding: 20px 12px 60px;
  }

  .form-panel {
    padding: 24px 16px;
  }
}
</style>
