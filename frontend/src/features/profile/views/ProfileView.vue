<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { profileApi } from '../api/profileApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Membership } from '@/core/types/common'
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
const favoriteSports = ref<string[]>(auth.user?.favorite_sports || ['badminton'])
const selectedAvatar = ref(auth.user?.avatar_url || '')

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

const availableSports = [
  { id: 'badminton', name: 'Cầu lông', icon: 'sports_tennis', emoji: '🏸' },
  { id: 'football_5v5', name: 'Bóng đá 5v5', icon: 'sports_soccer', emoji: '⚽' },
  { id: 'football_7v7', name: 'Bóng đá 7v7', icon: 'sports_soccer', emoji: '⚽' },
  { id: 'tennis', name: 'Tennis', icon: 'sports_tennis', emoji: '🎾' },
  { id: 'pickleball', name: 'Pickleball', icon: 'sports_tennis', emoji: '🏓' },
  { id: 'basketball', name: 'Bóng rổ', icon: 'sports_basketball', emoji: '🏀' },
  { id: 'volleyball', name: 'Bóng chuyền', icon: 'sports_volleyball', emoji: '🏐' },
  { id: 'table_tennis', name: 'Bóng bàn', icon: 'table_restaurant', emoji: '🏓' },
]

const avatarPresets = [
  { id: 'athlete-boy', url: '/avatars/athlete-boy.jpg', label: 'VĐV Nam' },
  { id: 'athlete-girl', url: '/avatars/athlete-girl.jpg', label: 'VĐV Nữ' },
]

function toggleSport(sportId: string) {
  if (favoriteSports.value.includes(sportId)) {
    if (favoriteSports.value.length > 1) {
      favoriteSports.value = favoriteSports.value.filter((id) => id !== sportId)
    }
  } else {
    favoriteSports.value.push(sportId)
  }
}

import { getAvatarUrl, getUserInitials } from '@/lib/avatar'

const userInitials = computed(() => {
  return getUserInitials(auth.user?.name)
})

const currentAvatarUrl = computed(() => {
  if (selectedAvatar.value) return selectedAvatar.value
  return getAvatarUrl(auth.user?.name, auth.user?.avatar_url)
})

const isSocialUserWithoutPassword = computed(() => {
  return auth.user?.password_set === false
})

const socialProviderName = computed(() => {
  if (auth.user?.social_provider === 'google') return 'Google'
  if (auth.user?.social_provider === 'facebook') return 'Facebook'
  return 'Mạng xã hội'
})

const playLevelLabel = computed(() => {
  switch (playLevel.value) {
    case 'beginner':
      return t('profile.levelBeginner')
    case 'advanced':
      return t('profile.levelAdvanced')
    case 'pro':
      return t('profile.levelPro')
    case 'intermediate':
    default:
      return t('profile.levelIntermediate')
  }
})

