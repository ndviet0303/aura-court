<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  size?: 'sm' | 'md' | 'lg'
  fullscreen?: boolean
  overlay?: boolean
  text?: string
  showLogo?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  fullscreen: false,
  overlay: false,
  text: '',
  showLogo: true,
})

const containerClass = computed(() => {
  return {
    'is-fullscreen': props.fullscreen,
    'is-overlay': props.overlay && !props.fullscreen,
    [`size-${props.size}`]: true,
  }
})
</script>

<template>
  <div class="aura-loading-root" :class="containerClass">
    <div class="loading-content">
      <!-- Outer Orbit Spinner Ring -->
      <div class="spinner-stage">
        <div class="orbit-ring ring-outer"></div>
        <div class="orbit-ring ring-inner"></div>

        <!-- Center Pulse Logo -->
        <div v-if="showLogo && size !== 'sm'" class="logo-pulse-box">
          <img src="/aura/badge-logo.png" alt="Aura Court" class="logo-img" />
        </div>
      </div>

      <!-- Animated Status Text -->
      <div v-if="text || size === 'lg'" class="loading-label-wrap">
        <span class="loading-label">{{ text || $t('common.loading') }}</span>
        <span class="dots-wave" aria-hidden="true">
          <span class="dot dot-1">.</span>
          <span class="dot dot-2">.</span>
          <span class="dot dot-3">.</span>
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.aura-loading-root {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

/* Fullscreen Mode */
.aura-loading-root.is-fullscreen {
  position: fixed;
  inset: 0;
  z-index: 99999;
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  padding: 0;
}

/* Card / Container Overlay Mode */
.aura-loading-root.is-overlay {
  position: absolute;
  inset: 0;
  z-index: 40;
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  border-radius: inherit;
  padding: 0;
}

.loading-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
}

/* Spinner Stage */
.spinner-stage {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Size Variants */
.size-sm .spinner-stage {
  width: 24px;
  height: 24px;
}
.size-sm .orbit-ring {
  border-width: 2.5px;
}

.size-md .spinner-stage {
  width: 56px;
  height: 56px;
}
.size-md .orbit-ring {
  border-width: 3px;
}

.size-lg .spinner-stage {
  width: 84px;
  height: 84px;
}
.size-lg .orbit-ring {
  border-width: 4px;
}

/* Orbit Rings */
.orbit-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border-style: solid;
  box-sizing: border-box;
}

.ring-outer {
  border-color: rgba(201, 161, 90, 0.25);
  border-top-color: var(--primary, #6b2d3f);
  border-right-color: var(--accent, #c9a15a);
  animation: aura-spin 1s cubic-bezier(0.55, 0.15, 0.45, 0.85) infinite;
  box-shadow: 0 0 16px rgba(107, 45, 63, 0.15);
}

.ring-inner {
  inset: 5px;
  border-color: transparent;
  border-bottom-color: var(--accent, #c9a15a);
  border-left-color: var(--primary, #6b2d3f);
  animation: aura-spin-reverse 1.4s linear infinite;
  opacity: 0.85;
}

.size-sm .ring-inner {
  display: none;
}

/* Logo in center */
.logo-pulse-box {
  position: relative;
  z-index: 2;
  border-radius: 50%;
  background: #ffffff;
  display: grid;
  place-items: center;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.15);
  animation: aura-pulse 2s ease-in-out infinite;
}

.size-md .logo-pulse-box {
  width: 28px;
  height: 28px;
}
.size-md .logo-img {
  width: 16px;
  height: 16px;
}

.size-lg .logo-pulse-box {
  width: 42px;
  height: 42px;
}
.size-lg .logo-img {
  width: 24px;
  height: 24px;
}

.logo-img {
  object-fit: contain;
}

/* Animated Text */
.loading-label-wrap {
  display: flex;
  align-items: baseline;
  color: #5e3e47;
  font-size: 13.5px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.size-lg .loading-label-wrap {
  font-size: 15px;
}

.dots-wave {
  display: inline-flex;
  margin-left: 2px;
}

.dot {
  animation: dot-jump 1.4s infinite;
  font-size: 16px;
  line-height: 1;
}

.dot-1 {
  animation-delay: 0s;
}
.dot-2 {
  animation-delay: 0.2s;
}
.dot-3 {
  animation-delay: 0.4s;
}

/* Keyframes */
@keyframes aura-spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

@keyframes aura-spin-reverse {
  0% {
    transform: rotate(360deg);
  }
  100% {
    transform: rotate(0deg);
  }
}

@keyframes aura-pulse {
  0%,
  100% {
    transform: scale(0.92);
  }
  50% {
    transform: scale(1.06);
  }
}

@keyframes dot-jump {
  0%,
  80%,
  100% {
    transform: translateY(0);
    opacity: 0.3;
  }
  40% {
    transform: translateY(-3px);
    opacity: 1;
  }
}
</style>
