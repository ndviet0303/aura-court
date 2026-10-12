<script setup lang="ts">
import { watch } from 'vue'
import { ThemeToggle, LanguageSwitcher } from '@/shared/components'
import NavbarBrand from './NavbarBrand.vue'

import NavbarDrawerUserCard from './NavbarDrawerUserCard.vue'
import NavbarDrawerNavList from './NavbarDrawerNavList.vue'

interface Props {
  open: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'logout'): void
}>()

function close() {
  emit('update:open', false)
}

function handleLogout() {
  close()
  emit('logout')
}

watch(
  () => props.open,
  (isOpen) => {
    if (typeof document !== 'undefined') {
      document.body.style.overflow = isOpen ? 'hidden' : ''
    }
  },
)
</script>

<template>
  <Teleport to="body">
    <!-- Backdrop -->
    <Transition name="drawer-fade">
      <div v-if="open" class="mobile-drawer-backdrop" @click="close" aria-hidden="true"></div>
    </Transition>

    <!-- Slide-over Panel -->
    <Transition name="drawer-slide">
      <aside
        v-if="open"
        class="mobile-drawer-panel"
        role="dialog"
        aria-modal="true"
        aria-label="Mobile Navigation"
      >
        <!-- Drawer Header -->
        <div class="drawer-header">
          <NavbarBrand show-subtitle @click="close" />
          <button
            type="button"
            class="drawer-close-btn"
            @click="close"
            aria-label="Close navigation"
          >
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <!-- User Card in Drawer -->
        <NavbarDrawerUserCard @navigate="close" @logout="handleLogout" />

        <!-- Drawer Navigation Scrollable List -->
        <NavbarDrawerNavList @navigate="close" />

        <!-- Drawer Footer Utilities -->
        <div class="drawer-footer">
          <div class="drawer-util-card">
            <div class="drawer-util-item">
              <span class="util-text">{{ $t('nav.theme') }}</span>
              <ThemeToggle />
            </div>
            <div class="drawer-util-divider"></div>
            <div class="drawer-util-item">
              <span class="util-text">{{ $t('nav.language') }}</span>
              <LanguageSwitcher />
            </div>
          </div>
          <div class="drawer-subtext">
            <span>Aura Court · Multi-Sport Hub</span>
          </div>
        </div>
      </aside>
    </Transition>
  </Teleport>
</template>

<style scoped>
.mobile-drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 12, 18, 0.65);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 10000;
}

.mobile-drawer-panel {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(340px, 86vw);
  height: 100dvh;
  max-height: 100dvh;
  background: var(--surface, #ffffff);
  color: var(--ink, #1c1618);
  z-index: 10001;
  display: flex;
  flex-direction: column;
  box-shadow: -10px 0 36px rgba(0, 0, 0, 0.28);
  border-left: 1px solid var(--outline, #dcd3c5);
  box-sizing: border-box;
  overflow: hidden;
}

html.dark .mobile-drawer-panel {
  background: #191618;
  border-color: #382f35;
  box-shadow: -10px 0 40px rgba(0, 0, 0, 0.55);
}

/* Drawer Header */
.drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline, #ebdccb);
  flex-shrink: 0;
}

html.dark .drawer-header {
  border-bottom-color: #382f35;
  background: #191618;
}

.drawer-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid var(--outline, #ebdccb);
  background: var(--surface-subtle, #faf7f3);
  color: var(--ink, #1c1618);
  cursor: pointer;
  display: grid;
  place-items: center;
  padding: 0;
  transition: all 0.2s ease;
}

.drawer-close-btn:hover {
  background: #faf7f3;
  color: #6b2d3f;
  border-color: #6b2d3f;
  transform: rotate(90deg);
}

html.dark .drawer-close-btn {
  background: #262024;
  border-color: #382f35;
  color: #faf7f3;
}

html.dark .drawer-close-btn:hover {
  background: #2e242a;
  border-color: #dfba72;
  color: #dfba72;
}

/* Drawer Footer Utilities */
.drawer-footer {
  padding: 14px 18px;
  border-top: 1px solid var(--outline, #ebdccb);
  background: var(--surface-subtle, #faf7f3);
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

html.dark .drawer-footer {
  background: #201b1e;
  border-top-color: #382f35;
}

.drawer-util-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #ebdccb);
  border-radius: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

html.dark .drawer-util-card {
  background: #262024;
  border-color: #382f35;
}

.drawer-util-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.drawer-util-divider {
  width: 1px;
  height: 24px;
  background: var(--outline, #ebdccb);
}

html.dark .drawer-util-divider {
  background: #382f35;
}

.util-text {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--muted, #6e6668);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

html.dark .util-text {
  color: #a89fa4;
}

.drawer-subtext {
  text-align: center;
  font-size: 11px;
  font-weight: 600;
  color: var(--muted, #6e6668);
}

html.dark .drawer-subtext {
  color: #a89fa4;
}

/* Transition Animations */
.drawer-fade-enter-active,
.drawer-fade-leave-active {
  transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.drawer-fade-enter-from,
.drawer-fade-leave-to {
  opacity: 0;
}

.drawer-slide-enter-active,
.drawer-slide-leave-active {
  transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1);
}

.drawer-slide-enter-from,
.drawer-slide-leave-to {
  transform: translateX(100%);
}
</style>