const selectedSportsList = computed(() => {
  return availableSports.filter((s) => favoriteSports.value.includes(s.id))
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
      favorite_sports: favoriteSports.value,
      avatar_url: selectedAvatar.value || undefined,
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
  await auth.logout()
  router.push('/login')
}

onMounted(async () => {
  if (auth.user) {
    name.value = auth.user.name || ''
    phone.value = auth.user.phone || ''
    playLevel.value = auth.user.play_level || 'intermediate'
    if (auth.user.favorite_sports && Array.isArray(auth.user.favorite_sports) && auth.user.favorite_sports.length > 0) {
      favoriteSports.value = auth.user.favorite_sports
    }
    if (auth.user.avatar_url) {
      selectedAvatar.value = auth.user.avatar_url
    }
  }
  try {
    const [memRes, bookRes] = await Promise.allSettled([
      profileApi.getMembership(),
      profileApi.getBookings(),
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
  <div class="stitch-profile-page">
    <div class="stitch-profile-wrap">
      <!-- 1. TOP PROFILE SUMMARY CARD -->
      <section class="stitch-summary-card">
        <div class="summary-card-inner">
          <div class="summary-hero-row">
            <!-- Cute Athlete Avatar -->
            <div class="avatar-box">
              <img :src="currentAvatarUrl" :alt="auth.user?.name" class="avatar-img" />
              <div class="avatar-initials-badge">{{ userInitials }}</div>
            </div>

            <!-- Athlete Info -->
            <div class="summary-details">
              <div class="badges-row">
                <span class="badge-pill badge-role">
                  <span class="material-symbols-outlined icon-14">person</span>
                  {{ auth.user?.role === 'admin' ? t('profile.roleAdmin') : 'Hội viên / Người chơi' }}
                </span>

                <span class="badge-pill badge-level">
                  <span class="material-symbols-outlined icon-14">directions_run</span>
                  {{ playLevelLabel }}
                </span>

                <span v-for="sport in selectedSportsList" :key="sport.id" class="badge-pill badge-sport">
                  <span class="sport-emoji">{{ sport.emoji }}</span>
                  <span>{{ sport.name }}</span>
                </span>

                <span v-if="auth.user?.email_verified_at" class="badge-pill badge-verified">
                  <span class="material-symbols-outlined icon-14">check_circle</span>
                  {{ t('profile.emailVerified') }}
                </span>
                <span v-else class="badge-pill badge-unverified">
                  <span class="material-symbols-outlined icon-14">warning</span>
                  {{ t('profile.emailUnverified') }}
                </span>

                <span v-if="auth.user?.social_provider" class="badge-pill badge-social">
                  <svg v-if="auth.user?.social_provider === 'google'" class="svg-12" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                  </svg>
                  <svg v-else class="svg-12" viewBox="0 0 24 24" fill="#1877F2">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                  </svg>
                  {{ socialProviderName }}
                </span>
              </div>

              <h1 class="athlete-display-name">{{ auth.user?.name || 'Aura Athlete' }}</h1>
              <p class="athlete-email">
                <span class="material-symbols-outlined icon-16">mail</span>
                {{ auth.user?.email }}
              </p>

              <!-- Resend verify if needed -->
              <div v-if="!auth.user?.email_verified_at" class="unverified-callout">
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
          </div>
        </div>

        <!-- Horizontal Tab Bar (Inside Summary Card) -->
        <nav class="stitch-tabs-bar" aria-label="Profile navigation">
          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'general' }"
            @click="activeTab = 'general'"
          >
            <span class="material-symbols-outlined icon-18">person</span>
            <span>{{ t('profile.tabGeneral') }}</span>
          </button>

          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'security' }"
            @click="activeTab = 'security'"
          >
            <span class="material-symbols-outlined icon-18">lock</span>
            <span>{{ isSocialUserWithoutPassword ? 'Thiết lập mật khẩu' : t('profile.tabSecurity') }}</span>
            <span v-if="isSocialUserWithoutPassword" class="mini-tag">Mới</span>
          </button>

          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'membership' }"
            @click="activeTab = 'membership'"
          >
            <span class="material-symbols-outlined icon-18">workspace_premium</span>
            <span>{{ t('profile.tabMembership') }}</span>
          </button>

          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'shortcuts' }"
            @click="activeTab = 'shortcuts'"
          >
            <span class="material-symbols-outlined icon-18">bolt</span>
            <span>{{ t('profile.tabQuickLinks') }}</span>
          </button>
        </nav>
      </section>

      <!-- Global Alerts -->
      <div v-if="notice" class="stitch-alert alert-success" role="alert">
        <span class="material-symbols-outlined">check_circle</span>
        <span>{{ notice }}</span>
      </div>

      <div v-if="error" class="stitch-alert alert-error" role="alert">
        <span class="material-symbols-outlined">error</span>
        <span>{{ error }}</span>
      </div>

      <!-- 2. MAIN CARD PANE -->
      <section class="stitch-main-card">
        <!-- TAB 1: General Info -->
        <div v-if="activeTab === 'general'" class="pane-body">
          <div class="pane-heading">
            <h2 class="heading-title">{{ t('profile.tabGeneral') }}</h2>
            <p class="heading-sub">{{ t('profile.subtitle') }}</p>
          </div>

          <form @submit.prevent="updateGeneralProfile" class="stitch-form">
            <div class="form-grid-2">
              <!-- Full Name -->
              <div class="form-group">
                <label for="f-name" class="minimal-label">{{ t('profile.fullName') }} <span class="req">*</span></label>
                <div class="minimal-input-wrap">
                  <span class="material-symbols-outlined minimal-input-icon">person</span>
                  <input
                    id="f-name"
                    v-model="name"
                    type="text"
                    required
                    maxlength="120"
                    class="minimal-input"
                    :placeholder="t('auth.namePlaceholder')"
                  />
                </div>
              </div>

              <!-- Phone Number -->
              <div class="form-group">
                <label for="f-phone" class="minimal-label">{{ t('profile.phone') }}</label>
                <div class="minimal-input-wrap">
                  <span class="material-symbols-outlined minimal-input-icon">call</span>
                  <input
                    id="f-phone"
                    v-model="phone"
                    type="tel"
                    maxlength="30"
                    class="minimal-input"
                    :placeholder="t('profile.phonePlaceholder')"
                  />
                </div>
              </div>

              <!-- Email (Disabled / Readonly) -->
              <div class="form-group full-width">
                <label class="minimal-label">{{ t('auth.email') }}</label>
                <div class="minimal-input-wrap disabled-wrap">
                  <span class="material-symbols-outlined minimal-input-icon">mail</span>
                  <input
                    type="email"
                    :value="auth.user?.email"
                    disabled
                    readonly
                    class="minimal-input disabled-input"
                  />
                </div>
              </div>
            </div>

            <!-- Multi-sport Selection Section -->
            <div class="form-group full-width sport-pref-section">
              <div class="pref-label-group">
                <label class="minimal-label">{{ t('profile.favoriteSports') }}</label>
                <span class="pref-sub-hint">{{ t('profile.favoriteSportsHint') }}</span>
              </div>
              <div class="sports-chips-grid">
                <button
                  v-for="sport in availableSports"
                  :key="sport.id"
                  type="button"
                  class="sport-chip-card"
                  :class="{ active: favoriteSports.includes(sport.id) }"
                  @click="toggleSport(sport.id)"
                >
                  <span class="sport-chip-emoji">{{ sport.emoji }}</span>
                  <span class="sport-chip-name">{{ sport.name }}</span>
                  <span v-if="favoriteSports.includes(sport.id)" class="material-symbols-outlined chip-check-icon">check_circle</span>
                </button>
              </div>
            </div>

            <!-- Skill Level Selection -->
            <div class="form-group full-width skill-section">
              <label class="minimal-label">{{ t('profile.playLevel') }}</label>
              <div class="skill-cards-grid">
                <label
                  class="skill-card-box"
                  :class="{ active: playLevel === 'beginner' }"
                >
                  <input type="radio" v-model="playLevel" value="beginner" class="sr-only" />
                  <span class="material-symbols-outlined skill-ico text-green">spa</span>
                  <span class="skill-name">{{ t('profile.levelBeginner') }}</span>
                </label>

                <label
                  class="skill-card-box"
                  :class="{ active: playLevel === 'intermediate' }"
                >
                  <input type="radio" v-model="playLevel" value="intermediate" class="sr-only" />
                  <span class="material-symbols-outlined skill-ico text-orange">directions_run</span>
                  <span class="skill-name">{{ t('profile.levelIntermediate') }}</span>
                </label>

                <label
                  class="skill-card-box"
                  :class="{ active: playLevel === 'advanced' }"
                >
                  <input type="radio" v-model="playLevel" value="advanced" class="sr-only" />
                  <span class="material-symbols-outlined skill-ico text-yellow">bolt</span>
                  <span class="skill-name">{{ t('profile.levelAdvanced') }}</span>
                </label>

                <label
                  class="skill-card-box"
                  :class="{ active: playLevel === 'pro' }"
                >
                  <input type="radio" v-model="playLevel" value="pro" class="sr-only" />
                  <span class="material-symbols-outlined skill-ico text-gold">military_tech</span>
                  <span class="skill-name">{{ t('profile.levelPro') }}</span>
                </label>
              </div>
            </div>

            <div class="form-actions-bar">
              <button type="submit" class="stitch-cta-btn" :disabled="loading">
                <span v-if="loading" class="spin-loader"></span>
                {{ loading ? t('common.loading') : t('profile.saveChanges') }}
              </button>
            </div>
          </form>
        </div>

        <!-- TAB 2: Security & Password -->
        <div v-if="activeTab === 'security'" class="pane-body">
          <div class="pane-heading">
            <div v-if="isSocialUserWithoutPassword" class="social-alert-card">
              <span class="social-pill-badge">
                <svg v-if="auth.user?.social_provider === 'google'" class="svg-12" viewBox="0 0 24 24">
                  <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                  <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                  <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                  <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                </svg>
                {{ t('profile.socialLoginBadge') }} {{ socialProviderName }}
              </span>
              <h3 class="social-alert-title">{{ t('profile.setPasswordTitle') }}</h3>
              <p class="social-alert-sub">{{ t('profile.setPasswordSubtitle', { provider: socialProviderName }) }}</p>
            </div>

            <div v-else>
              <h2 class="heading-title">{{ t('profile.passwordSection') }}</h2>
              <p class="heading-sub">{{ t('profile.passwordSubtitle') }}</p>
            </div>
          </div>

          <form @submit.prevent="updatePassword" class="stitch-form">
            <!-- Current Password -->
            <div v-if="!isSocialUserWithoutPassword" class="form-group full-width">
              <label for="f-curr-pwd" class="minimal-label">{{ t('profile.currentPassword') }} <span class="req">*</span></label>
              <div class="minimal-input-wrap">
                <span class="material-symbols-outlined minimal-input-icon">key</span>
                <input
                  id="f-curr-pwd"
                  v-model="currentPassword"
                  :type="showCurrentPassword ? 'text' : 'password'"
                  :required="!isSocialUserWithoutPassword"
                  minlength="8"
                  class="minimal-input"
                />
                <button
                  type="button"
                  class="toggle-eye-btn"
                  @click="showCurrentPassword = !showCurrentPassword"
                >
                  <span class="material-symbols-outlined icon-18">{{ showCurrentPassword ? 'visibility' : 'visibility_off' }}</span>
                </button>
              </div>
            </div>

            <div class="form-grid-2">
              <!-- New Password -->
              <div class="form-group">
                <label for="f-new-pwd" class="minimal-label">{{ t('profile.newPassword') }} <span class="req">*</span></label>
                <div class="minimal-input-wrap">
                  <span class="material-symbols-outlined minimal-input-icon">lock</span>
                  <input
                    id="f-new-pwd"
                    v-model="newPassword"
                    :type="showNewPassword ? 'text' : 'password'"
                    required
                    minlength="8"
                    class="minimal-input"
                    :placeholder="t('auth.passwordPlaceholder')"
                  />
                  <button
                    type="button"
                    class="toggle-eye-btn"
                    @click="showNewPassword = !showNewPassword"
                  >
                    <span class="material-symbols-outlined icon-18">{{ showNewPassword ? 'visibility' : 'visibility_off' }}</span>
                  </button>
                </div>

                <!-- Strength Meter -->
                <div v-if="newPassword" class="meter-bar-track">
                  <div
                    class="meter-bar-fill"
                    :style="{ width: `${passwordStrength}%` }"
                    :class="{
                      weak: passwordStrength <= 25,
                      med: passwordStrength > 25 && passwordStrength <= 75,
                      strong: passwordStrength > 75,
                    }"
                  ></div>
                </div>
              </div>

              <!-- Confirm New Password -->
              <div class="form-group">
                <label for="f-conf-pwd" class="minimal-label">{{ t('profile.confirmNewPassword') }} <span class="req">*</span></label>
                <div class="minimal-input-wrap">
                  <span class="material-symbols-outlined minimal-input-icon">shield</span>
                  <input
                    id="f-conf-pwd"
                    v-model="passwordConfirmation"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    required
                    minlength="8"
                    class="minimal-input"
                    :placeholder="t('auth.passwordConfirmPlaceholder')"
                  />
                  <button
                    type="button"
                    class="toggle-eye-btn"
                    @click="showConfirmPassword = !showConfirmPassword"
                  >
                    <span class="material-symbols-outlined icon-18">{{ showConfirmPassword ? 'visibility' : 'visibility_off' }}</span>
                  </button>
                </div>

                <!-- Match Pill -->
                <div v-if="passwordConfirmation" class="match-pill" :class="{ matched: passwordsMatch }">
                  <span class="material-symbols-outlined icon-14">{{ passwordsMatch ? 'check_circle' : 'cancel' }}</span>
                  <span>{{ passwordsMatch ? t('auth.passwordMatch') : t('auth.passwordsDoNotMatch') }}</span>
                </div>
              </div>
            </div>

            <div class="form-actions-bar">
              <button
                type="submit"
                class="stitch-cta-btn"
                :disabled="passwordLoading || (newPassword.length > 0 && !passwordsMatch)"
              >
                <span v-if="passwordLoading" class="spin-loader"></span>
                {{ passwordLoading ? t('common.loading') : (isSocialUserWithoutPassword ? t('profile.setPasswordBtn') : t('profile.passwordSection')) }}
              </button>
            </div>
          </form>
        </div>

        <!-- TAB 3: VIP Membership -->
        <div v-if="activeTab === 'membership'" class="pane-body">
          <div class="pane-heading">
            <h2 class="heading-title">{{ t('profile.vipTitle') }}</h2>
            <p class="heading-sub">{{ t('profile.vipSubtitle') }}</p>
          </div>

          <div v-if="membership" class="vip-soft-card">
            <div class="vip-row-head">
              <span class="vip-soft-tag">AURA VIP CLUB</span>
              <span class="vip-soft-tier">{{ membership.plan.name }}</span>
            </div>
            <div class="vip-row-metrics">
              <div class="metric-box">
                <span class="metric-label">{{ t('profile.vipDiscount') }}</span>
                <span class="metric-val text-brand">-{{ membership.plan.discount_percent }}%</span>
              </div>
              <div class="metric-box">
                <span class="metric-label">{{ t('profile.vipExpires') }}</span>
                <span class="metric-val">{{ formatDate(membership.ends_at) }}</span>
              </div>
            </div>
          </div>

          <div v-else class="empty-soft-box">
            <div class="empty-icon-wrap">
              <span class="material-symbols-outlined icon-36 text-brand">workspace_premium</span>
            </div>
            <h3 class="empty-h">{{ t('profile.vipPlan') }}</h3>
            <p class="empty-p">{{ t('profile.vipNone') }}</p>
            <RouterLink to="/courts" class="stitch-outline-btn">
              🏟️ {{ t('profile.bookCourtNow') }}
            </RouterLink>
          </div>
        </div>

        <!-- TAB 4: Shortcuts & Quick Actions -->
        <div v-if="activeTab === 'shortcuts'" class="pane-body">
          <div class="pane-heading">
            <h2 class="heading-title">{{ t('profile.tabQuickLinks') }}</h2>
            <p class="heading-sub">{{ t('profile.subtitle') }}</p>
          </div>

          <div class="shortcuts-soft-grid">
            <RouterLink to="/bookings" class="shortcut-card">
              <div class="shortcut-icon-circle">
                <span class="material-symbols-outlined">calendar_month</span>
              </div>
              <div class="shortcut-text">
                <h4 class="shortcut-title">{{ t('profile.myBookings') }}</h4>
                <p class="shortcut-desc">Xem lịch sân đã đặt và biên lai thanh toán</p>
              </div>
              <span class="material-symbols-outlined shortcut-chevron">chevron_right</span>
            </RouterLink>

            <RouterLink to="/courts" class="shortcut-card">
              <div class="shortcut-icon-circle">
                <span class="material-symbols-outlined">stadium</span>
              </div>
              <div class="shortcut-text">
                <h4 class="shortcut-title">{{ t('profile.bookCourtNow') }}</h4>
                <p class="shortcut-desc">Tìm sân trống đa môn thể thao và đặt trực tuyến</p>
              </div>
              <span class="material-symbols-outlined shortcut-chevron">chevron_right</span>
            </RouterLink>

            <RouterLink to="/matchmaking" class="shortcut-card">
              <div class="shortcut-icon-circle">
                <span class="material-symbols-outlined">groups</span>
              </div>
              <div class="shortcut-text">
                <h4 class="shortcut-title">{{ t('profile.matchmakingShortcut') }}</h4>
                <p class="shortcut-desc">Tìm đồng đội, đối thủ giao lưu các môn</p>
              </div>
              <span class="material-symbols-outlined shortcut-chevron">chevron_right</span>
            </RouterLink>

            <RouterLink to="/resale" class="shortcut-card">
              <div class="shortcut-icon-circle">
                <span class="material-symbols-outlined">swap_horiz</span>
              </div>
              <div class="shortcut-text">
                <h4 class="shortcut-title">{{ t('profile.resaleShortcut') }}</h4>
                <p class="shortcut-desc">Chợ nhượng vé sân & pass lịch chơi an toàn</p>
              </div>
              <span class="material-symbols-outlined shortcut-chevron">chevron_right</span>
            </RouterLink>

            <RouterLink v-if="auth.user?.role === 'admin'" to="/admin/organization" class="shortcut-card admin-border">
              <div class="shortcut-icon-circle">
                <span class="material-symbols-outlined">domain</span>
              </div>
              <div class="shortcut-text">
                <h4 class="shortcut-title">{{ t('profile.manageOrg') }}</h4>
                <p class="shortcut-desc">Quản lý cụm sân, khu thể thao và bảng giá</p>
              </div>
              <span class="material-symbols-outlined shortcut-chevron">chevron_right</span>
            </RouterLink>

            <button type="button" class="shortcut-card danger-card" @click="handleLogout">
              <div class="shortcut-icon-circle danger-icon">
                <span class="material-symbols-outlined">logout</span>
              </div>
              <div class="shortcut-text">
                <h4 class="shortcut-title">{{ t('profile.logoutAccount') }}</h4>
                <p class="shortcut-desc">Đăng xuất khỏi phiên làm việc</p>
              </div>
              <span class="material-symbols-outlined shortcut-chevron">power_settings_new</span>
            </button>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.stitch-profile-page {
  min-height: calc(100vh - 120px);
  padding: 32px 16px 80px;
  background-color: var(--bg);
  color: var(--ink);
  transition: background-color 0.3s ease, color 0.3s ease;
}

.stitch-profile-wrap {
  max-width: 860px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Material Symbols icon helpers */
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

.icon-36 {
  font-size: 36px !important;
}

.svg-12 {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
}

/* 1. TOP SUMMARY CARD */
.stitch-summary-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

.summary-card-inner {
  padding: 28px 32px 24px;
}

.summary-hero-row {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24px;
  text-align: center;
}

@media (min-width: 640px) {
  .summary-hero-row {
    flex-direction: row;
    align-items: flex-start;
    text-align: left;
  }
}

.avatar-box {
  position: relative;
  width: 96px;
  height: 96px;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 4px 14px rgba(255, 182, 197, 0.4);
  flex-shrink: 0;
  background: linear-gradient(135deg, #ffd1dc 0%, #ffb6c5 100%);
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-initials-badge {
  position: absolute;
  bottom: 4px;
  right: 4px;
  padding: 2px 6px;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(4px);
  color: #ffffff;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
}

.summary-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.badges-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 2px;
}

@media (min-width: 640px) {
  .badges-row {
    justify-content: flex-start;
  }
}

.badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 600;
}

.badge-role {
  background: #fff3ed;
  color: #c2410c;
  border: 1px solid #fed7aa;
}

html.dark .badge-role {
  background: rgba(234, 88, 12, 0.15);
  color: #fb923c;
  border-color: rgba(234, 88, 12, 0.3);
}

.badge-level {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

html.dark .badge-level {
  background: rgba(34, 197, 94, 0.15);
  color: #4ade80;
  border-color: rgba(34, 197, 94, 0.3);
}

.badge-sport {
  background: #fdf2f8;
  color: #9d174d;
  border: 1px solid #fbcfe8;
}

html.dark .badge-sport {
  background: rgba(244, 114, 182, 0.15);
  color: #f472b6;
  border-color: rgba(244, 114, 182, 0.3);
}

.sport-emoji {
  font-size: 12px;
}

.badge-verified {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

html.dark .badge-verified {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  border-color: rgba(59, 130, 246, 0.3);
}

.badge-unverified {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

html.dark .badge-unverified {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
}

.badge-social {
  background: #f8fafc;
  color: #334155;
  border: 1px solid #e2e8f0;
}

html.dark .badge-social {
  background: rgba(148, 163, 184, 0.15);
  color: #cbd5e1;
  border-color: rgba(148, 163, 184, 0.25);
}

.athlete-display-name {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
  margin: 0;
  line-height: 1.2;
}

.athlete-email {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted);
  margin: 0;
}

.unverified-callout {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #ef4444;
  margin-top: 4px;
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

/* Horizontal Tabs */
.stitch-tabs-bar {
  display: flex;
  overflow-x: auto;
  border-top: 1px solid var(--outline);
  background: var(--surface);
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

.tab-btn {
  flex: 1;
  min-width: max-content;
  padding: 14px 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: all 0.2s ease;
}

.tab-btn:hover {
  color: var(--ink);
  background: rgba(255, 255, 255, 0.04);
}

.tab-btn.active {
  color: var(--ink);
  border-bottom-color: #ffb6c5;
  font-weight: 700;
}

html.dark .tab-btn.active {
  border-bottom-color: #f8b4c4;
}

.mini-tag {
  padding: 1px 6px;
  border-radius: 9999px;
  background: #ffb6c5;
  color: #1a1a1a;
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
}

/* Alerts */
.stitch-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}

.alert-success {
  background: rgba(34, 197, 94, 0.12);
  border: 1px solid rgba(34, 197, 94, 0.3);
  color: #4ade80;
}

.alert-error {
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #f87171;
}

/* 2. MAIN CARD PANE */
.stitch-main-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

.pane-body {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.pane-heading {
  margin-bottom: 4px;
}

.heading-title {
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
  margin: 0 0 4px;
}

.heading-sub {
  font-size: 13px;
  color: var(--muted);
  margin: 0;
}

/* Minimalist Inputs */
.stitch-form {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

@media (min-width: 640px) {
  .form-grid-2 {
    grid-template-columns: 1fr 1fr;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.full-width {
  grid-column: 1 / -1;
}

.minimal-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.req {
  color: #ef4444;
}

.minimal-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.minimal-input-icon {
  position: absolute;
  left: 0;
  font-size: 18px;
  color: var(--muted);
  pointer-events: none;
}

.minimal-input {
  width: 100%;
  border: none;
  border-bottom: 2px solid var(--outline);
  border-radius: 0;
  background: transparent;
  padding: 8px 10px 8px 30px;
  font-size: 14px;
  color: var(--ink);
  transition: border-color 0.2s ease;
}

.minimal-input:focus {
  outline: none;
  border-bottom-color: #ffb6c5;
}

.disabled-input {
  color: var(--muted);
  cursor: not-allowed;
  border-bottom-style: dashed;
}

.toggle-eye-btn {
  position: absolute;
  right: 0;
  background: transparent;
  border: none;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.toggle-eye-btn:hover {
  color: var(--ink);
}

/* Multi-sport Chips Grid */
.sport-pref-section {
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  border-radius: 14px;
  padding: 16px 18px;
}

.pref-label-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-bottom: 12px;
}

.pref-sub-hint {
  font-size: 12px;
  color: var(--muted);
}

.sports-chips-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.sport-chip-card {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 9999px;
  border: 1px solid var(--border, #e2e8f0);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.sport-chip-card:hover {
  border-color: var(--pink, #ffd1dc);
  background: var(--pink-light, #fff5f7);
  transform: translateY(-1px);
}

.sport-chip-card.active {
  background: var(--nav-active-bg, #78555e);
  color: var(--nav-active-text, #ffffff);
  border-color: var(--pink-dark, #78555e);
  box-shadow: 0 3px 10px rgba(120, 85, 94, 0.2);
}

.sport-chip-emoji {
  font-size: 15px;
}

.chip-check-icon {
  font-size: 16px !important;
  color: inherit;
}

/* Skill Cards Grid */
.skill-cards-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-top: 6px;
}

@media (min-width: 640px) {
  .skill-cards-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.skill-card-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 16px 12px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
}

.skill-card-box:hover {
  background: var(--surface-subtle);
  border-color: #ffb6c5;
}

.skill-card-box.active {
  background: #ffe8ed;
  border-color: #ffb6c5;
}

html.dark .skill-card-box.active {
  background: rgba(255, 182, 197, 0.15);
  border-color: #f8b4c4;
}

.skill-ico {
  font-size: 26px !important;
}

.text-green {
  color: #22c55e;
}

.text-orange {
  color: #f97316;
}

.text-yellow {
  color: #eab308;
}

.text-gold {
  color: #f59e0b;
}

.text-brand {
  color: #f43f5e;
}

.skill-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

/* Form Actions */
.form-actions-bar {
  display: flex;
  justify-content: flex-end;
  padding-top: 16px;
  border-top: 1px solid var(--outline);
}

.stitch-cta-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 32px;
  border-radius: 10px;
  background: #ffd1dc;
  color: #1a1a1a;
  border: none;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(255, 182, 197, 0.3);
}

.stitch-cta-btn:hover:not(:disabled) {
  background: #ffb6c5;
  transform: translateY(-1px);
}

html.dark .stitch-cta-btn {
  background: #ffb6c5;
  color: #121212;
}

html.dark .stitch-cta-btn:hover:not(:disabled) {
  background: #ffa4b6;
}

.stitch-cta-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spin-loader {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(0, 0, 0, 0.2);
  border-top-color: #1a1a1a;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Social Setup Banner */
.social-alert-card {
  padding: 18px 20px;
  border-radius: 12px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
}

html.dark .social-alert-card {
  background: rgba(148, 163, 184, 0.12);
  border-color: rgba(148, 163, 184, 0.25);
}

.social-pill-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 2px 8px;
  border-radius: 9999px;
  font-size: 10px;
  font-weight: 800;
  background: #e0f2fe;
  color: #0369a1;
  text-transform: uppercase;
}

html.dark .social-pill-badge {
  background: rgba(14, 165, 233, 0.2);
  color: #7dd3fc;
}

.social-alert-title {
  font-size: 15px;
  font-weight: 800;
  color: #0369a1;
  margin: 6px 0 2px;
}

html.dark .social-alert-title {
  color: #7dd3fc;
}

.social-alert-sub {
  font-size: 12px;
  color: #0284c7;
  margin: 0;
}

html.dark .social-alert-sub {
  color: #94a3b8;
}

/* Meter Bar */
.meter-bar-track {
  width: 100%;
  height: 4px;
  background: var(--outline);
  border-radius: 2px;
  overflow: hidden;
  margin-top: 4px;
}

.meter-bar-fill {
  height: 100%;
  transition: width 0.3s ease, background-color 0.3s ease;
}

.meter-bar-fill.weak {
  background: #ef4444;
}

.meter-bar-fill.med {
  background: #f59e0b;
}

.meter-bar-fill.strong {
  background: #10b981;
}

/* Match Pill */
.match-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  color: #ef4444;
  margin-top: 4px;
}

.match-pill.matched {
  color: #10b981;
}

/* VIP Card */
.vip-soft-card {
  border-radius: 16px;
  padding: 24px;
  background: linear-gradient(135deg, rgba(255, 209, 220, 0.25) 0%, rgba(255, 182, 197, 0.1) 100%);
  border: 1px solid rgba(255, 182, 197, 0.4);
}

.vip-row-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.vip-soft-tag {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  padding: 4px 10px;
  border-radius: 9999px;
  background: #1a1a1a;
  color: #ffffff;
}

.vip-soft-tier {
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.vip-row-metrics {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.metric-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.metric-label {
  font-size: 11px;
  color: var(--muted);
  font-weight: 600;
}

.metric-val {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink);
}

/* Empty State */
.empty-soft-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  text-align: center;
  border: 1px dashed var(--outline);
  border-radius: 16px;
  gap: 12px;
}

.empty-icon-wrap {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: rgba(255, 209, 220, 0.3);
  display: grid;
  place-items: center;
  margin-bottom: 4px;
}

.empty-h {
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
  margin: 0;
}

.empty-p {
  font-size: 13px;
  color: var(--muted);
  max-width: 380px;
  margin: 0;
}

.stitch-outline-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 24px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  margin-top: 8px;
}

.stitch-outline-btn:hover {
  background: var(--surface-subtle);
  border-color: #ffb6c5;
}

/* Shortcuts Grid */
.shortcuts-soft-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

@media (min-width: 640px) {
  .shortcuts-soft-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.shortcut-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  border-radius: 14px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  text-decoration: none;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: left;
}

.shortcut-card:hover {
  background: var(--surface);
  border-color: #ffb6c5;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.shortcut-icon-circle {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: var(--surface);
  border: 1px solid var(--outline);
  display: grid;
  place-items: center;
  color: var(--ink);
  flex-shrink: 0;
}

.shortcut-text {
  flex: 1;
}

.shortcut-title {
  font-size: 14px;
  font-weight: 700;
  margin: 0 0 2px;
  color: var(--ink);
}

.shortcut-desc {
  font-size: 12px;
  color: var(--muted);
  margin: 0;
}

.shortcut-chevron {
  color: var(--muted);
  font-size: 20px;
}

.admin-border {
  border-color: rgba(234, 88, 12, 0.3);
}

.danger-card {
  background: rgba(239, 68, 68, 0.04);
  border-color: rgba(239, 68, 68, 0.2);
}

.danger-card:hover {
  background: rgba(239, 68, 68, 0.08);
  border-color: #ef4444;
}

.danger-icon {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.2);
}
</style>
