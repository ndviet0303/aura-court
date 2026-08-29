<script setup lang="ts">
import { RouterLink } from 'vue-router'

defineProps<{
  email: string
  password: string
  rememberMe: boolean
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'update:email', val: string): void
  (e: 'update:password', val: string): void
  (e: 'update:rememberMe', val: boolean): void
  (e: 'submit'): void
}>()
</script>

<template>
  <form class="auth-form" @submit.prevent="emit('submit')">
    <div class="form-group">
      <label class="form-label">Email tài khoản:</label>
      <input
        :value="email"
        name="email"
        type="email"
        required
        class="form-input"
        placeholder="tenban@example.com"
        @input="emit('update:email', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <div class="form-group">
      <div class="label-with-link">
        <label class="form-label">Mật khẩu:</label>
        <RouterLink to="/forgot-password" class="forgot-link">Quên mật khẩu?</RouterLink>
      </div>
      <input
        :value="password"
        name="password"
        type="password"
        required
        class="form-input"
        placeholder="••••••••"
        @input="emit('update:password', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <button type="submit" class="auth-submit-btn" :disabled="loading">
      <span v-if="loading">Đang đăng nhập...</span>
      <span v-else>Đăng Nhập Vào Aura</span>
    </button>
  </form>
</template>

<style scoped>
.auth-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.label-with-link {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.forgot-link {
  font-size: 12px;
  color: var(--pink-dark, #db2777);
  text-decoration: none;
}

.auth-submit-btn {
  padding: 12px;
  border-radius: 12px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(219, 39, 119, 0.25);
}
</style>
