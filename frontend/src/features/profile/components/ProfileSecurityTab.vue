<script setup lang="ts">
const props = defineProps<{
  user: any
  currentPassword: string
  newPassword: string
  confirmPassword: string
}>()

const emit = defineEmits<{
  (e: 'update:currentPassword', val: string): void
  (e: 'update:newPassword', val: string): void
  (e: 'update:confirmPassword', val: string): void
  (e: 'save'): void
}>()
</script>

<template>
  <div class="profile-tab-card">
    <h3 class="tab-title">🔒 Bảo Mật Tài Khoản & Mật Khẩu</h3>

    <div v-if="user?.social_provider" class="social-alert-card">
      <span>🌐 Đăng nhập qua {{ user.social_provider }}. Bạn có thể thiết lập mật khẩu riêng để đăng nhập bằng email.</span>
    </div>

    <form class="security-form" @submit.prevent="emit('save')">
      <!-- Current Password field (shown if password is set) -->
      <div v-if="user?.password_set !== false" class="form-group">
        <label class="form-label" for="f-curr-pwd">Mật khẩu hiện tại:</label>
        <input
          id="f-curr-pwd"
          :value="currentPassword"
          type="password"
          class="form-input"
          placeholder="••••••••"
          @input="emit('update:currentPassword', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="form-group">
        <label class="form-label" for="f-new-pwd">Mật khẩu mới:</label>
        <input
          id="f-new-pwd"
          :value="newPassword"
          type="password"
          class="form-input"
          placeholder="••••••••"
          @input="emit('update:newPassword', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="form-group">
        <label class="form-label" for="f-conf-pwd">Xác nhận mật khẩu mới:</label>
        <input
          id="f-conf-pwd"
          :value="confirmPassword"
          type="password"
          class="form-input"
          placeholder="••••••••"
          @input="emit('update:confirmPassword', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-save" :disabled="!newPassword">
          🔒 Cập Nhật Mật Khẩu
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.profile-tab-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.tab-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.security-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  max-width: 500px;
}

.form-actions {
  display: flex;
  justify-content: flex-start;
  margin-top: 6px;
}

.btn-save {
  padding: 10px 24px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
