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

// Membership
const membership = ref<Membership | null>(null)

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

const playLevelLabel = computed(() => {
  switch (auth.user?.play_level) {
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
    await auth.changePassword(currentPassword.value, newPassword.value, passwordConfirmation.value)
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
    membership.value = (await api.get<{ membership: Membership | null }>('/membership')).membership
  } catch {
    // optional
  }
})
</script>

<template>
  <div class="profile-page-wrapper">
    <!-- Background Glow Aura -->
    <div class="profile-glow-blob" aria-hidden="true"></div>

    <div class="profile-container">
      <!-- Top Athlete Banner -->
      <section class="athlete-banner card">
        <div class="banner-content">
          <div class="avatar-column">
            <div class="athlete-avatar">
              <span class="avatar-text">{{ userInitials }}</span>
              <div class="avatar-glow"></div>
            </div>
          </div>

          <div class="info-column">
            <div class="athlete-meta-header">
              <span class="role-badge" :class="auth.user?.role">
                <span v-if="auth.user?.role === 'admin'" class="badge-icon">👑</span>
                <span v-else class="badge-icon">🏸</span>
                {{ auth.user?.role === 'admin' ? t('profile.roleAdmin') : t('profile.roleCustomer') }}
              </span>

              <span v-if="auth.user?.play_level" class="level-badge">
                🎯 {{ playLevelLabel }}
              </span>

              <span v-if="auth.user?.email_verified_at" class="verified-badge">
                <svg viewBox="0 0 20 20" fill="currentColor" class="icon-svg">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
                {{ t('profile.emailVerified') }}
              </span>
              <span v-else class="unverified-badge">
                ⚠️ {{ t('profile.emailUnverified') }}
              </span>
            </div>

            <h1 class="athlete-name">{{ auth.user?.name || 'Athlete' }}</h1>
            <p class="athlete-email">{{ auth.user?.email }}</p>

            <div v-if="!auth.user?.email_verified_at" class="verify-action-box">
              <span>{{ t('profile.emailUnverified') }}.</span>
              <button
                type="button"
                class="btn-resend-verify"
                :disabled="verificationLoading"
                @click="resendVerification"
              >
                {{ verificationLoading ? t('common.loading') : t('profile.resendVerification') }}
              </button>
            </div>
          </div>

          <!-- Quick Stats / VIP summary pill -->
          <div class="vip-summary-column" v-if="membership">
            <div class="vip-mini-card">
              <div class="vip-tag">VIP {{ membership.plan.name }}</div>
              <div class="vip-discount">-{{ membership.plan.discount_percent }}% OFF</div>
              <div class="vip-expiry">{{ formatDate(membership.ends_at) }}</div>
            </div>
          </div>
        </div>

        <!-- Tab Navigation Bar -->
        <nav class="profile-tabs-nav" aria-label="Profile navigation">
          <button
            type="button"
            class="tab-item"
            :class="{ active: activeTab === 'general' }"
            @click="activeTab = 'general'"
          >
            <span class="tab-icon">👤</span>
            {{ t('profile.tabGeneral') }}
          </button>
          <button
            type="button"
            class="tab-item"
            :class="{ active: activeTab === 'security' }"
            @click="activeTab = 'security'"
          >
            <span class="tab-icon">🔒</span>
            {{ t('profile.tabSecurity') }}
          </button>
          <button
            type="button"
            class="tab-item"
            :class="{ active: activeTab === 'membership' }"
            @click="activeTab = 'membership'"
          >
            <span class="tab-icon">⭐</span>
            {{ t('profile.tabMembership') }}
          </button>
          <button
            type="button"
            class="tab-item"
            :class="{ active: activeTab === 'shortcuts' }"
            @click="activeTab = 'shortcuts'"
          >
            <span class="tab-icon">⚡</span>
            {{ t('profile.tabQuickLinks') }}
          </button>
        </nav>
      </section>

      <!-- Global Notice / Error Alerts -->
      <div v-if="notice" class="alert-box alert-success" role="alert">
        <svg viewBox="0 0 20 20" fill="currentColor" class="alert-icon">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
        </svg>
        <span>{{ notice }}</span>
      </div>

      <div v-if="error" class="alert-box alert-error" role="alert">
        <svg viewBox="0 0 20 20" fill="currentColor" class="alert-icon">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
        </svg>
        <span>{{ error }}</span>
      </div>

      <!-- TAB 1: General Info -->
      <section v-if="activeTab === 'general'" class="profile-card card animate-fade-in">
        <div class="card-header">
          <h2 class="card-title">{{ t('profile.tabGeneral') }}</h2>
          <p class="card-subtitle">{{ t('profile.subtitle') }}</p>
        </div>

        <form @submit.prevent="updateGeneralProfile" class="profile-form">
          <div class="form-grid">
            <!-- Full Name -->
            <div class="form-group">
              <label for="p-name" class="form-label">{{ t('profile.fullName') }} <span class="required">*</span></label>
              <div class="input-container">
                <span class="input-icon">👤</span>
                <input
                  id="p-name"
                  v-model="name"
                  type="text"
                  required
                  maxlength="120"
                  class="profile-input"
                  :placeholder="t('auth.namePlaceholder')"
                />
              </div>
            </div>

            <!-- Phone Number -->
            <div class="form-group">
              <label for="p-phone" class="form-label">{{ t('profile.phone') }}</label>
              <div class="input-container">
                <span class="input-icon">📞</span>
                <input
                  id="p-phone"
                  v-model="phone"
                  type="tel"
                  maxlength="30"
                  class="profile-input"
                  :placeholder="t('profile.phonePlaceholder')"
                />
              </div>
            </div>

            <!-- Email (Read-only) -->
            <div class="form-group full-width">
              <label class="form-label">{{ t('auth.email') }}</label>
              <div class="input-container disabled-container">
                <span class="input-icon">✉️</span>
                <input
                  type="email"
                  :value="auth.user?.email"
                  disabled
                  class="profile-input disabled-input"
                />
              </div>
            </div>

            <!-- Play Level Selection -->
            <div class="form-group full-width">
              <label class="form-label">{{ t('profile.playLevel') }}</label>
              <div class="play-level-grid">
                <label
                  class="level-card"
                  :class="{ active: playLevel === 'beginner' }"
                >
                  <input type="radio" v-model="playLevel" value="beginner" class="sr-only" />
                  <span class="level-icon">🌱</span>
                  <span class="level-name">{{ t('profile.levelBeginner') }}</span>
                </label>

                <label
                  class="level-card"
                  :class="{ active: playLevel === 'intermediate' }"
                >
                  <input type="radio" v-model="playLevel" value="intermediate" class="sr-only" />
                  <span class="level-icon">🏸</span>
                  <span class="level-name">{{ t('profile.levelIntermediate') }}</span>
                </label>

                <label
                  class="level-card"
                  :class="{ active: playLevel === 'advanced' }"
                >
                  <input type="radio" v-model="playLevel" value="advanced" class="sr-only" />
                  <span class="level-icon">⚡</span>
                  <span class="level-name">{{ t('profile.levelAdvanced') }}</span>
                </label>

                <label
                  class="level-card"
                  :class="{ active: playLevel === 'pro' }"
                >
                  <input type="radio" v-model="playLevel" value="pro" class="sr-only" />
                  <span class="level-icon">🏆</span>
                  <span class="level-name">{{ t('profile.levelPro') }}</span>
                </label>
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-primary" :disabled="loading">
              <span v-if="loading" class="spinner-inline"></span>
              {{ loading ? t('common.loading') : t('profile.saveChanges') }}
            </button>
          </div>
        </form>
      </section>

      <!-- TAB 2: Security & Password -->
      <section v-if="activeTab === 'security'" class="profile-card card animate-fade-in">
        <div class="card-header">
          <h2 class="card-title">{{ t('profile.passwordSection') }}</h2>
          <p class="card-subtitle">{{ t('profile.passwordSubtitle') }}</p>
        </div>

        <form @submit.prevent="updatePassword" class="profile-form">
          <div class="form-grid">
            <!-- Current Password -->
            <div class="form-group full-width">
              <label for="p-current-pwd" class="form-label">{{ t('profile.currentPassword') }} <span class="required">*</span></label>
              <div class="input-container">
                <span class="input-icon">🔑</span>
                <input
                  id="p-current-pwd"
                  v-model="currentPassword"
                  :type="showCurrentPassword ? 'text' : 'password'"
                  required
                  minlength="8"
                  class="profile-input"
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  @click="showCurrentPassword = !showCurrentPassword"
                >
                  {{ showCurrentPassword ? '👁️' : '👁️‍🗨️' }}
                </button>
              </div>
            </div>

            <!-- New Password -->
            <div class="form-group">
              <label for="p-new-pwd" class="form-label">{{ t('profile.newPassword') }} <span class="required">*</span></label>
              <div class="input-container">
                <span class="input-icon">🔒</span>
                <input
                  id="p-new-pwd"
                  v-model="newPassword"
                  :type="showNewPassword ? 'text' : 'password'"
                  required
                  minlength="8"
                  class="profile-input"
                  :placeholder="t('auth.passwordPlaceholder')"
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  @click="showNewPassword = !showNewPassword"
                >
                  {{ showNewPassword ? '👁️' : '👁️‍🗨️' }}
                </button>
              </div>

              <!-- Strength Bar -->
              <div v-if="newPassword" class="strength-meter">
                <div class="strength-bar-bg">
                  <div
                    class="strength-bar-fill"
                    :style="{ width: `${passwordStrength}%` }"
                    :class="{
                      weak: passwordStrength <= 25,
                      medium: passwordStrength > 25 && passwordStrength <= 75,
                      strong: passwordStrength > 75,
                    }"
                  ></div>
                </div>
              </div>
            </div>

            <!-- Confirm New Password -->
            <div class="form-group">
              <label for="p-confirm-pwd" class="form-label">{{ t('profile.confirmNewPassword') }} <span class="required">*</span></label>
              <div class="input-container">
                <span class="input-icon">🛡️</span>
                <input
                  id="p-confirm-pwd"
                  v-model="passwordConfirmation"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  required
                  minlength="8"
                  class="profile-input"
                  :placeholder="t('auth.passwordConfirmPlaceholder')"
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  @click="showConfirmPassword = !showConfirmPassword"
                >
                  {{ showConfirmPassword ? '👁️' : '👁️‍🗨️' }}
                </button>
              </div>

              <div v-if="passwordConfirmation" class="match-indicator" :class="{ matched: passwordsMatch }">
                <span>{{ passwordsMatch ? '✓ ' + t('auth.passwordMatch') : '✕ ' + t('auth.passwordsDoNotMatch') }}</span>
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-primary" :disabled="passwordLoading || (newPassword.length > 0 && !passwordsMatch)">
              <span v-if="passwordLoading" class="spinner-inline"></span>
              {{ passwordLoading ? t('common.loading') : t('profile.passwordSection') }}
            </button>
          </div>
        </form>
      </section>

      <!-- TAB 3: VIP Membership -->
      <section v-if="activeTab === 'membership'" class="profile-card card animate-fade-in">
        <div class="card-header">
          <h2 class="card-title">{{ t('profile.vipTitle') }}</h2>
          <p class="card-subtitle">{{ t('profile.vipSubtitle') }}</p>
        </div>

        <div v-if="membership" class="vip-deluxe-card">
          <div class="vip-card-header">
            <div class="vip-shield">AURA VIP</div>
            <div class="vip-plan-title">{{ membership.plan.name }}</div>
          </div>
          <div class="vip-card-body">
            <div class="vip-stat">
              <span class="vip-stat-label">{{ t('profile.vipDiscount') }}</span>
              <span class="vip-stat-val">{{ membership.plan.discount_percent }}%</span>
            </div>
            <div class="vip-stat">
              <span class="vip-stat-label">{{ t('profile.vipExpires') }}</span>
              <span class="vip-stat-val">{{ formatDate(membership.ends_at) }}</span>
            </div>
          </div>
        </div>

        <div v-else class="empty-vip-state">
          <div class="empty-icon">⭐</div>
          <p class="empty-text">{{ t('profile.vipNone') }}</p>
          <RouterLink to="/v2/reservations" class="btn-secondary">
            🏸 {{ t('profile.bookCourtNow') }}
          </RouterLink>
        </div>
      </section>

      <!-- TAB 4: Shortcuts & Management -->
      <section v-if="activeTab === 'shortcuts'" class="profile-card card animate-fade-in">
        <div class="card-header">
          <h2 class="card-title">{{ t('profile.tabQuickLinks') }}</h2>
          <p class="card-subtitle">{{ t('profile.subtitle') }}</p>
        </div>

        <div class="shortcuts-grid">
          <RouterLink to="/bookings" class="shortcut-card">
            <div class="shortcut-icon-box">📅</div>
            <div class="shortcut-text">
              <div class="shortcut-title">{{ t('profile.myBookings') }}</div>
              <div class="shortcut-desc">Xem và quản lý các lịch đặt sân hiện tại</div>
            </div>
            <span class="shortcut-arrow">→</span>
          </RouterLink>

          <RouterLink v-if="auth.user?.role === 'admin'" to="/admin/organization" class="shortcut-card admin-shortcut">
            <div class="shortcut-icon-box">🏢</div>
            <div class="shortcut-text">
              <div class="shortcut-title">{{ t('profile.manageOrg') }}</div>
              <div class="shortcut-desc">Cấu hình cụm sân, thời gian biểu và giá thuê</div>
            </div>
            <span class="shortcut-arrow">→</span>
          </RouterLink>

          <RouterLink to="/v2/reservations" class="shortcut-card">
            <div class="shortcut-icon-box">🏸</div>
            <div class="shortcut-text">
              <div class="shortcut-title">{{ t('profile.bookCourtNow') }}</div>
              <div class="shortcut-desc">Đặt sân online theo thời gian thực</div>
            </div>
            <span class="shortcut-arrow">→</span>
          </RouterLink>

          <button type="button" class="shortcut-card danger-shortcut" @click="handleLogout">
            <div class="shortcut-icon-box">🚪</div>
            <div class="shortcut-text">
              <div class="shortcut-title">{{ t('profile.logoutAccount') }}</div>
              <div class="shortcut-desc">Đăng xuất khỏi phiên làm việc hiện tại</div>
            </div>
            <span class="shortcut-arrow">⏻</span>
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.profile-page-wrapper {
  position: relative;
  min-height: calc(100vh - 120px);
  padding: 32px 16px 64px;
  max-width: 1040px;
  margin: 0 auto;
}

