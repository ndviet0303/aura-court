<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/api'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const mode = ref<'login'|'register'>('login')
const name = ref('')
const email = ref('')
const password = ref('')
const confirmation = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  error.value = ''
  try {
    if (mode.value === 'login') await auth.login(email.value, password.value)
    else await auth.register(name.value, email.value, password.value, confirmation.value)
    router.push(String(route.query.redirect || '/'))
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('auth.invalidCredentials'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="card form">
    <h1>{{ mode === 'login' ? t('auth.loginTitle') : t('auth.registerTitle') }}</h1>
    <p class="muted">{{ t('auth.loginSubtitle') }}</p>
    <form @submit.prevent="submit">
      <div v-if="mode === 'register'" class="field">
        <label for="name">{{ t('auth.name') }}</label>
        <input id="name" name="name" v-model="name" autocomplete="name" required />
      </div>
      <div class="field">
        <label for="email">{{ t('auth.email') }}</label>
        <input id="email" name="email" v-model="email" type="email" autocomplete="email" required />
      </div>
      <div class="field">
        <label for="password">{{ t('auth.password') }}</label>
        <input id="password" name="password" v-model="password" type="password" autocomplete="current-password" minlength="8" required />
      </div>
      <div v-if="mode === 'register'" class="field">
        <label for="password-confirmation">{{ t('auth.passwordConfirm') }}</label>
        <input id="password-confirmation" name="password_confirmation" v-model="confirmation" type="password" autocomplete="new-password" minlength="8" required />
      </div>
      <p v-if="error" class="error">{{ error }}</p>
      <button class="btn" :disabled="loading">
        {{ mode === 'login' ? t('auth.loginBtn') : t('auth.registerBtn') }}
      </button>
    </form>
    <p v-if="mode === 'login'" class="muted demo-request-note">
      {{ t('auth.demoAccountNotice') }}
    </p>
    <RouterLink v-if="mode === 'login'" to="/forgot-password" class="muted" style="display:inline-block;margin-top:16px">
      {{ t('auth.forgotPassword') }}
    </RouterLink>
    <p>
      <button type="button" class="link-button" style="color:#0e6b50" @click="mode = mode === 'login' ? 'register' : 'login'">
        {{ mode === 'login' ? t('auth.dontHaveAccount') : t('auth.alreadyHaveAccount') }}
      </button>
    </p>
  </section>
</template>
