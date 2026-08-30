<script setup lang="ts">
import { RouterLink } from 'vue-router'

interface Props {
  to?: string
  showSubtitle?: boolean
}

withDefaults(defineProps<Props>(), {
  to: '/',
  showSubtitle: false,
})

const emit = defineEmits<{
  (e: 'click'): void
}>()
</script>

<template>
  <RouterLink :to="to" class="brand" title="Aura Court" @click="emit('click')">
    <div class="brand-logo-box">
      <img src="/aura/badge-logo.png" alt="Aura Court" class="brand-logo-img" />
    </div>
    <div class="brand-text">
      <span class="brand-title">Aura Court</span>
      <span v-if="showSubtitle" class="brand-subtitle">Badminton & Sports</span>
    </div>
  </RouterLink>
</template>

<style scoped>
.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  flex-shrink: 0;
}

.brand-logo-box {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--pink-light, #f8e8ec);
  display: grid;
  place-items: center;
  transition: transform 0.2s ease;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
  flex-shrink: 0;
}

.brand:hover .brand-logo-box {
  transform: scale(1.05);
}

.brand-logo-img {
  width: 25px;
  height: 25px;
  object-fit: contain;
}

.brand-text {
  display: flex;
  flex-direction: column;
}

.brand-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink, #1c1618);
  letter-spacing: -0.03em;
  transition: color 0.3s ease;
  white-space: nowrap;
}

.brand-subtitle {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted, #6e6668);
  letter-spacing: 0.02em;
}

:global(html.dark) .brand-title {
  color: #faf7f3 !important;
}

:global(html.dark) .brand-subtitle {
  color: #a89fa4 !important;
}

:global(html.dark) .brand-logo-box {
  background: #36141d;
  box-shadow: inset 0 0 0 1px rgba(229, 142, 159, 0.3);
}

@media (max-width: 720px) {
  .brand-title {
    font-size: 18px;
  }
}
</style>
