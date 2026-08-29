<script setup lang="ts">
defineProps<{
  name: string
  email: string
  password: string
  passwordConfirmation: string
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'update:name', val: string): void
  (e: 'update:email', val: string): void
  (e: 'update:password', val: string): void
  (e: 'update:passwordConfirmation', val: string): void
  (e: 'submit'): void
}>()
</script>

<template>
  <form class="auth-form" @submit.prevent="emit('submit')">
    <div class="form-group">
      <label class="form-label">Họ và tên của bạn:</label>
      <input
        :value="name"
        name="name"
        type="text"
        required
        class="form-input"
        placeholder="Nguyễn Văn A"
        @input="emit('update:name', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <div class="form-group">
      <label class="form-label">Địa chỉ email:</label>
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
      <label class="form-label">Mật khẩu mới:</label>
      <input
        :value="password"
        name="password"
        type="password"
        required
        class="form-input"
        placeholder="Tối thiểu 8 ký tự"
        @input="emit('update:password', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <div class="form-group">
      <label class="form-label">Xác nhận lại mật khẩu:</label>
      <input
        :value="passwordConfirmation"
        name="password_confirmation"
        type="password"
        required
        class="form-input"
        placeholder="••••••••"
        @input="emit('update:passwordConfirmation', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <button type="submit" class="auth-submit-btn" :disabled="loading">
      <span v-if="loading">Đang đăng ký...</span>
      <span v-else>Tạo Tài Khoản Aura Mới</span>
    </button>
  </form>
</template>

<style scoped>
.auth-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
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
