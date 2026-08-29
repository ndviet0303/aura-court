<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { api, errorMessage } from '@/lib/api'

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

// Form state
const name = ref('')
const email = ref('')
const password = ref('')
const confirmation = ref('')
const rememberMe = ref(true)
const acceptTerms = ref(true)

// Password show/hide states
const showPassword = ref(false)
const showConfirmation = ref(false)

// Feedback state
const error = ref('')
const loading = ref(false)
const socialLoading = ref<string | null>(null)

// Password strength calculation
const passwordStrength = computed(() => {
  const pwd = password.value
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
  if (!confirmation.value) return null
  return password.value === confirmation.value
})

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

async function submit() {
  if (mode.value === 'register') {
    if (password.value !== confirmation.value) {
      error.value = t('auth.passwordsDoNotMatch')
      return
    }
    if (!acceptTerms.value) {
      error.value = t('auth.termsNotice') + ' ' + t('auth.termsOfService')
      return
    }
  }

  loading.value = true
  error.value = ''
  try {
    if (mode.value === 'login') {
      await auth.login(email.value.trim(), password.value)
      handlePostAuthRedirect(auth.user)
    } else {
      const user = await auth.register(
        name.value.trim(),
        email.value.trim(),
        password.value,
        confirmation.value
      )
      handlePostAuthRedirect(user)
    }
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
        <div class="brand-panel">
          <div class="brand-content">
            <div class="brand-badge">
              <span class="material-symbols-outlined icon-glow">sports_tennis</span>
              <span>AURA ATHLETICS CLUB</span>
            </div>

            <h2 class="brand-title">
              {{ t('auth.clubAuraTitle') }}
            </h2>
            <p class="brand-subtitle">
              {{ t('auth.clubAuraSubtitle') }}
            </p>

            <!-- Key Features / Perks List -->
            <div class="perks-list">
              <div class="perk-item">
                <div class="perk-icon-box">
                  <span class="material-symbols-outlined">bolt</span>
                </div>
                <div class="perk-text">
                  <h4>{{ t('auth.perk1Title') }}</h4>
                  <p>{{ t('auth.perk1Desc') }}</p>
                </div>
              </div>

              <div class="perk-item">
                <div class="perk-icon-box">
                  <span class="material-symbols-outlined">qr_code_2</span>
                </div>
                <div class="perk-text">
                  <h4>{{ t('auth.perk2Title') }}</h4>
                  <p>{{ t('auth.perk2Desc') }}</p>
                </div>
              </div>

              <div class="perk-item">
                <div class="perk-icon-box">
                  <span class="material-symbols-outlined">verified</span>
                </div>
                <div class="perk-text">
                  <h4>{{ t('auth.perk3Title') }}</h4>
                  <p>{{ t('auth.perk3Desc') }}</p>
                </div>
              </div>
            </div>

            <!-- Testimonial Quote -->
            <div class="testimonial-box">
              <span class="quote-symbol">“</span>
              <p class="quote-text">{{ t('auth.testimonialText') }}</p>
              <div class="quote-author">{{ t('auth.testimonialAuthor') }}</div>
            </div>
          </div>
        </div>

        <!-- Right Side: Interactive Auth Form -->
        <div class="form-panel">
          <!-- Segmented Tab Switcher -->
          <div class="auth-tabs" role="tablist">
            <button
              type="button"
              class="auth-tab-btn"
              :class="{ active: mode === 'login' }"
              role="tab"
              :aria-selected="mode === 'login'"
              @click="setMode('login')"
            >
              <span class="material-symbols-outlined tab-icon">login</span>
              <span>{{ t('auth.loginTitle') }}</span>
            </button>
            <button
              type="button"
              class="auth-tab-btn"
              :class="{ active: mode === 'register' }"
              role="tab"
              :aria-selected="mode === 'register'"
              @click="setMode('register')"
            >
              <span class="material-symbols-outlined tab-icon">person_add</span>
              <span>{{ t('auth.registerTitle') }}</span>
            </button>
          </div>

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

          <!-- Auth Form -->
          <form class="auth-form" @submit.prevent="submit" novalidate>
            
            <!-- Full Name (Register only) -->
            <div v-if="mode === 'register'" class="input-group">
              <label for="name" class="input-label">
                {{ t('auth.name') }} <span class="required">*</span>
              </label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">person</span>
                <input
                  id="name"
                  name="name"
                  v-model="name"
                  type="text"
                  class="custom-input"
                  :placeholder="t('auth.namePlaceholder')"
                  autocomplete="name"
                  required
                />
              </div>
            </div>

            <!-- Email Address -->
            <div class="input-group">
              <label for="email" class="input-label">
                {{ t('auth.email') }} <span class="required">*</span>
              </label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">alternate_email</span>
                <input
                  id="email"
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

            <!-- Password -->
            <div class="input-group">
              <div class="input-label-row">
                <label for="password" class="input-label">
                  {{ t('auth.password') }} <span class="required">*</span>
                </label>
                <RouterLink
                  v-if="mode === 'login'"
                  to="/forgot-password"
                  class="forgot-link"
                >
                  {{ t('auth.forgotPassword') }}
                </RouterLink>
              </div>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">lock</span>
                <input
                  id="password"
                  name="password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  class="custom-input with-toggle"
                  :placeholder="t('auth.passwordPlaceholder')"
                  :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
                  minlength="8"
                  required
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

              <!-- Password strength meter (Register only) -->
              <div v-if="mode === 'register' && password" class="password-strength-bar">
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

            <!-- Confirm Password (Register only) -->
            <div v-if="mode === 'register'" class="input-group">
              <label for="password-confirmation" class="input-label">
                {{ t('auth.passwordConfirm') }} <span class="required">*</span>
              </label>
              <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">lock_reset</span>
                <input
                  id="password-confirmation"
                  name="password_confirmation"
                  v-model="confirmation"
                  :type="showConfirmation ? 'text' : 'password'"
                  class="custom-input with-toggle"
                  :placeholder="t('auth.passwordConfirmPlaceholder')"
                  autocomplete="new-password"
                  minlength="8"
                  required
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
              <div v-if="confirmation" class="match-status" :class="passwordsMatch ? 'match-ok' : 'match-error'">
                <span class="material-symbols-outlined match-icon">
                  {{ passwordsMatch ? 'check_circle' : 'cancel' }}
                </span>
                <span>{{ passwordsMatch ? t('auth.passwordMatch') : t('auth.passwordsDoNotMatch') }}</span>
              </div>
            </div>

            <!-- Remember me / Terms -->
            <div v-if="mode === 'login'" class="form-options">
              <label class="custom-checkbox-label">
                <input type="checkbox" v-model="rememberMe" class="custom-checkbox" />
                <span class="checkbox-text">{{ t('auth.rememberMe') }}</span>
              </label>
            </div>

            <div v-if="mode === 'register'" class="terms-agreement">
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
            <button
              type="submit"
              class="auth-submit-btn"
              :disabled="loading || !!socialLoading"
            >
              <span v-if="loading" class="spinner" aria-hidden="true"></span>
              <span v-else class="material-symbols-outlined submit-icon">
                {{ mode === 'login' ? 'arrow_forward' : 'person_add' }}
              </span>
              <span>
                {{ mode === 'login' ? t('auth.loginBtn') : t('auth.registerBtn') }}
              </span>
            </button>
          </form>

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
              @click="handleSocialLogin('google')"
            >
              <span v-if="socialLoading === 'google'" class="social-spinner"></span>
              <svg v-else class="social-svg-icon" viewBox="0 0 24 24" width="20" height="20">
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
              @click="handleSocialLogin('facebook')"
            >
              <span v-if="socialLoading === 'facebook'" class="social-spinner"></span>
              <svg v-else class="social-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="#1877F2">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              <span>{{ t('auth.loginWithFacebook') }}</span>
            </button>
          </div>

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
  padding: 40px 0 80px;
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
  margin: 0 auto;
}

/* Split Card Layout */
.auth-split-card {
  display: grid;
  grid-template-columns: 1fr 1.15fr;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 28px;
  overflow: hidden;
  box-shadow: 0 20px 50px -15px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.02);
  transition: all 0.3s ease;
}

