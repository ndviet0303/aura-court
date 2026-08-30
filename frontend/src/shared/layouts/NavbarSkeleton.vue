<script setup lang="ts">
interface Props {
  fixed?: boolean
}

withDefaults(defineProps<Props>(), {
  fixed: true,
})
</script>

<template>
  <header
    class="navbar-skeleton"
    :class="{ 'is-fixed': fixed }"
    role="status"
    aria-label="Loading navigation"
  >
    <div class="navbar-skeleton-inner">
      <!-- Brand Left Skeleton -->
      <div class="skeleton-brand">
        <div class="skel-item skel-logo"></div>
        <div class="skel-item skel-brand-title"></div>
      </div>

      <!-- Center Capsule Nav Skeleton -->
      <div class="skeleton-nav-pills">
        <div class="skel-item skel-pill skel-pill-1"></div>
        <div class="skel-item skel-pill skel-pill-2"></div>
        <div class="skel-item skel-pill skel-pill-3"></div>
        <div class="skel-item skel-pill skel-pill-4"></div>
      </div>

      <!-- Right Actions Skeleton -->
      <div class="skeleton-nav-right">
        <div class="skel-item skel-circle-btn"></div>
        <div class="skel-item skel-lang-btn"></div>
        <div class="skeleton-user">
          <div class="skeleton-user-meta">
            <div class="skel-item skel-user-name"></div>
            <div class="skel-item skel-user-badge"></div>
          </div>
          <div class="skel-item skel-user-avatar"></div>
        </div>
        <div class="skel-item skel-mobile-menu-btn"></div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.navbar-skeleton {
  width: 100%;
  height: 78px;
  background: var(--topbar-bg, rgba(250, 247, 243, 0.94));
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border-bottom: 1px solid var(--outline, rgba(0, 0, 0, 0.06));
  box-sizing: border-box;
  z-index: 1000;
}

.navbar-skeleton.is-fixed {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
}

.navbar-skeleton-inner {
  width: 100%;
  max-width: 1280px;
  height: 100%;
  margin: 0 auto;
  padding: 0 28px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

/* Shimmer item base */
.skel-item {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
  border-radius: 8px;
}

.skel-item::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(201, 161, 90, 0.22) 45%,
    rgba(255, 255, 255, 0.7) 55%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer-nav 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

:global(html.dark) .skel-item {
  background-color: var(--soft, #262024);
}

:global(html.dark) .skel-item::after {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(229, 142, 159, 0.25) 45%,
    rgba(255, 255, 255, 0.12) 55%,
    rgba(255, 255, 255, 0) 100%
  );
}

/* Brand */
.skeleton-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.skel-logo {
  width: 40px;
  height: 40px;
  border-radius: 12px;
}

.skel-brand-title {
  width: 105px;
  height: 20px;
  border-radius: 6px;
}

/* Nav Pills */
.skeleton-nav-pills {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--outline, rgba(0, 0, 0, 0.05));
  border-radius: 9999px;
  padding: 4px 6px;
}

.skel-pill {
  height: 31px;
  border-radius: 9999px;
}

.skel-pill-1 {
  width: 78px;
}

.skel-pill-2 {
  width: 86px;
}

.skel-pill-3 {
  width: 96px;
}

.skel-pill-4 {
  width: 68px;
}

/* Nav Right */
.skeleton-nav-right {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-shrink: 0;
}

.skel-circle-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
}

.skel-lang-btn {
  width: 58px;
  height: 32px;
  border-radius: 9999px;
}

.skeleton-user {
  display: flex;
  align-items: center;
  gap: 11px;
}

.skeleton-user-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 5px;
}

.skel-user-name {
  width: 76px;
  height: 13px;
  border-radius: 4px;
}

.skel-user-badge {
  width: 50px;
  height: 11px;
  border-radius: 4px;
}

.skel-user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
}

.skel-mobile-menu-btn {
  display: none;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  flex-shrink: 0;
}

@keyframes shimmer-nav {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

@media (max-width: 1080px) {
  .skeleton-user-meta,
  .skel-circle-btn,
  .skel-lang-btn,
  .skeleton-nav-pills {
    display: none !important;
  }
  .skel-mobile-menu-btn {
    display: block !important;
  }
  .skeleton-nav-right {
    gap: 8px;
  }
}

@media (max-width: 720px) {
  .navbar-skeleton {
    height: 66px;
  }
  .navbar-skeleton-inner {
    padding: 0 16px;
  }
  .skel-brand-title {
    width: 85px;
    height: 18px;
  }
  .skel-user-avatar {
    width: 36px;
    height: 36px;
  }
  .skel-mobile-menu-btn {
    width: 38px;
    height: 38px;
  }
}
</style>
