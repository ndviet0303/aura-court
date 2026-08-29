<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

interface Props {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger' | 'success' | 'glass'
  size?: 'sm' | 'md' | 'lg' | 'pill'
  type?: 'button' | 'submit' | 'reset'
  to?: string | object
  href?: string
  target?: string
  disabled?: boolean
  loading?: boolean
  icon?: string
  iconRight?: string
  block?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  type: 'button',
  to: undefined,
  href: undefined,
  target: undefined,
  disabled: false,
  loading: false,
  icon: undefined,
  iconRight: undefined,
  block: false,
})

const isLink = computed(() => !!props.to)
const isExternalLink = computed(() => !!props.href)

const buttonClasses = computed(() => [
  'aura-btn',
  `aura-btn--${props.variant}`,
  `aura-btn--${props.size}`,
  {
    'aura-btn--loading': props.loading,
    'aura-btn--disabled': props.disabled || props.loading,
    'aura-btn--block': props.block,
  },
])
</script>

<template>
  <RouterLink
    v-if="isLink"
    :to="to!"
    :class="buttonClasses"
    :tabindex="disabled ? -1 : 0"
    :aria-disabled="disabled || loading"
  >
    <span v-if="loading" class="material-symbols-outlined aura-btn__spinner">sync</span>
    <span v-else-if="icon" class="material-symbols-outlined aura-btn__icon aura-btn__icon--left">{{ icon }}</span>

    <span class="aura-btn__content">
      <slot />
    </span>

    <span v-if="!loading && iconRight" class="material-symbols-outlined aura-btn__icon aura-btn__icon--right">{{ iconRight }}</span>
  </RouterLink>

  <a
    v-else-if="isExternalLink"
    :href="href"
    :target="target"
    :rel="target === '_blank' ? 'noopener noreferrer' : undefined"
    :class="buttonClasses"
    :aria-disabled="disabled || loading"
  >
    <span v-if="loading" class="material-symbols-outlined aura-btn__spinner">sync</span>
    <span v-else-if="icon" class="material-symbols-outlined aura-btn__icon aura-btn__icon--left">{{ icon }}</span>

    <span class="aura-btn__content">
      <slot />
    </span>

    <span v-if="!loading && iconRight" class="material-symbols-outlined aura-btn__icon aura-btn__icon--right">{{ iconRight }}</span>
  </a>

  <button
    v-else
    :type="type"
    :disabled="disabled || loading"
    :class="buttonClasses"
  >
    <span v-if="loading" class="material-symbols-outlined aura-btn__spinner">sync</span>
    <span v-else-if="icon" class="material-symbols-outlined aura-btn__icon aura-btn__icon--left">{{ icon }}</span>

    <span class="aura-btn__content">
      <slot />
    </span>

    <span v-if="!loading && iconRight" class="material-symbols-outlined aura-btn__icon aura-btn__icon--right">{{ iconRight }}</span>
  </button>
</template>

<style scoped>
/* ==========================================================================
   Aura Universal Button Component Styles
   ========================================================================== */
.aura-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-family: inherit;
  font-weight: 700;
  line-height: 1.2;
  text-decoration: none;
  cursor: pointer;
  border: 1px solid transparent;
  outline: none;
  user-select: none;
  white-space: nowrap;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-sizing: border-box;
}

.aura-btn:focus-visible {
  box-shadow: 0 0 0 3px rgba(248, 180, 196, 0.5);
}

.aura-btn--block {
  display: flex;
  width: 100%;
}

.aura-btn--disabled,
.aura-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  pointer-events: none;
  box-shadow: none !important;
  transform: none !important;
}

/* ================= SIZES ================= */
.aura-btn--sm {
  padding: 6px 12px;
  font-size: 12px;
  border-radius: 8px;
}

.aura-btn--md {
  padding: 9px 18px;
  font-size: 13.5px;
  border-radius: 12px;
}

.aura-btn--lg {
  padding: 12px 24px;
  font-size: 15px;
  border-radius: 14px;
}

.aura-btn--pill {
  padding: 9px 22px;
  font-size: 13.5px;
  border-radius: 9999px;
}

/* ================= ICONS & SPINNER ================= */
.aura-btn__icon {
  font-size: 1.2em;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.2s ease;
}

.aura-btn__spinner {
  font-size: 1.2em;
  animation: aura-spin 1s linear infinite;
}

@keyframes aura-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* ================= VARIANTS ================= */
/* 1. Primary Variant (High Contrast & Vibrant Glow) */
.aura-btn--primary {
  background-color: var(--primary, #78555e);
  color: var(--on-primary, #ffffff);
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
}

.aura-btn--primary:hover:not(:disabled) {
  filter: brightness(1.1);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(120, 85, 94, 0.35);
}

:global(html.dark) .aura-btn--primary {
  background-color: #f8b4c4;
  color: #201317;
  font-weight: 800;
  box-shadow: 0 4px 18px rgba(248, 180, 196, 0.38);
}

:global(html.dark) .aura-btn--primary:hover:not(:disabled) {
  background-color: #ffd5df;
  color: #201317;
  box-shadow: 0 6px 24px rgba(248, 180, 196, 0.5);
  transform: translateY(-1px);
}

/* 2. Secondary Variant */
.aura-btn--secondary {
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface, #191c1d);
  border-color: var(--border, #e0e4ea);
}

.aura-btn--secondary:hover:not(:disabled) {
  background-color: var(--surface-container-high, #e7e8e9);
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .aura-btn--secondary {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: var(--on-surface, #f3f4f6);
}

:global(html.dark) .aura-btn--secondary:hover:not(:disabled) {
  background-color: var(--surface-container-high, #353943);
}

/* 3. Outline Variant */
.aura-btn--outline {
  background-color: transparent;
  color: var(--primary, #78555e);
  border-color: var(--primary, #78555e);
}

.aura-btn--outline:hover:not(:disabled) {
  background-color: var(--primary-container, #ffd1dc);
}

:global(html.dark) .aura-btn--outline {
  color: #f8b4c4;
  border-color: #f8b4c4;
}

:global(html.dark) .aura-btn--outline:hover:not(:disabled) {
  background-color: rgba(248, 180, 196, 0.15);
}

/* 4. Ghost Variant */
.aura-btn--ghost {
  background-color: transparent;
  color: var(--on-surface-variant, #5d5f5f);
  border-color: transparent;
}

.aura-btn--ghost:hover:not(:disabled) {
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .aura-btn--ghost {
  color: var(--on-surface-variant, #c0c4cc);
}

:global(html.dark) .aura-btn--ghost:hover:not(:disabled) {
  background-color: var(--surface-container, #2b2e36);
  color: #ffffff;
}

/* 5. Danger Variant */
.aura-btn--danger {
  background-color: #ba1a1a;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(186, 26, 26, 0.25);
}

.aura-btn--danger:hover:not(:disabled) {
  background-color: #93000a;
}

:global(html.dark) .aura-btn--danger {
  background-color: #ffb4ab;
  color: #690005;
  font-weight: 800;
  box-shadow: 0 4px 14px rgba(255, 180, 171, 0.3);
}

/* 6. Success Variant */
.aura-btn--success {
  background-color: #2e7d32;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(46, 125, 50, 0.25);
}

.aura-btn--success:hover:not(:disabled) {
  background-color: #1b5e20;
}

:global(html.dark) .aura-btn--success {
  background-color: #81c784;
  color: #003300;
  font-weight: 800;
}

/* 7. Glassmorphism Variant */
.aura-btn--glass {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: inherit;
}

.aura-btn--glass:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.25);
}
</style>