html.dark .auth-split-card {
  box-shadow: 0 24px 60px -15px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
}

/* Brand Panel (Left) */
.brand-panel {
  background: linear-gradient(145deg, rgba(255, 209, 220, 0.35) 0%, rgba(248, 249, 250, 0.95) 40%, var(--surface-subtle) 100%);
  border-right: 1px solid var(--border);
  padding: 48px 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
}

html.dark .brand-panel {
  background: linear-gradient(145deg, rgba(120, 85, 94, 0.3) 0%, rgba(34, 36, 42, 0.95) 40%, var(--surface-subtle) 100%);
}

.brand-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(120, 85, 94, 0.12);
  color: var(--pink-dark);
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  margin-bottom: 24px;
}

html.dark .brand-badge {
  background: rgba(255, 209, 220, 0.15);
  color: #f8b4c4;
}

.icon-glow {
  font-size: 16px;
}

.brand-title {
  font-size: clamp(26px, 2.6vw, 34px);
  font-weight: 800;
  line-height: 1.18;
  letter-spacing: -0.03em;
  color: var(--ink);
  margin-bottom: 12px;
}

.brand-subtitle {
  font-size: 14.5px;
  line-height: 1.6;
  color: var(--muted);
  margin-bottom: 32px;
}

/* Perks List */
.perks-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-bottom: 36px;
}

