<script setup lang="ts">
defineProps<{
  mode: 'login' | 'register'
}>()

const emit = defineEmits<{
  (e: 'update:mode', val: 'login' | 'register'): void
}>()
</script>

<template>
  <div class="auth-tabs-row">
    <button
      type="button"
      class="auth-tab-btn"
      :class="{ active: mode === 'login' }"
      @click="emit('update:mode', 'login')"
    >
      Đăng Nhập
    </button>
    <button
      type="button"
      class="auth-tab-btn"
      :class="{ active: mode === 'register' }"
      @click="emit('update:mode', 'register')"
    >
      Đăng Ký Tài Khoản
    </button>
  </div>
</template>

<style scoped>
.auth-tabs-row {
  display: flex;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  padding: 4px;
  border-radius: 12px;
  margin-bottom: 20px;
}

.auth-tab-btn {
  flex: 1;
  padding: 10px;
  border-radius: 8px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.auth-tab-btn.active {
  background: var(--surface);
  color: var(--pink-dark, #db2777);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}
</style>
