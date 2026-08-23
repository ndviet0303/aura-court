<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, errorMessage } from '@/lib/api'
import type { Membership } from '@/types'
import { useAuthStore } from '@/stores/auth'
import { formatDate } from '@/i18n'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

// Tabs
type Tab = 'general' | 'security' | 'membership' | 'shortcuts'
const activeTab = ref<Tab>('general')

// General Profile Form
const name = ref(auth.user?.name || '')
const phone = ref(auth.user?.phone || '')
const playLevel = ref(auth.user?.play_level || 'intermediate')

// Password Form
const currentPassword = ref('')
const newPassword = ref('')
const passwordConfirmation = ref('')
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)

// Membership & stats
const membership = ref<Membership | null>(null)
const bookingsCount = ref<number | null>(null)

// State
const error = ref('')
const notice = ref('')
const loading = ref(false)
const passwordLoading = ref(false)
const verificationLoading = ref(false)

const userInitials = computed(() => {
  if (!auth.user?.name) return 'AC'
  const parts = auth.user.name.trim().split(/\s+/)
  if (parts.length === 1) return (parts[0]?.substring(0, 2) ?? 'AC').toUpperCase()
  return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase()
})

const isSocialUserWithoutPassword = computed(() => {
  return auth.user?.password_set === false
})

const socialProviderName = computed(() => {
  if (auth.user?.social_provider === 'google') return 'Google'
  if (auth.user?.social_provider === 'facebook') return 'Facebook'
  return 'Mạng xã hội'
})

const playLevelInfo = computed(() => {
  switch (playLevel.value) {
    case 'beginner':
      return { label: t('profile.levelBeginner'), icon: 'spa', desc: 'Mới làm quen bộ môn cầu lông' }
    case 'advanced':
      return { label: t('profile.levelAdvanced'), icon: 'bolt', desc: 'Kỹ thuật tốt, thường xuyên giao lưu cọ xát' }
    case 'pro':
      return { label: t('profile.levelPro'), icon: 'military_tech', desc: 'Thi đấu chuyên nghiệp, đẳng cấp cao' }
    case 'intermediate':
    default:
      return { label: t('profile.levelIntermediate'), icon: 'sports_tennis', desc: 'Nắm vững luật và các đường cầu cơ bản' }
  }
})

const passwordStrength = computed(() => {
  const p = newPassword.value
  if (!p) return 0
  let score = 0
  if (p.length >= 8) score += 25
  if (/[A-Z]/.test(p)) score += 25
  if (/[0-9]/.test(p)) score += 25
  if (/[^A-Za-z0-9]/.test(p)) score += 25
  return score
})

const passwordsMatch = computed(() => {
  if (!newPassword.value || !passwordConfirmation.value) return false
  return newPassword.value === passwordConfirmation.value
})