.profile-glow-blob {
  position: absolute;
  top: 10%;
  left: 50%;
  transform: translateX(-50%);
  width: 600px;
  height: 350px;
  background: radial-gradient(circle, rgba(230, 92, 0, 0.08) 0%, rgba(200, 40, 0, 0.03) 50%, transparent 70%);
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}

.profile-container {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Athlete Hero Banner */
.athlete-banner {
  background: linear-gradient(135deg, var(--surface) 0%, var(--surface-card, rgba(20, 20, 25, 0.7)) 100%);
  border: 1px solid var(--outline);
  border-radius: 24px;
  overflow: hidden;
  padding: 0;
  box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.35);
}

.banner-content {
  padding: 32px 32px 24px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

@media (min-width: 768px) {
  .banner-content {
    flex-direction: row;
    align-items: center;
  }
}

.avatar-column {
  display: flex;
  justify-content: center;
}

.athlete-avatar {
  position: relative;
  width: 96px;
  height: 96px;
  border-radius: 28px;
  background: linear-gradient(135deg, #ff7a1a 0%, #e63946 50%, #8338ec 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: 800;
  font-size: 32px;
  letter-spacing: -0.02em;
  box-shadow: 0 10px 25px -5px rgba(230, 57, 70, 0.4);
}

.avatar-glow {
  position: absolute;
  inset: -2px;
  border-radius: 30px;
  background: linear-gradient(135deg, #ff7a1a, transparent 70%);
  opacity: 0.6;
  z-index: -1;
}

.info-column {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.athlete-meta-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 12px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.02em;
  background: rgba(230, 92, 0, 0.15);
  color: #ff8c3a;
  border: 1px solid rgba(230, 92, 0, 0.3);
}

.role-badge.admin {
  background: rgba(131, 56, 236, 0.15);
  color: #b185ff;
  border-color: rgba(131, 56, 236, 0.3);
}

.level-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 600;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.verified-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 600;
  background: rgba(59, 130, 246, 0.12);
  color: #60a5fa;
}

.unverified-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 600;
  background: rgba(239, 68, 68, 0.12);
  color: #f87171;
}

.icon-svg {
  width: 14px;
  height: 14px;
}

.athlete-name {
  font-size: 26px;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  line-height: 1.2;
}

.athlete-email {
  font-size: 14px;
  color: var(--text-muted);
  margin: 0;
}

.verify-action-box {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
  font-size: 12px;
  color: #f87171;
}

.btn-resend-verify {
  background: transparent;
  border: none;
  color: #60a5fa;
  font-size: 12px;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

.vip-summary-column {
  display: flex;
  align-items: center;
}

.vip-mini-card {
  padding: 12px 18px;
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(255, 170, 0, 0.15) 0%, rgba(230, 57, 70, 0.1) 100%);
  border: 1px solid rgba(255, 170, 0, 0.35);
  text-align: center;
}

.vip-tag {
  font-size: 11px;
  font-weight: 800;
  color: #fbbf24;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.vip-discount {
  font-size: 18px;
  font-weight: 900;
  color: #fff;
  margin: 2px 0;
}

.vip-expiry {
  font-size: 10px;
  color: var(--text-muted);
}

/* Tabs Navigation */
.profile-tabs-nav {
  display: flex;
  border-top: 1px solid var(--outline);
  background: rgba(0, 0, 0, 0.15);
  overflow-x: auto;
}

.tab-item {
  flex: 1;
  min-width: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px 14px;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--text-muted);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.tab-item:hover {
  color: var(--text-primary);
  background: rgba(255, 255, 255, 0.03);
}

.tab-item.active {
  color: #ff8c3a;
  border-bottom-color: #ff8c3a;
  background: rgba(255, 140, 58, 0.06);
}

/* Alert Boxes */
.alert-box {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 14px;
  font-size: 14px;
  font-weight: 500;
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

.alert-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

/* Profile Form Card */
.profile-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 24px;
  padding: 32px;
}

.card-header {
  margin-bottom: 24px;
}

.card-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 6px;
}

