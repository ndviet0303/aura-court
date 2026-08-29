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
  border: 1px solid var(--outline, #e1e3e4);
  color: var(--ink, #191c1d);
  cursor: pointer;
  padding: 0;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  flex-shrink: 0;
}

.theme-toggle-btn:hover {
  background: var(--pink-light, #ffd9e2);
  border-color: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  transform: translateY(-1px);
  box-shadow: 0 3px 10px rgba(120, 85, 94, 0.12);
}

:global(html.dark) .theme-toggle-btn {
  background: #22242a;
  border-color: #353942;
  color: #f8b4c4;
}

:global(html.dark) .theme-toggle-btn:hover {
  background: #2b2e36;
  border-color: rgba(248, 180, 196, 0.4);
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
  color: #ffd1dc;
}

:global(html.dark) .moon-icon {
  color: #f8b4c4;
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