async function updateGeneralProfile() {
  loading.value = true
  error.value = ''
  notice.value = ''
  try {
    await auth.updateProfile({
      name: name.value.trim(),
      phone: phone.value.trim(),
      play_level: playLevel.value,
    })
    notice.value = t('profile.saveSuccess')
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}

async function updatePassword() {
  if (newPassword.value !== passwordConfirmation.value) {
    error.value = t('auth.passwordsDoNotMatch')
    return
  }
  passwordLoading.value = true
  error.value = ''
  notice.value = ''
  try {
    await auth.changePassword(
      isSocialUserWithoutPassword.value ? '' : currentPassword.value,
      newPassword.value,
      passwordConfirmation.value
    )
    currentPassword.value = ''
    newPassword.value = ''
    passwordConfirmation.value = ''
    notice.value = t('profile.changePasswordSuccess')
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    passwordLoading.value = false
  }
}

async function resendVerification() {
  verificationLoading.value = true
  error.value = ''
  notice.value = ''
  try {
    await auth.resendVerification()
    notice.value = t('common.success')
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    verificationLoading.value = false
  }
}

async function handleLogout() {
  if (window.confirm(t('auth.logoutConfirm'))) {
    await auth.logout()
    router.push('/login')
  }
}

onMounted(async () => {
  if (auth.user) {
    name.value = auth.user.name || ''
    phone.value = auth.user.phone || ''
    playLevel.value = auth.user.play_level || 'intermediate'
  }
  try {
    const [memRes, bookRes] = await Promise.allSettled([
      api.get<{ membership: Membership | null }>('/membership'),
      api.get<{ bookings?: unknown[] }>('/bookings'),
    ])
    if (memRes.status === 'fulfilled') {
      membership.value = memRes.value.membership
    }
    if (bookRes.status === 'fulfilled' && Array.isArray(bookRes.value.bookings)) {
      bookingsCount.value = bookRes.value.bookings.length
    }
  } catch {
    // optional
  }
})
</script>

<template>
  <div class="stitch-profile-root">
    <!-- Ambient Backdrop Aura Glow -->
    <div class="ambient-glow-top" aria-hidden="true"></div>
    <div class="ambient-glow-bottom" aria-hidden="true"></div>

    <div class="stitch-profile-container">
      <!-- 1. HERO COVER & ATHLETE CARD -->
      <section class="profile-hero-card">
        <div class="hero-cover-pattern">
          <div class="pattern-mesh"></div>
          <div class="cover-gradient-overlay"></div>
          
          <!-- Badges on top right of cover -->
          <div class="cover-top-actions">
            <span class="badge-role" :class="auth.user?.role">
              <span class="material-symbols-outlined icon-16">{{ auth.user?.role === 'admin' ? 'admin_panel_settings' : 'sports_tennis' }}</span>
              {{ auth.user?.role === 'admin' ? t('profile.roleAdmin') : t('profile.roleCustomer') }}
            </span>
            <span v-if="auth.user?.social_provider" class="badge-social">
              <svg v-if="auth.user?.social_provider === 'google'" class="brand-svg-14" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
              </svg>
              <svg v-else class="brand-svg-14" viewBox="0 0 24 24" fill="#1877F2">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              {{ socialProviderName }}
            </span>
          </div>
        </div>

        <div class="hero-profile-body">
          <div class="avatar-wrapper">
            <div class="avatar-ring">
              <div class="athlete-avatar-circle">
                <span>{{ userInitials }}</span>
              </div>
            </div>
            <span class="status-dot" title="Tài khoản đang hoạt động"></span>
          </div>

          <div class="athlete-details-col">
            <div class="name-row">
              <h1 class="athlete-display-name">{{ auth.user?.name || 'Aura Athlete' }}</h1>
              <span v-if="auth.user?.email_verified_at" class="pill-verified" :title="t('profile.emailVerified')">
                <span class="material-symbols-outlined icon-16">verified</span>
                {{ t('profile.emailVerified') }}
              </span>
              <span v-else class="pill-unverified">
                <span class="material-symbols-outlined icon-16">warning</span>
                {{ t('profile.emailUnverified') }}
              </span>
            </div>

            <div class="meta-subrow">
              <span class="meta-item">
                <span class="material-symbols-outlined icon-16">mail</span> {{ auth.user?.email }}
              </span>
              <span v-if="auth.user?.phone" class="meta-item">
                <span class="material-symbols-outlined icon-16">call</span> {{ auth.user.phone }}
              </span>
              <span class="meta-item">
                <span class="material-symbols-outlined icon-16">military_tech</span> {{ playLevelInfo.label }}
              </span>
            </div>

            <!-- Email Verification Callout if unverified -->
            <div v-if="!auth.user?.email_verified_at" class="verification-callout">
              <span>{{ t('profile.emailUnverified') }}.</span>
              <button
                type="button"
                class="btn-resend-link"
                :disabled="verificationLoading"
                @click="resendVerification"
              >
                {{ verificationLoading ? t('common.loading') : t('profile.resendVerification') }}
              </button>
            </div>
          </div>

          <!-- Quick Stat Counters -->
          <div class="stats-pills-row">
            <div class="stat-pill">
              <span class="stat-num">{{ bookingsCount !== null ? bookingsCount : '—' }}</span>
              <span class="stat-label">Lịch đặt sân</span>
            </div>
            <div class="stat-pill vip-pill" v-if="membership">
              <span class="stat-num text-gold">-{{ membership.plan.discount_percent }}%</span>
              <span class="stat-label">VIP {{ membership.plan.name }}</span>
            </div>
            <div class="stat-pill" v-else>
              <span class="stat-num">Standard</span>
              <span class="stat-label">Hạng thành viên</span>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. MAIN BENTO GRID (Navigation & Content) -->
      <div class="stitch-layout-grid">
        <!-- LEFT SIDEBAR: Nav Pills & Summary Cards -->
        <aside class="stitch-sidebar">
          <nav class="stitch-nav-menu">
            <button
              type="button"
              class="nav-tab-btn"
              :class="{ active: activeTab === 'general' }"
              @click="activeTab = 'general'"
            >
              <span class="tab-icon-box">
                <span class="material-symbols-outlined">person</span>
              </span>
              <div class="tab-text-wrap">
                <span class="tab-title">{{ t('profile.tabGeneral') }}</span>
                <span class="tab-subtitle">Họ tên, SĐT & Trình độ</span>
              </div>
            </button>

            <button
              type="button"
              class="nav-tab-btn"
              :class="{ active: activeTab === 'security' }"
              @click="activeTab = 'security'"
            >
              <span class="tab-icon-box">
                <span class="material-symbols-outlined">lock</span>
              </span>
              <div class="tab-text-wrap">
                <span class="tab-title">{{ t('profile.tabSecurity') }}</span>
                <span class="tab-subtitle">{{ isSocialUserWithoutPassword ? 'Thiết lập mật khẩu' : 'Đổi mật khẩu' }}</span>
              </div>
              <span v-if="isSocialUserWithoutPassword" class="tab-indicator-badge">Mới</span>
            </button>

            <button
              type="button"
              class="nav-tab-btn"
              :class="{ active: activeTab === 'membership' }"
              @click="activeTab = 'membership'"
            >
              <span class="tab-icon-box">
                <span class="material-symbols-outlined">workspace_premium</span>
              </span>
              <div class="tab-text-wrap">
                <span class="tab-title">{{ t('profile.tabMembership') }}</span>
                <span class="tab-subtitle">Đặc quyền giảm giá</span>
              </div>
            </button>

            <button
              type="button"
              class="nav-tab-btn"
              :class="{ active: activeTab === 'shortcuts' }"
              @click="activeTab = 'shortcuts'"
            >
              <span class="tab-icon-box">
                <span class="material-symbols-outlined">bolt</span>
              </span>
              <div class="tab-text-wrap">
                <span class="tab-title">{{ t('profile.tabQuickLinks') }}</span>
                <span class="tab-subtitle">Quản lý & Lối tắt</span>
              </div>
            </button>
          </nav>

          <!-- VIP Perk Widget in Sidebar -->
          <div class="sidebar-perk-widget">
            <div class="widget-header">
              <span class="material-symbols-outlined text-gold">sports_tennis</span>
              <span class="widget-badge">Aura Badminton</span>
            </div>
            <h3 class="widget-title">Trải nghiệm sân đỉnh cao</h3>
            <p class="widget-desc">Sân thảm BWF tiêu chuẩn quốc tế, đèn chống lóa và đặt lịch nhanh 24/7.</p>
            <RouterLink to="/v2/reservations" class="widget-btn">
              Đặt sân trực tuyến <span class="material-symbols-outlined icon-14 inline-icon">arrow_forward</span>
            </RouterLink>
          </div>
        </aside>

        <!-- RIGHT CONTENT PANEL -->
        <main class="stitch-content-panel">
          <!-- Global Alerts -->
          <div v-if="notice" class="stitch-alert alert-success" role="alert">
            <span class="material-symbols-outlined alert-icon">check_circle</span>
            <span class="alert-text">{{ notice }}</span>
          </div>

          <div v-if="error" class="stitch-alert alert-error" role="alert">
            <span class="material-symbols-outlined alert-icon">error</span>
            <span class="alert-text">{{ error }}</span>
          </div>

          <!-- TAB 1: General Info -->
          <div v-if="activeTab === 'general'" class="stitch-card-pane">
            <div class="pane-header">
              <h2 class="pane-title">{{ t('profile.tabGeneral') }}</h2>
              <p class="pane-subtitle">{{ t('profile.subtitle') }}</p>
            </div>

            <form @submit.prevent="updateGeneralProfile" class="stitch-form">
              <div class="form-row-2">
                <!-- Full Name -->
                <div class="form-field">
                  <label for="f-name" class="field-label">{{ t('profile.fullName') }} <span class="req">*</span></label>
                  <div class="input-wrap">
                    <span class="material-symbols-outlined prefix-icon">person</span>
                    <input
                      id="f-name"
                      v-model="name"
                      type="text"
                      required
                      maxlength="120"
                      class="stitch-input"
                      :placeholder="t('auth.namePlaceholder')"
                    />
                  </div>
                </div>

                <!-- Phone -->
                <div class="form-field">
                  <label for="f-phone" class="field-label">{{ t('profile.phone') }}</label>
                  <div class="input-wrap">
                    <span class="material-symbols-outlined prefix-icon">call</span>
                    <input
                      id="f-phone"
                      v-model="phone"
                      type="tel"
                      maxlength="30"
                      class="stitch-input"
                      :placeholder="t('profile.phonePlaceholder')"
                    />
                  </div>
                </div>
              </div>

              <!-- Email (Disabled) -->
              <div class="form-field">
                <label class="field-label">{{ t('auth.email') }}</label>
                <div class="input-wrap disabled-wrap">
                  <span class="material-symbols-outlined prefix-icon">mail</span>
                  <input
                    type="email"
                    :value="auth.user?.email"
                    disabled
                    class="stitch-input disabled-input"
                  />
                </div>
                <span class="field-hint">Địa chỉ email được sử dụng để nhận mã vé và biên lai đặt sân.</span>
              </div>

              <!-- Play Level Cards -->
              <div class="form-field">
                <label class="field-label">{{ t('profile.playLevel') }}</label>
                <div class="skill-cards-grid">
                  <label
                    class="skill-card-item"
                    :class="{ active: playLevel === 'beginner' }"
                  >
                    <input type="radio" v-model="playLevel" value="beginner" class="sr-only" />
                    <span class="material-symbols-outlined skill-symbol">spa</span>
                    <span class="skill-title">{{ t('profile.levelBeginner') }}</span>
                    <span class="skill-sub">Mới chơi</span>
                  </label>

                  <label
                    class="skill-card-item"
                    :class="{ active: playLevel === 'intermediate' }"
                  >
                    <input type="radio" v-model="playLevel" value="intermediate" class="sr-only" />
                    <span class="material-symbols-outlined skill-symbol">sports_tennis</span>
                    <span class="skill-title">{{ t('profile.levelIntermediate') }}</span>
                    <span class="skill-sub">Phong trào</span>
                  </label>

                  <label
                    class="skill-card-item"
                    :class="{ active: playLevel === 'advanced' }"
                  >
                    <input type="radio" v-model="playLevel" value="advanced" class="sr-only" />
                    <span class="material-symbols-outlined skill-symbol">bolt</span>
                    <span class="skill-title">{{ t('profile.levelAdvanced') }}</span>
                    <span class="skill-sub">Bán chuyên</span>
                  </label>

                  <label
                    class="skill-card-item"
                    :class="{ active: playLevel === 'pro' }"
                  >
                    <input type="radio" v-model="playLevel" value="pro" class="sr-only" />
                    <span class="material-symbols-outlined skill-symbol">military_tech</span>
                    <span class="skill-title">{{ t('profile.levelPro') }}</span>
                    <span class="skill-sub">Chuyên nghiệp</span>
                  </label>
                </div>
              </div>

              <div class="form-footer">
                <button type="submit" class="stitch-btn-primary" :disabled="loading">
                  <span v-if="loading" class="spin-loader"></span>
                  <span class="material-symbols-outlined icon-18" v-else>save</span>
                  {{ loading ? t('common.loading') : t('profile.saveChanges') }}
                </button>
              </div>
            </form>
          </div>

          <!-- TAB 2: Security & Password -->
          <div v-if="activeTab === 'security'" class="stitch-card-pane">
            <div class="pane-header">
              <!-- Special Banner for Social Users -->
              <div v-if="isSocialUserWithoutPassword" class="social-notice-box">
                <div class="notice-top">
                  <span class="social-tag">
                    <svg v-if="auth.user?.social_provider === 'google'" class="brand-svg-14" viewBox="0 0 24 24">
                      <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                      <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                      <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                      <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                    </svg>
                    <svg v-else class="brand-svg-14" viewBox="0 0 24 24" fill="#1877F2">
                      <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    {{ t('profile.socialLoginBadge') }} {{ socialProviderName }}
                  </span>
                </div>
                <h3 class="notice-title">{{ t('profile.setPasswordTitle') }}</h3>
                <p class="notice-desc">{{ t('profile.setPasswordSubtitle', { provider: socialProviderName }) }}</p>
              </div>

              <div v-else>
                <h2 class="pane-title">{{ t('profile.passwordSection') }}</h2>
                <p class="pane-subtitle">{{ t('profile.passwordSubtitle') }}</p>
              </div>
            </div>

            <form @submit.prevent="updatePassword" class="stitch-form">
              <!-- Current Password (only if already set) -->
              <div v-if="!isSocialUserWithoutPassword" class="form-field">
                <label for="f-curr-pwd" class="field-label">{{ t('profile.currentPassword') }} <span class="req">*</span></label>
                <div class="input-wrap">
                  <span class="material-symbols-outlined prefix-icon">key</span>
                  <input
                    id="f-curr-pwd"
                    v-model="currentPassword"
                    :type="showCurrentPassword ? 'text' : 'password'"
                    :required="!isSocialUserWithoutPassword"
                    minlength="8"
                    class="stitch-input"
                  />
                  <button
                    type="button"
                    class="toggle-eye-btn"
                    @click="showCurrentPassword = !showCurrentPassword"
                  >
                    <span class="material-symbols-outlined">{{ showCurrentPassword ? 'visibility' : 'visibility_off' }}</span>
                  </button>
                </div>
              </div>

              <div class="form-row-2">
                <!-- New Password -->
                <div class="form-field">
                  <label for="f-new-pwd" class="field-label">{{ t('profile.newPassword') }} <span class="req">*</span></label>
                  <div class="input-wrap">
                    <span class="material-symbols-outlined prefix-icon">lock</span>
                    <input
                      id="f-new-pwd"
                      v-model="newPassword"
                      :type="showNewPassword ? 'text' : 'password'"
                      required
                      minlength="8"
                      class="stitch-input"
                      :placeholder="t('auth.passwordPlaceholder')"
                    />
                    <button
                      type="button"
                      class="toggle-eye-btn"
                      @click="showNewPassword = !showNewPassword"
                    >
                      <span class="material-symbols-outlined">{{ showNewPassword ? 'visibility' : 'visibility_off' }}</span>
                    </button>
                  </div>

                  <!-- Password Strength Meter -->
                  <div v-if="newPassword" class="password-meter-wrap">
                    <div class="meter-bar-track">
                      <div
                        class="meter-bar-progress"
                        :style="{ width: `${passwordStrength}%` }"
                        :class="{
                          weak: passwordStrength <= 25,
                          med: passwordStrength > 25 && passwordStrength <= 75,
                          strong: passwordStrength > 75,
                        }"
                      ></div>
                    </div>
                  </div>
                </div>

                <!-- Confirm New Password -->
                <div class="form-field">
                  <label for="f-conf-pwd" class="field-label">{{ t('profile.confirmNewPassword') }} <span class="req">*</span></label>
                  <div class="input-wrap">
                    <span class="material-symbols-outlined prefix-icon">shield</span>
                    <input
                      id="f-conf-pwd"
                      v-model="passwordConfirmation"
                      :type="showConfirmPassword ? 'text' : 'password'"
                      required
                      minlength="8"
                      class="stitch-input"
                      :placeholder="t('auth.passwordConfirmPlaceholder')"
                    />
                    <button
                      type="button"
                      class="toggle-eye-btn"
                      @click="showConfirmPassword = !showConfirmPassword"
                    >
                      <span class="material-symbols-outlined">{{ showConfirmPassword ? 'visibility' : 'visibility_off' }}</span>
                    </button>
                  </div>

                  <!-- Password Match Pill -->
                  <div v-if="passwordConfirmation" class="match-pill" :class="{ matched: passwordsMatch }">
                    <span class="material-symbols-outlined icon-14 inline-icon">{{ passwordsMatch ? 'check_circle' : 'cancel' }}</span>
                    <span>{{ passwordsMatch ? t('auth.passwordMatch') : t('auth.passwordsDoNotMatch') }}</span>
                  </div>
                </div>
              </div>

              <div class="form-footer">
                <button
                  type="submit"
                  class="stitch-btn-primary"
                  :disabled="passwordLoading || (newPassword.length > 0 && !passwordsMatch)"
                >
                  <span v-if="passwordLoading" class="spin-loader"></span>
                  <span class="material-symbols-outlined icon-18" v-else>lock_reset</span>
                  {{ passwordLoading ? t('common.loading') : (isSocialUserWithoutPassword ? t('profile.setPasswordBtn') : t('profile.passwordSection')) }}
                </button>
              </div>
            </form>
          </div>

          <!-- TAB 3: VIP Membership -->
          <div v-if="activeTab === 'membership'" class="stitch-card-pane">
            <div class="pane-header">
              <h2 class="pane-title">{{ t('profile.vipTitle') }}</h2>
              <p class="pane-subtitle">{{ t('profile.vipSubtitle') }}</p>
            </div>

            <div v-if="membership" class="luxury-vip-card">
              <div class="vip-top-row">
                <div class="vip-crest-badge">
                  <span class="material-symbols-outlined icon-16">workspace_premium</span>
                  AURA CLUB VIP
                </div>
                <div class="vip-tier-name">{{ membership.plan.name }}</div>
              </div>
              <div class="vip-body-grid">
                <div class="vip-feature-box">
                  <span class="feature-label">{{ t('profile.vipDiscount') }}</span>
                  <span class="feature-val">-{{ membership.plan.discount_percent }}%</span>
                </div>
                <div class="vip-feature-box">
                  <span class="feature-label">{{ t('profile.vipExpires') }}</span>
                  <span class="feature-val">{{ formatDate(membership.ends_at) }}</span>
                </div>
              </div>
            </div>

            <div v-else class="empty-state-card">
              <div class="empty-glow-icon">
                <span class="material-symbols-outlined icon-48 text-gold">workspace_premium</span>
              </div>
              <h3 class="empty-title">Chưa kích hoạt gói VIP</h3>
              <p class="empty-desc">{{ t('profile.vipNone') }}</p>
              <RouterLink to="/v2/reservations" class="stitch-btn-secondary">
                <span class="material-symbols-outlined icon-18">sports_tennis</span>
                {{ t('profile.bookCourtNow') }}
              </RouterLink>
            </div>
          </div>

          <!-- TAB 4: Shortcuts & Quick Actions -->
          <div v-if="activeTab === 'shortcuts'" class="stitch-card-pane">
            <div class="pane-header">
              <h2 class="pane-title">{{ t('profile.tabQuickLinks') }}</h2>
              <p class="pane-subtitle">{{ t('profile.subtitle') }}</p>
            </div>

            <div class="bento-shortcuts-grid">
              <RouterLink to="/bookings" class="bento-shortcut-item">
                <div class="bento-icon-sq">
                  <span class="material-symbols-outlined">calendar_month</span>
                </div>
                <div class="bento-info">
                  <h4 class="bento-h">{{ t('profile.myBookings') }}</h4>
                  <p class="bento-p">Xem các lịch sân đã đặt và hóa đơn thanh toán</p>
                </div>
                <span class="material-symbols-outlined bento-arrow">arrow_forward</span>
              </RouterLink>

              <RouterLink v-if="auth.user?.role === 'admin'" to="/admin/organization" class="bento-shortcut-item admin-theme">
                <div class="bento-icon-sq">
                  <span class="material-symbols-outlined">domain</span>
                </div>
                <div class="bento-info">
                  <h4 class="bento-h">{{ t('profile.manageOrg') }}</h4>
                  <p class="bento-p">Cấu hình cụm sân, thời gian biểu và giá giờ vàng</p>
                </div>
                <span class="material-symbols-outlined bento-arrow">arrow_forward</span>
              </RouterLink>

              <RouterLink to="/v2/reservations" class="bento-shortcut-item">
                <div class="bento-icon-sq">
                  <span class="material-symbols-outlined">sports_tennis</span>
                </div>
                <div class="bento-info">
                  <h4 class="bento-h">{{ t('profile.bookCourtNow') }}</h4>
                  <p class="bento-p">Tìm sân trống và đặt ngay theo thời gian thực</p>
                </div>
                <span class="material-symbols-outlined bento-arrow">arrow_forward</span>
              </RouterLink>

              <button type="button" class="bento-shortcut-item danger-theme" @click="handleLogout">
                <div class="bento-icon-sq">
                  <span class="material-symbols-outlined">logout</span>
                </div>
                <div class="bento-info">
                  <h4 class="bento-h">{{ t('profile.logoutAccount') }}</h4>
                  <p class="bento-p">Đăng xuất khỏi phiên đăng nhập trên thiết bị này</p>
                </div>
                <span class="material-symbols-outlined bento-arrow">power_settings_new</span>
              </button>
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stitch-profile-root {
  position: relative;
  min-height: calc(100vh - 120px);
  padding: 32px 16px 80px;
  max-width: 1120px;
  margin: 0 auto;
}

