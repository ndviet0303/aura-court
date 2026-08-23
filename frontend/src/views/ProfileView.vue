<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, errorMessage } from '@/lib/api'
import type { Membership } from '@/types'
import { useAuthStore } from '@/stores/auth'
import { formatDate } from '@/i18n'

const { t } = useI18n()
const auth = useAuthStore()
const name = ref(auth.user?.name || '')
const currentPassword = ref('')
const newPassword = ref('')
const passwordConfirmation = ref('')
const membership = ref<Membership | null>(null)
const error = ref('')
const notice = ref('')
const loading = ref(false)
const passwordLoading = ref(false)
const verificationLoading = ref(false)

async function submit() {
  loading.value = true
  error.value = ''
  notice.value = ''
  try {
    await auth.updateProfile(name.value)
    notice.value = t('profile.saveSuccess')
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}

async function changePassword() {
  passwordLoading.value = true
  error.value = ''
  notice.value = ''
  try {
    await auth.changePassword(currentPassword.value, newPassword.value, passwordConfirmation.value)
    currentPassword.value = ''
    newPassword.value = ''
    passwordConfirmation.value = ''
    notice.value = t('profile.saveSuccess')
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    passwordLoading.value = false
  }
}

async function resendVerification() {
  verificationLoading.value = true
  error.value = ''
  try {
    await auth.resendVerification()
    notice.value = t('common.success')
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    verificationLoading.value = false
  }
}

onMounted(async () => {
  try {
    membership.value = (await api.get<{ membership: Membership | null }>('/membership')).membership
  } catch {
    /* profile remains usable when promotion data is unavailable */
  }
})
</script>

<template>
  <section class="card form">
    <div class="eyebrow">{{ t('profile.eyebrow') }}</div>
    <h1>{{ t('profile.title') }}</h1>
    <p class="muted">{{ auth.user?.email }}</p>
    <p v-if="!auth.user?.email_verified_at" class="notice">
      Email unverified.
      <button type="button" class="link-button" :disabled="verificationLoading" @click="resendVerification">
        {{ verificationLoading ? t('common.loading') : 'Resend' }}
      </button>
    </p>
    <div v-if="membership" class="notice">
      <strong>VIP {{ membership.plan.name }}</strong><br />
      -{{ membership.plan.discount_percent }}% -> {{ formatDate(membership.ends_at) }}.
    </div>
    <form @submit.prevent="submit">
      <div class="field">
        <label>{{ t('auth.name') }}</label>
        <input v-model="name" required maxlength="120" />
      </div>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="notice" class="notice">{{ notice }}</p>
      <button class="btn" :disabled="loading">
        {{ loading ? t('common.loading') : t('common.save') }}
      </button>
    </form>
    <hr style="margin: 24px 0; border: 0; border-top: 1px solid var(--outline);" />
    <h2>{{ t('auth.resetPassword') }}</h2>
    <form @submit.prevent="changePassword">
      <div class="field">
        <label>{{ t('auth.password') }}</label>
        <input v-model="currentPassword" type="password" minlength="8" required />
      </div>
      <div class="field">
        <label>{{ t('auth.password') }} (New)</label>
        <input v-model="newPassword" type="password" minlength="8" required />
      </div>
      <div class="field">
        <label>{{ t('auth.passwordConfirm') }}</label>
        <input v-model="passwordConfirmation" type="password" minlength="8" required />
      </div>
      <button class="btn secondary" :disabled="passwordLoading">
        {{ passwordLoading ? t('common.loading') : t('auth.resetPassword') }}
      </button>
    </form>
  </section>
</template>
