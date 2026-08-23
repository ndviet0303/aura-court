<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/lib/api'
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
    await auth.forgotPassword(email.value)
    notice.value = t('auth.sendResetLink')
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="card form">
    <h1>{{ t('auth.forgotPassword') }}</h1>
    <p class="muted">{{ t('auth.loginSubtitle') }}</p>
    <form @submit.prevent="submit">
      <div class="field">
        <label>{{ t('auth.email') }}</label>
        <input v-model="email" type="email" autocomplete="email" required />
      </div>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="notice" class="notice">{{ notice }}</p>
      <button class="btn" :disabled="loading">
        {{ loading ? t('common.submitting') : t('auth.sendResetLink') }}
      </button>
    </form>
    <RouterLink to="/login" class="muted" style="display:inline-block;margin-top:18px">
      {{ t('auth.alreadyHaveAccount') }}
    </RouterLink>
  </section>
</template>
