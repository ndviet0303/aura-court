<script setup lang="ts">
import { useThemeStore } from '@/stores/theme'

const themeStore = useThemeStore()
</script>

<template>
  <button
    type="button"
    class="theme-toggle-btn"
    :title="$t('common.theme.toggle')"
    :aria-label="$t('common.theme.toggle')"
    @click="themeStore.toggleTheme"
  >
    <div class="icon-container" :class="{ 'is-dark': themeStore.theme === 'dark' }">
      <!-- Sun Icon (Active in Light Mode) -->
      <span class="material-symbols-outlined icon sun-icon">light_mode</span>
      <!-- Moon Icon (Active in Dark Mode) -->
      <span class="material-symbols-outlined icon moon-icon">dark_mode</span>
    </div>
  </button>
</template>

<style scoped>
.theme-toggle-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 999px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #ebdccb);
  color: var(--ink, #191c1d);
  cursor: pointer;
  padding: 0;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  flex-shrink: 0;
}

.theme-toggle-btn:hover {
  background: #FAF7F3;
  border-color: #6B2D3F;
  color: #6B2D3F;
  transform: translateY(-1px);
  box-shadow: 0 3px 10px rgba(107, 45, 63, 0.12);
}

html.dark .theme-toggle-btn {
  background: #1e191c;
  border-color: #382f35;
  color: #dfba72;
}

html.dark .theme-toggle-btn:hover {
  background: #262024;
  border-color: rgba(223, 186, 114, 0.4);
  color: #FAF7F3;
}

.icon-container {
  position: relative;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.icon {
  position: absolute;
  font-size: 19px;
  transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease;
}

/* Light Mode: Sun is visible, Moon rotated out */
.sun-icon {
  opacity: 1;
  transform: rotate(0deg) scale(1);
  color: #e59310;
}

.moon-icon {
  opacity: 0;
  transform: rotate(90deg) scale(0.5);
  color: #dfba72;
}

html.dark .moon-icon {
  color: #dfba72;
}

/* Dark Mode: Moon is visible, Sun rotated out */
.is-dark .sun-icon {
  opacity: 0;
  transform: rotate(-90deg) scale(0.5);
}

.is-dark .moon-icon {
  opacity: 1;
  transform: rotate(0deg) scale(1);
}
</style>