.perk-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.perk-icon-box {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--pink-dark);
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

html.dark .perk-icon-box {
  color: #f8b4c4;
}

.perk-text h4 {
  font-size: 14.5px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 2px;
}

.perk-text p {
  font-size: 12.5px;
  line-height: 1.5;
  color: var(--muted);
  margin: 0;
}

/* Testimonial */
.testimonial-box {
  position: relative;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px 22px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

.quote-symbol {
  position: absolute;
  top: 6px;
  right: 14px;
  font-size: 38px;
  line-height: 1;
  font-family: Georgia, serif;
  color: var(--pink-dark);
  opacity: 0.2;
}

.quote-text {
  font-size: 13px;
  font-style: italic;
  line-height: 1.55;
  color: var(--ink);
  margin: 0 0 10px;
}

.quote-author {
  font-size: 12px;
  font-weight: 600;
  color: var(--pink-dark);
}

html.dark .quote-author {
  color: #f8b4c4;
}

/* Form Panel (Right) */
.form-panel {
  padding: 44px 44px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  background: var(--surface);
}

/* Segmented Tab Switcher */
.auth-tabs {
  display: flex;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  padding: 5px;
  border-radius: 14px;
  margin-bottom: 24px;
}

.auth-tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  font-size: 14px;
  font-weight: 600;
  color: var(--muted);
  background: transparent;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-tab-btn:hover:not(.active) {
  color: var(--ink);
}

.auth-tab-btn.active {
  background: var(--surface);
  color: var(--pink-dark);
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
}

html.dark .auth-tab-btn.active {
  color: #f8b4c4;
  background: var(--soft);
}

.tab-icon {
  font-size: 18px;
}

/* Form Header */
.form-header {
  margin-bottom: 20px;
}

.form-title {
  font-size: 24px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.02em;
  margin: 0 0 6px;
}

.form-subtitle {
  font-size: 13.5px;
  color: var(--muted);
  margin: 0;
  line-height: 1.5;
}

/* Error Banner */
.error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background: rgba(186, 26, 26, 0.08);
  border: 1px solid rgba(186, 26, 26, 0.25);
  border-radius: 12px;
  color: #ba1a1a;
  margin-bottom: 18px;
  font-size: 13px;
  animation: shake 0.4s ease;
}

html.dark .error-banner {
  background: rgba(255, 218, 214, 0.12);
  border-color: rgba(255, 218, 214, 0.25);
  color: #ffb4ab;
}

