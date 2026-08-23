<script setup lang="ts">
import { RouterView } from 'vue-router'
import Navbar from '@/components/Navbar.vue'
import SiteFooter from '@/components/SiteFooter.vue'
import DemoWarningModal from '@/components/DemoWarningModal.vue'
import { isRouteNavigating } from '@/stores/navigation'
</script>

<template>
  <Navbar />
  <!-- Top Navigation Progress Indicator -->
  <Transition name="progress-fade">
    <div v-if="isRouteNavigating" class="route-progress-bar" role="progressbar" aria-label="Loading page">
      <div class="progress-glow"></div>
    </div>
  </Transition>

  <DemoWarningModal />

  <main class="site-main">
    <RouterView />
  </main>

  <SiteFooter />
</template>

<style scoped>
/* Route Progress Bar right under Navbar */
.route-progress-bar {
  position: fixed;
  top: 78px;
  left: 0;
  right: 0;
  height: 3px;
  z-index: 100;
  background: rgba(255, 209, 220, 0.4);
  overflow: hidden;
}

.progress-glow {
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, #ffd1dc 0%, #78555e 50%, #ffd9e2 100%);
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