/* Ambient Lights */
.ambient-glow-top {
  position: absolute;
  top: 0;
  left: 20%;
  width: 500px;
  height: 300px;
  background: radial-gradient(circle, rgba(255, 122, 26, 0.08) 0%, transparent 70%);
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}

.ambient-glow-bottom {
  position: absolute;
  bottom: 10%;
  right: 15%;
  width: 450px;
  height: 300px;
  background: radial-gradient(circle, rgba(131, 56, 236, 0.06) 0%, transparent 70%);
  filter: blur(90px);
  pointer-events: none;
  z-index: 0;
}

.stitch-profile-container {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* Icons Utilities */
.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
  vertical-align: middle;
  line-height: 1;
}

.icon-14 {
  font-size: 14px !important;
}

.icon-16 {
  font-size: 16px !important;
}

.icon-18 {
  font-size: 18px !important;
}

.icon-48 {
  font-size: 48px !important;
}

.inline-icon {
  display: inline-flex;
  vertical-align: text-bottom;
}

.brand-svg-14 {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}

/* 1. HERO COVER & ATHLETE CARD */
.profile-hero-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 28px;
  overflow: hidden;
  box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.35);
}

.hero-cover-pattern {
  position: relative;
  height: 140px;
  background: linear-gradient(135deg, #1c1917 0%, #292524 50%, #431407 100%);
  overflow: hidden;
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
  padding: 18px 24px;
}

.pattern-mesh {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
  background-size: 16px 16px;
  opacity: 0.8;
}

.cover-gradient-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, transparent 0%, rgba(0, 0, 0, 0.4) 100%);
}

