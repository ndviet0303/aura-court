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
  background: #faf7f3;
  border: 1px solid #ebdccb;
  display: grid;
  place-items: center;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.08);
  flex-shrink: 0;
}

.brand:hover .brand-logo-box {
  transform: scale(1.05);
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.16);
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

html.dark .brand-title {
  color: #faf7f3 !important;
}

html.dark .brand-subtitle {
  color: #dfba72 !important;
}

html.dark .brand-logo-box {
  background: #251e22;
  border: 1px solid rgba(223, 186, 114, 0.25);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
}

html.dark .brand:hover .brand-logo-box {
  border-color: rgba(223, 186, 114, 0.45);
  box-shadow: 0 4px 16px rgba(223, 186, 114, 0.2);
}

@media (max-width: 720px) {
  .brand-title {
    font-size: 18px;
  }
}
</style>
