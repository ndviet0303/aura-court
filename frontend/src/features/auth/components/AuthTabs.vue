<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  mode: 'login' | 'register'
}>()

const emit = defineEmits<{
  (e: 'update:mode', val: 'login' | 'register'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="auth-tabs" role="tablist">
    <button
      type="button"
      class="auth-tab-btn"
      :class="{ active: mode === 'login' }"
      role="tab"
      :aria-selected="mode === 'login'"
      @click="emit('update:mode', 'login')"
    >
      <span class="material-symbols-outlined tab-icon">login</span>
      <span>{{ t('auth.loginTitle') }}</span>
    </button>
    <button
      type="button"
      class="auth-tab-btn"
      :class="{ active: mode === 'register' }"
      role="tab"
      :aria-selected="mode === 'register'"
      @click="emit('update:mode', 'register')"
    >
      <span class="material-symbols-outlined tab-icon">person_add</span>
      <span>{{ t('auth.registerTitle') }}</span>
    </button>
  </div>
</template>

<style scoped>
.auth-tabs {
  display: flex;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--border, var(--outline, #e2e8f0));
  padding: 4px;
  border-radius: 14px;
  margin-bottom: 22px;
}

.auth-tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 9px 16px;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--muted, #64748b);
  background: transparent;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-tab-btn:hover:not(.active) {
  color: var(--ink, #0f172a);
}

.auth-tab-btn.active {
  background: var(--surface, #ffffff);
  color: var(--pink-dark, #db2777);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

html.dark .auth-tab-btn.active {
  color: #f8b4c4;
}

.tab-icon {
  font-size: 18px;
}
</style>