.cover-top-actions {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 10px;
}

.badge-role {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 700;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(10px);
  color: #ff8c3a;
  border: 1px solid rgba(255, 140, 58, 0.4);
}

.badge-role.admin {
  color: #c4b5fd;
  border-color: rgba(196, 181, 253, 0.4);
}

.badge-social {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 700;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(10px);
  color: #93c5fd;
  border: 1px solid rgba(147, 197, 253, 0.4);
}

.hero-profile-body {
  position: relative;
  padding: 0 32px 32px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

@media (min-width: 768px) {
  .hero-profile-body {
    flex-direction: row;
    align-items: flex-end;
  }
}

.avatar-wrapper {
  position: relative;
  margin-top: -50px;
  width: 108px;
  height: 108px;
  flex-shrink: 0;
}

.avatar-ring {
  width: 100%;
  height: 100%;
  border-radius: 32px;
  padding: 4px;
  background: linear-gradient(135deg, #ff7a1a 0%, #e63946 50%, #8338ec 100%);
  box-shadow: 0 12px 28px -6px rgba(230, 57, 70, 0.5);
}

.athlete-avatar-circle {
  width: 100%;
  height: 100%;
  border-radius: 28px;
  background: var(--surface, #18181b);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: 900;
  font-size: 34px;
  letter-spacing: -0.02em;
}

.status-dot {
  position: absolute;
  bottom: 2px;
  right: 2px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #10b981;
  border: 3px solid var(--surface, #18181b);
}

.athlete-details-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.name-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.athlete-display-name {
  font-size: 26px;
  font-weight: 900;
  color: var(--text-primary);
  margin: 0;
  line-height: 1.2;
}

.pill-verified {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.pill-unverified {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
}

.meta-subrow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 16px;
  font-size: 13px;
  color: var(--text-muted);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.verification-callout {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
  font-size: 12px;
  color: #f87171;
}

.btn-resend-link {
  background: transparent;
  border: none;
  color: #60a5fa;
  font-size: 12px;
  font-weight: 700;
  text-decoration: underline;
  cursor: pointer;
}

.stats-pills-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.stat-pill {
  padding: 12px 18px;
  border-radius: 18px;
  background: var(--surface-input, rgba(0, 0, 0, 0.2));
  border: 1px solid var(--outline);
  text-align: center;
  min-width: 100px;
}

.stat-num {
  display: block;
  font-size: 18px;
  font-weight: 900;
  color: var(--text-primary);
}

.stat-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--text-muted);
}

.text-gold {
  color: #fbbf24;
}

/* 2. BENTO GRID LAYOUT */
.stitch-layout-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 900px) {
  .stitch-layout-grid {
    grid-template-columns: 280px 1fr;
  }
}

/* Sidebar Nav */
.stitch-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.stitch-nav-menu {
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 24px;
  padding: 12px;
}

.nav-tab-btn {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border-radius: 16px;
  background: transparent;
  border: 1px solid transparent;
  cursor: pointer;
  text-align: left;
  transition: all 0.2s ease;
  width: 100%;
}

.nav-tab-btn:hover {
  background: rgba(255, 255, 255, 0.04);
}

.nav-tab-btn.active {
  background: rgba(255, 140, 58, 0.12);
  border: 1px solid rgba(255, 140, 58, 0.3);
}

.tab-icon-box {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  flex-shrink: 0;
}

.nav-tab-btn.active .tab-icon-box {
  background: rgba(255, 140, 58, 0.2);
  color: #ff8c3a;
}

.tab-text-wrap {
  flex: 1;
}

.tab-title {
  display: block;
  font-size: 14px;
  font-weight: 700;
  color: var(--text-primary);
}

.nav-tab-btn.active .tab-title {
  color: #ff8c3a;
}

.tab-subtitle {
  display: block;
  font-size: 11px;
  color: var(--text-muted);
}

.tab-indicator-badge {
  padding: 2px 8px;
  border-radius: 9999px;
  background: #ff7a1a;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

/* Sidebar Perk Widget */
.sidebar-perk-widget {
  padding: 22px;
  border-radius: 24px;
  background: linear-gradient(135deg, rgba(230, 92, 0, 0.08) 0%, rgba(131, 56, 236, 0.05) 100%);
  border: 1px solid rgba(255, 140, 58, 0.25);
}

.widget-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.widget-badge {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: #ff8c3a;
  text-transform: uppercase;
}

.widget-title {
  font-size: 15px;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 6px;
}

.widget-desc {
  font-size: 12px;
  color: var(--text-muted);
  line-height: 1.5;
  margin: 0 0 14px;
}

.widget-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 700;
  color: #ff8c3a;
  text-decoration: none;
  transition: transform 0.2s ease;
}

.widget-btn:hover {
  transform: translateX(4px);
}

/* Content Panel */
.stitch-content-panel {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.stitch-alert {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 16px;
  font-size: 13px;
  font-weight: 600;
}

.alert-success {
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #34d399;
}

.alert-error {
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #f87171;
}

.stitch-card-pane {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 28px;
  padding: 32px;
  box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.2);
  animation: paneFadeIn 0.25s ease-out;
}

@keyframes paneFadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.pane-header {
  margin-bottom: 26px;
}

.pane-title {
  font-size: 20px;
  font-weight: 900;
  color: var(--text-primary);
  margin: 0 0 6px;
}

.pane-subtitle {
  font-size: 13px;
  color: var(--text-muted);
  margin: 0;
}

/* Stitch Form Controls */
.stitch-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 18px;
}

@media (min-width: 640px) {
  .form-row-2 {
    grid-template-columns: 1fr 1fr;
  }
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.field-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--text-primary);
}