.card-subtitle {
  font-size: 14px;
  color: var(--text-muted);
  margin: 0;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

@media (min-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-primary);
}

.required {
  color: #ef4444;
}

.input-container {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 14px;
  font-size: 16px;
  pointer-events: none;
}

.profile-input {
  width: 100%;
  padding: 13px 14px 13px 44px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-input, rgba(0, 0, 0, 0.2));
  color: var(--text-primary);
  font-size: 14px;
  transition: all 0.2s ease;
}

.profile-input:focus {
  outline: none;
  border-color: #ff8c3a;
  box-shadow: 0 0 0 3px rgba(255, 140, 58, 0.15);
}

.disabled-container {
  opacity: 0.65;
}

.disabled-input {
  background: rgba(0, 0, 0, 0.1);
  cursor: not-allowed;
}

.password-toggle-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 16px;
  opacity: 0.7;
}

.password-toggle-btn:hover {
  opacity: 1;
}

/* Play Level Selection Grid */
.play-level-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

@media (min-width: 640px) {
  .play-level-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.level-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px 12px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
}

.level-card:hover {
  border-color: rgba(255, 140, 58, 0.4);
  background: rgba(255, 140, 58, 0.04);
}

.level-card.active {
  border-color: #ff8c3a;
  background: rgba(255, 140, 58, 0.12);
  box-shadow: 0 0 15px rgba(255, 140, 58, 0.15);
}

