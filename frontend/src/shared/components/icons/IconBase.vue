<script setup lang="ts">
interface Props {
  size?: number | string
  color?: string
  viewBox?: string
  ariaLabel?: string
}

withDefaults(defineProps<Props>(), {
  size: 24,
  color: 'currentColor',
  viewBox: '0 0 24 24',
  ariaLabel: undefined,
})
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    :width="size"
    :height="size"
    :viewBox="viewBox"
    :fill="color"
    :aria-label="ariaLabel"
    :aria-hidden="ariaLabel ? undefined : 'true'"
    role="img"
    class="erabook-icon"
  >
    <slot />
  </svg>
</template>

<style scoped>
.erabook-icon {
  display: inline-block;
  vertical-align: middle;
  flex-shrink: 0;
  transition:
    color 0.2s ease,
    transform 0.2s ease;
}
</style>
