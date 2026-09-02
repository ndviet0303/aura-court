<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { PosShift } from '../types/pos'

defineProps<{
  activeShift: PosShift | null
  isFullscreen: boolean
  notice?: string
  error?: string
}>()

const emit = defineEmits<{
  (e: 'openShiftModal'): void
  (e: 'toggleFullscreen'): void
}>()

const { t } = useI18n()
</script>

<template>
  <header class="pos-topbar">
    <div class="topbar-left">
      <RouterLink to="/admin" class="pos-brand" title="Aura Admin">
        <span class="material-symbols-outlined brand-icon">point_of_sale</span>
        <span class="brand-badge">{{ t('admin.pos.brand') }}</span>
      </RouterLink>
      <span class="pos-mode-tag">{{ t('admin.pos.modeTag') }}</span>
    </div>

    <div class="topbar-center">
      <transition name="banner-fade">
        <div v-if="notice" class="pos-banner-notice">
          <span class="material-symbols-outlined icon-16">check_circle</span>
          <span>{{ notice }}</span>
        </div>
      </transition>
      <transition name="banner-fade">
        <div v-if="error" class="pos-banner-error">
          <span class="material-symbols-outlined icon-16">error</span>
          <span>{{ error }}</span>
        </div>
      </transition>
    </div>

    <div class="topbar-right">
      <button
        type="button"
        class="topbar-btn shift-btn"
        :class="{ 'shift-active': !!activeShift }"
        @click="emit('openShiftModal')"
      >
        <span class="shift-pulse" v-if="activeShift"></span>
        <span class="material-symbols-outlined icon-18">badge</span>
        <span v-if="activeShift">
          {{ t('admin.pos.shiftNumber', { id: activeShift.id }) }}
          <span v-if="activeShift.cashier?.name" class="cashier-name">({{ activeShift.cashier.name }})</span>
        </span>
        <span v-else>{{ t('admin.pos.openShift') }}</span>
      </button>

      <button
        type="button"
        class="topbar-btn fullscreen-btn"
        :title="isFullscreen ? t('admin.pos.minimize') : t('admin.pos.fullscreen')"
        @click="emit('toggleFullscreen')"
      >
        <span class="material-symbols-outlined icon-18">
          {{ isFullscreen ? 'fullscreen_exit' : 'fullscreen' }}
        </span>
        <span class="btn-text">{{ isFullscreen ? t('admin.pos.minimize') : t('admin.pos.fullscreen') }}</span>
      </button>
    </div>
  </header>
</template>

<style scoped>
.pos-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 18px;
  background: var(--surface, #1e293b);
  border-bottom: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  min-height: 58px;
  gap: 16px;
  user-select: none;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pos-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  color: var(--ink, #ffffff);
  font-weight: 700;
  font-size: 16px;
  transition: opacity 0.2s ease;
}

.pos-brand:hover {
  opacity: 0.9;
}

.brand-icon {
  color: var(--primary, #10b981);
  font-size: 22px;
}

.brand-badge {
  background: linear-gradient(135deg, var(--primary, #10b981) 0%, #059669 100%);
  color: #ffffff;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.5px;
}

.pos-mode-tag {
  font-size: 12px;
  color: var(--muted, #94a3b8);
  font-weight: 500;
  border-left: 1px solid var(--outline, rgba(255, 255, 255, 0.15));
  padding-left: 12px;
}

.topbar-center {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
  min-height: 36px;
}

.pos-banner-notice,
.pos-banner-error {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 500;
  animation: slideDown 0.25s ease-out;
}

.pos-banner-notice {
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #10b981;
}

.pos-banner-error {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #ef4444;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.topbar-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 10px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  color: var(--ink, #ffffff);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.topbar-btn:hover {
  background: var(--surface-active, rgba(255, 255, 255, 0.1));
  border-color: var(--primary, #10b981);
}

.shift-btn.shift-active {
  background: rgba(16, 185, 129, 0.1);
  border-color: rgba(16, 185, 129, 0.3);
  color: #10b981;
}

.shift-pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  animation: pulseGreen 1.8s infinite;
}

.cashier-name {
  font-weight: 400;
  font-size: 12px;
  color: var(--muted, #94a3b8);
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}

@keyframes pulseGreen {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
  }
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.banner-fade-enter-active,
.banner-fade-leave-active {
  transition: all 0.25s ease;
}

.banner-fade-enter-from,
.banner-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (max-width: 768px) {
  .pos-mode-tag,
  .btn-text,
  .cashier-name {
    display: none;
  }
}
</style>
