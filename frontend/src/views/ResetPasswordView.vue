<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const email = ref(String(route.query.email || ''))
const token = ref(String(route.query.token || ''))
const password = ref('')
const confirmation = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.resetPassword(email.value, token.value, password.value, confirmation.value)
    router.push('/login')
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="card form">
    <h1>{{ t('auth.resetPassword') }}</h1>
    <form @submit.prevent="submit">
      <div class="field">
        <label>{{ t('auth.email') }}</label>
        <input v-model="email" type="email" autocomplete="email" required />
      </div>
      <div class="field">
        <label>Token</label>
        <input v-model="token" required />
      </div>
      <div class="field">
        <label>{{ t('auth.password') }}</label>
        <input v-model="password" type="password" minlength="8" required />
      </div>
      <div class="field">
        <label>{{ t('auth.passwordConfirm') }}</label>
        <input v-model="confirmation" type="password" minlength="8" required />
      </div>
      <p v-if="error" class="error">{{ error }}</p>
      <button class="btn" :disabled="loading">
        {{ loading ? t('common.submitting') : t('auth.resetPassword') }}
      </button>
    </form>
  </section>
</template>
