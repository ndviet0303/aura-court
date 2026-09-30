<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { Navbar, SiteFooter } from '@/shared/layouts'
import { DemoWarningModal } from '@/shared/components'
import { isRouteNavigating } from '@/stores/navigation'


const route = useRoute()
const isAdminRoute = computed(() => Boolean(route?.path?.startsWith('/admin')))
</script>

<template>
  <div class="app-shell" :class="{ 'is-admin-shell': isAdminRoute }">
    <Navbar />
    <!-- Top Navigation Progress Indicator -->
    <Transition name="progress-fade">
      <div v-if="isRouteNavigating" class="route-progress-bar" role="progressbar" aria-label="Loading page">
        <div class="progress-glow"></div>
      </div>
    </Transition>

    <DemoWarningModal />

    <main class="site-main" :class="{ 'is-admin-main': isAdminRoute }">
      <RouterView />
    </main>

    <SiteFooter v-if="!isAdminRoute" />
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
  width: 100%;
  max-width: 100%;
  display: flex;
  flex-direction: column;
}

.is-admin-shell {
  height: 100vh;
  max-height: 100vh;
  overflow: hidden;
  background: var(--bg, #18191d);
}

.is-admin-main {
  height: 100vh;
  max-height: 100vh;
  overflow: hidden;
  padding-top: 78px;
  box-sizing: border-box;
  background: var(--bg, #18191d);
}

/* Route Progress Bar right under Navbar */
.route-progress-bar {
  position: fixed;
  top: 78px;
  left: 0;
  right: 0;
  height: 3px;
  z-index: 100;
  background: rgba(201, 161, 90, 0.2);
  overflow: hidden;
}

.progress-glow {
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, #F8E8EC 0%, #6B2D3F 40%, #C9A15A 70%, #FAF2E3 100%);
  animation: route-progress-sweep 1.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  transform-origin: left center;
}

@keyframes route-progress-sweep {
  0% {
    transform: translateX(-100%) scaleX(0.2);
  }
  50% {
    transform: translateX(0%) scaleX(0.8);
  }
  100% {
    transform: translateX(100%) scaleX(0.3);
  }
}

.progress-fade-enter-active,
.progress-fade-leave-active {
  transition: opacity 0.18s ease;
}

.progress-fade-enter-from,
.progress-fade-leave-to {
  opacity: 0;
}
</style>