.req {
  color: #ef4444;
}

.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.prefix-icon {
  position: absolute;
  left: 14px;
  color: var(--text-muted);
  font-size: 20px;
  pointer-events: none;
}

.stitch-input {
  width: 100%;
  padding: 13px 14px 13px 44px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface-input, rgba(0, 0, 0, 0.2));
  color: var(--text-primary);
  font-size: 14px;
  transition: all 0.2s ease;
}

.stitch-input:focus {
  outline: none;
  border-color: #ff8c3a;
  box-shadow: 0 0 0 3px rgba(255, 140, 58, 0.18);
}

.disabled-wrap {
  opacity: 0.65;
}

.disabled-input {
  background: rgba(0, 0, 0, 0.12);
  cursor: not-allowed;
}

.field-hint {
  font-size: 11px;
  color: var(--text-muted);
}

.toggle-eye-btn {
  position: absolute;
  right: 14px;
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
}

.toggle-eye-btn:hover {
  color: var(--text-primary);
}

/* Skill Cards Grid */
.skill-cards-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

@media (min-width: 640px) {
  .skill-cards-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.skill-card-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px 12px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
}

.skill-card-item:hover {
  border-color: rgba(255, 140, 58, 0.4);
  background: rgba(255, 140, 58, 0.05);
}