.error-icon {
  font-size: 20px;
  flex-shrink: 0;
}

.error-text {
  flex: 1;
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

/* Form Fields */
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
  color: var(--ink);
}

.required {
  color: #ba1a1a;
}

.forgot-link {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--pink-dark);
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
  color: var(--muted);
  font-size: 20px;
  pointer-events: none;
  transition: color 0.2s ease;
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

.custom-input.with-toggle {
  padding-right: 46px;
}

.custom-input:focus {
  border-color: var(--pink-dark);
  box-shadow: 0 0 0 3px rgba(120, 85, 94, 0.15);
}

html.dark .custom-input:focus {
  border-color: #f8b4c4;
  box-shadow: 0 0 0 3px rgba(248, 180, 196, 0.15);
}

.custom-input:focus ~ .input-icon {
  color: var(--pink-dark);
}

html.dark .custom-input:focus ~ .input-icon {
  color: #f8b4c4;
}

.pwd-toggle-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  cursor: pointer;
  border-radius: 6px;
  transition: color 0.15s ease;
}

.pwd-toggle-btn:hover {
  color: var(--ink);
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
  color: var(--muted);
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
  background: var(--soft);
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

/* Checkboxes & Terms */
.form-options,
.terms-agreement {
  display: flex;
  align-items: center;
}

.custom-checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  cursor: pointer;
  user-select: none;
}

.custom-checkbox {
  margin-top: 3px;
  width: 16px;
  height: 16px;
  accent-color: var(--pink-dark);
  cursor: pointer;
}

.checkbox-text {
  font-size: 13px;
  color: var(--muted);
  line-height: 1.4;
}

.terms-link {
  color: var(--pink-dark);
  text-decoration: underline;
}

html.dark .terms-link {
  color: #f8b4c4;
}

/* Submit Button */
.auth-submit-btn {
  margin-top: 8px;
  height: 50px;
  background: var(--pink-dark);
  color: #ffffff;
  border: none;
  border-radius: 14px;
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.28);
}

.auth-submit-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(120, 85, 94, 0.38);
  filter: brightness(1.08);
}

.auth-submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.submit-icon {
  font-size: 20px;
  transition: transform 0.2s ease;
}

.auth-submit-btn:hover:not(:disabled) .submit-icon {
  transform: translateX(3px);
}

/* Spinner loader */
.spinner {
  width: 20px;
  height: 20px;
  border: 2.5px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: #ffffff;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Social Login Divider & Buttons */
.social-divider {
  display: flex;
  align-items: center;
  gap: 14px;
  margin: 20px 0 16px;
}

.divider-line {
  flex: 1;
  height: 1px;
  background: var(--border);
}

.divider-text {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}

.social-buttons-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 8px;
}

.social-auth-btn {
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.social-auth-btn:hover:not(:disabled) {
  background: var(--surface-subtle);
  border-color: var(--pink-dark);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

html.dark .social-auth-btn:hover:not(:disabled) {
  border-color: #f8b4c4;
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
  border: 2px solid var(--border);
  border-radius: 50%;
  border-top-color: var(--pink-dark);
  animation: spin 0.7s linear infinite;
}

html.dark .social-spinner {
  border-top-color: #f8b4c4;
}

/* Switcher Footer */
.auth-switch-footer {
  margin-top: 24px;
  text-align: center;
  padding-top: 20px;
  border-top: 1px solid var(--border);
}

.switch-text {
  font-size: 13.5px;
  color: var(--muted);
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
}

.switch-action-btn {
  background: transparent;
  border: none;
  color: var(--pink-dark);
  font-size: 13.5px;
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
    max-width: 540px;
    margin: 0 auto;
    border-radius: 20px;
  }

  .brand-panel {
    display: none;
  }

  .form-panel {
    padding: 32px 24px;
  }
}

@media (max-width: 480px) {
  .auth-page-wrapper {
    padding: 20px 0 60px;
  }

  .form-panel {
    padding: 24px 18px;
  }

  .social-buttons-grid {
    grid-template-columns: 1fr;
  }
}
</style>
