<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
// Direct imports: the shared barrels would pull AdminLayout and every shared component into first load.
import Navbar from '@/shared/layouts/header/Navbar.vue'
import SiteFooter from '@/shared/layouts/footer/SiteFooter.vue'
import DemoWarningModal from '@/shared/components/feedback/DemoWarningModal.vue'
import { isRouteNavigating } from '@/stores/navigation'

const route = useRoute()
const isAdminRoute = computed(() => Boolean(route?.path?.startsWith('/admin')))
</script>

<template>
  <div class="app-shell" :class="{ 'is-admin-shell': isAdminRoute }">
    <Navbar />
    <!-- Top Navigation Progress Indicator -->
    <Transition name="progress-fade">
      <div
        v-if="isRouteNavigating"
        class="route-progress-bar"
        role="progressbar"
        aria-label="Loading page"
      >
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
  background: linear-gradient(90deg, #f8e8ec 0%, #6b2d3f 40%, #c9a15a 70%, #faf2e3 100%);
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