.skill-card-item.active {
  border-color: #ff8c3a;
  background: rgba(255, 140, 58, 0.12);
  box-shadow: 0 0 16px rgba(255, 140, 58, 0.2);
}

.skill-symbol {
  font-size: 28px !important;
  color: #ff8c3a;
  margin-bottom: 2px;
}

.skill-title {
  font-size: 13px;
  font-weight: 800;
  color: var(--text-primary);
}

.skill-sub {
  font-size: 10px;
  color: var(--text-muted);
}

/* Social Setup Banner */
.social-notice-box {
  padding: 20px;
  border-radius: 20px;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(131, 56, 236, 0.06) 100%);
  border: 1px solid rgba(59, 130, 246, 0.3);
  margin-bottom: 22px;
}

.social-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 800;
  background: rgba(59, 130, 246, 0.2);
  color: #93c5fd;
  border: 1px solid rgba(59, 130, 246, 0.4);
}

.notice-title {
  font-size: 16px;
  font-weight: 900;
  color: var(--text-primary);
  margin: 10px 0 4px;
}

.notice-desc {
  font-size: 12px;
  color: var(--text-muted);
  line-height: 1.5;
  margin: 0;
}

/* Password Meters */
.password-meter-wrap {
  margin-top: 6px;
}

.meter-bar-track {
  height: 4px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 2px;
  overflow: hidden;
}

