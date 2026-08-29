<script setup lang="ts">
import { useAuthForm } from '../composables/useAuthForm'

import AuthTabs from '../components/AuthTabs.vue'
import LoginForm from '../components/LoginForm.vue'
import RegisterForm from '../components/RegisterForm.vue'
import SocialLoginButtons from '../components/SocialLoginButtons.vue'

const {
  mode,
  loading,
  error,
  notice,
  loginEmail,
  loginPassword,
  rememberMe,
  regName,
  regEmail,
  regPassword,
  regPasswordConfirmation,
  handleLogin,
  handleRegister,
} = useAuthForm()

function handleSocialGoogle() {
  window.location.href = '/api/v1/auth/google'
}

function handleSocialFacebook() {
  window.location.href = '/api/v1/auth/facebook'
}
</script>

<template>
  <div class="auth-view-container">
    <div class="auth-card">
      <div class="auth-brand-header">
        <span class="brand-logo">🏸</span>
        <h1 class="auth-title">AURA BADMINTON</h1>
        <p class="auth-sub">Hệ thống đặt sân cầu lông và quản lý CLB thông minh số 1</p>
      </div>

      <!-- Feedback Alerts -->
      <div v-if="notice" class="alert-pill">{{ notice }}</div>
      <div v-if="error" class="alert-pill-error">{{ error }}</div>

      <!-- Tabs (Login vs Register) -->
      <AuthTabs
        :mode="mode"
        @update:mode="mode = $event"
      />

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

      <!-- Social Logins -->
      <SocialLoginButtons
        @google="handleSocialGoogle"
        @facebook="handleSocialFacebook"
      />
    </div>
  </div>
</template>

<style scoped>
.auth-view-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 120px);
  padding: 40px 20px;
}

.auth-card {
  width: 100%;
  max-width: 440px;
  padding: 32px;
  border-radius: 24px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.06);
}

.auth-brand-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 6px;
  margin-bottom: 24px;
}

.brand-logo {
  font-size: 36px;
}

.auth-title {
  margin: 0;
  font-size: 20px;
  font-weight: 900;
  letter-spacing: 0.5px;
}

.auth-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.alert-pill {
  padding: 8px 14px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 16px;
  text-align: center;
}

.alert-pill-error {
  padding: 8px 14px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 16px;
  text-align: center;
}
</style>
