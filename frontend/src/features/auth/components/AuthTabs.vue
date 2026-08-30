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
  background: var(--surface-subtle, #FAF7F3);
  border: 1px solid var(--border, #ebdccb);
  padding: 4px;
  border-radius: 14px;
  margin-bottom: 22px;
}

html.dark .auth-tabs {
  background: #251f23;
  border-color: #382f35;
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
  color: var(--muted, #6e6668);
  background: transparent;
  border: 1px solid transparent;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-tab-btn:hover:not(.active) {
  color: var(--ink, #191c1d);
}

.auth-tab-btn.active {
  background: #ffffff;
  color: #6B2D3F;
  border-color: rgba(107, 45, 63, 0.15);
  box-shadow: 0 2px 10px rgba(107, 45, 63, 0.08);
}

html.dark .auth-tab-btn.active {
  background: #2f252a;
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.25);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

.tab-icon {
  font-size: 18px;
}
</style>