.meter-bar-progress {
  height: 100%;
  transition: all 0.3s ease;
}

.meter-bar-progress.weak {
  background: #ef4444;
}

.meter-bar-progress.med {
  background: #f59e0b;
}

.meter-bar-progress.strong {
  background: #10b981;
}

.match-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 6px;
  font-size: 12px;
  font-weight: 700;
  color: #f87171;
}

.match-pill.matched {
  color: #34d399;
}

/* Buttons */
.form-footer {
  margin-top: 10px;
  display: flex;
  justify-content: flex-end;
}

.stitch-btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 13px 30px;
  border-radius: 14px;
  background: linear-gradient(135deg, #ff7a1a 0%, #e63946 100%);
  color: #fff;
  border: none;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 15px rgba(230, 57, 70, 0.35);
}

.stitch-btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 22px rgba(230, 57, 70, 0.5);
}

.stitch-btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.stitch-btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.08);
  color: var(--text-primary);
  border: 1px solid var(--outline);
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.stitch-btn-secondary:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #ff8c3a;
}

.spin-loader {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* VIP Deluxe Display */
.luxury-vip-card {
  padding: 28px;
  border-radius: 24px;
  background: linear-gradient(135deg, #1e1b4b 0%, #311042 50%, #451a03 100%);
  border: 1px solid rgba(251, 191, 36, 0.4);
  box-shadow: 0 15px 35px -10px rgba(251, 191, 36, 0.25);
}

.vip-top-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.vip-crest-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.1em;
  padding: 6px 14px;
  border-radius: 9999px;
  background: rgba(251, 191, 36, 0.2);
  color: #fbbf24;
  border: 1px solid rgba(251, 191, 36, 0.5);
}

.vip-tier-name {
  font-size: 24px;
  font-weight: 900;
  color: #fff;
}

.vip-body-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 20px;
}