.level-icon {
  font-size: 22px;
}

.level-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--text-primary);
}

/* Strength & Match Meters */
.strength-meter {
  margin-top: 6px;
}

.strength-bar-bg {
  height: 4px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 2px;
  overflow: hidden;
}

.strength-bar-fill {
  height: 100%;
  transition: all 0.3s ease;
}

.strength-bar-fill.weak {
  background: #ef4444;
}

.strength-bar-fill.medium {
  background: #f59e0b;
}

.strength-bar-fill.strong {
  background: #10b981;
}

.match-indicator {
  margin-top: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #f87171;
}

.match-indicator.matched {
  color: #34d399;
}

.form-actions {
  margin-top: 28px;
  display: flex;
  justify-content: flex-end;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 13px 28px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ff7a1a 0%, #e63946 100%);
  color: #fff;
  border: none;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 14px rgba(230, 57, 70, 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(230, 57, 70, 0.45);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.08);
  color: var(--text-primary);
  border: 1px solid var(--outline);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #ff8c3a;
}

.spinner-inline {
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

/* VIP Deluxe Card */
.vip-deluxe-card {
  padding: 28px;
  border-radius: 20px;
  background: linear-gradient(135deg, #1e1b4b 0%, #311042 50%, #451a03 100%);
  border: 1px solid rgba(251, 191, 36, 0.4);
  box-shadow: 0 15px 35px -10px rgba(251, 191, 36, 0.2);
}

.vip-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.vip-shield {
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.1em;
  padding: 6px 14px;
  border-radius: 9999px;
  background: rgba(251, 191, 36, 0.2);
  color: #fbbf24;
  border: 1px solid rgba(251, 191, 36, 0.5);
}

.vip-plan-title {
  font-size: 24px;
  font-weight: 800;
  color: #fff;
}

.vip-card-body {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 20px;
}

.vip-stat-label {
  display: block;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 4px;
}

.vip-stat-val {
  font-size: 20px;
  font-weight: 800;
  color: #fbbf24;
}

.empty-vip-state {
  text-align: center;
  padding: 32px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.empty-icon {
  font-size: 48px;
}

.empty-text {
  font-size: 14px;
  color: var(--text-muted);
  max-width: 420px;
  margin: 0;
}

/* Shortcuts Grid */
.shortcuts-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}

@media (min-width: 640px) {
  .shortcuts-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.shortcut-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  background: var(--surface-input, rgba(0, 0, 0, 0.2));
  border: 1px solid var(--outline);
  text-decoration: none;
  color: inherit;
  transition: all 0.2s ease;
  cursor: pointer;
  width: 100%;
  text-align: left;
}

.shortcut-card:hover {
  transform: translateY(-2px);
  border-color: #ff8c3a;
  background: rgba(255, 140, 58, 0.05);
}

.shortcut-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}

.shortcut-text {
  flex: 1;
}

.shortcut-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 2px;
}

.shortcut-desc {
  font-size: 12px;
  color: var(--text-muted);
}

.shortcut-arrow {
  font-size: 18px;
  color: var(--text-muted);
  transition: transform 0.2s ease;
}

.shortcut-card:hover .shortcut-arrow {
  transform: translateX(4px);
  color: #ff8c3a;
}

.admin-shortcut:hover {
  border-color: #8338ec;
  background: rgba(131, 56, 236, 0.05);
}

.admin-shortcut:hover .shortcut-arrow {
  color: #8338ec;
}

.danger-shortcut:hover {
  border-color: #ef4444;
  background: rgba(239, 68, 68, 0.05);
}

.danger-shortcut:hover .shortcut-arrow {
  color: #ef4444;
}

/* Animations */
.animate-fade-in {
  animation: fadeIn 0.25s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
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