.feature-label {
  display: block;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 4px;
}

.feature-val {
  font-size: 22px;
  font-weight: 900;
  color: #fbbf24;
}

.empty-state-card {
  text-align: center;
  padding: 40px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.empty-glow-icon {
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: rgba(251, 191, 36, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-title {
  font-size: 17px;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
}

.empty-desc {
  font-size: 13px;
  color: var(--text-muted);
  max-width: 400px;
  margin: 0;
}

/* Bento Shortcuts */
.bento-shortcuts-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}

@media (min-width: 640px) {
  .bento-shortcuts-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.bento-shortcut-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px 20px;
  border-radius: 20px;
  background: var(--surface-input, rgba(0, 0, 0, 0.2));
  border: 1px solid var(--outline);
  text-decoration: none;
  color: inherit;
  transition: all 0.2s ease;
  cursor: pointer;
  width: 100%;
  text-align: left;
}

.bento-shortcut-item:hover {
  transform: translateY(-2px);
  border-color: #ff8c3a;
  background: rgba(255, 140, 58, 0.06);
}

.bento-icon-sq {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-primary);
  flex-shrink: 0;
}

.bento-info {
  flex: 1;
}

.bento-h {
  font-size: 14px;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 2px;
}

.bento-p {
  font-size: 11px;
  color: var(--text-muted);
  margin: 0;
}

.bento-arrow {
  font-size: 20px;
  color: var(--text-muted);
  transition: transform 0.2s ease;
}

.bento-shortcut-item:hover .bento-arrow {
  transform: translateX(4px);
  color: #ff8c3a;
}

.admin-theme:hover {
  border-color: #8338ec;
  background: rgba(131, 56, 236, 0.06);
}

.admin-theme:hover .bento-arrow {
  color: #8338ec;
}

.danger-theme:hover {
  border-color: #ef4444;
  background: rgba(239, 68, 68, 0.06);
}

.danger-theme:hover .bento-arrow {
  color: #ef4444;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border-width: 0;
}
</style>
