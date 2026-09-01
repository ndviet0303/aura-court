<script setup lang="ts">
import { onMounted, ref } from 'vue'

const isOpen = ref(false)

function checkAndShow() {
  const acknowledged = sessionStorage.getItem('aura_demo_warned')
  if (!acknowledged) {
    isOpen.value = true
  }
}

function dismiss() {
  sessionStorage.setItem('aura_demo_warned', 'true')
  isOpen.value = false
}

onMounted(() => {
  checkAndShow()
})
</script>

<template>
  <Teleport to="body">
    <Transition name="demo-modal-fade">
      <div
        v-if="isOpen"
        class="demo-modal-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="demo-modal-title"
      >
        <div class="demo-modal-backdrop" @click="dismiss"></div>
        <div class="demo-modal-container">
          <div class="demo-modal-card">
            <!-- Close Button -->
            <button
              type="button"
              class="demo-modal-close-btn"
              :aria-label="$t('common.close')"
              :title="$t('common.close')"
              @click="dismiss"
            >
              <span class="material-symbols-outlined">close</span>
            </button>

            <!-- Warning Icon -->
            <div class="demo-modal-icon-badge">
              <span class="material-symbols-outlined demo-modal-icon">warning</span>
            </div>

            <!-- Tag -->
            <div class="demo-modal-tag">
              {{ $t('common.demoModal.warningTag') }}
            </div>

            <!-- Title -->
            <h2 id="demo-modal-title" class="demo-modal-title">
              {{ $t('common.demoModal.title') }}
            </h2>

            <!-- Content -->
            <div class="demo-modal-body">
              <p class="demo-modal-text">
                {{ $t('common.demoModal.content1') }}
              </p>
              <p class="demo-modal-text highlight">
                {{ $t('common.demoModal.content2') }}
              </p>
            </div>

            <!-- Action Button -->
            <div class="demo-modal-actions">
              <button type="button" class="btn-understand" @click="dismiss">
                <span>{{ $t('common.demoModal.understandBtn') }}</span>
                <span class="material-symbols-outlined">arrow_forward</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.demo-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.demo-modal-backdrop {
  position: absolute;
  inset: 0;
  z-index: 1;
  background: rgba(15, 20, 25, 0.65);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

.demo-modal-container {
  position: relative;
  z-index: 10;
  width: 100%;
  max-width: 480px;
}

.demo-modal-card {
  position: relative;
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, rgba(225, 227, 228, 0.8));
  border-radius: 24px;
  padding: 36px 32px 30px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35), 0 8px 24px rgba(120, 85, 94, 0.15);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  color: var(--ink, #191c1d);
}

.demo-modal-close-btn {
  position: absolute;
  top: 18px;
  right: 18px;
  border: 0;
  background: var(--soft, #f4f5f6);
  color: var(--muted, #5d5f5f);
  width: 34px;
  height: 34px;
  border-radius: 50%;
  cursor: pointer;
  display: grid;
  place-items: center;
  transition: all 0.2s ease;
}

.demo-modal-close-btn:hover {
  background: var(--pink-light, #ffd1dc);
  color: var(--pink-dark, #5e3e47);
}

.demo-modal-close-btn .material-symbols-outlined {
  font-size: 18px;
}

.demo-modal-icon-badge {
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: linear-gradient(135deg, var(--pink) 0%, var(--pink-light) 100%);
  display: grid;
  place-items: center;
  color: var(--pink-dark, #78555e);
  box-shadow: 0 8px 20px rgba(255, 209, 220, 0.3);
  margin-bottom: 16px;
}

.demo-modal-icon {
  font-size: 32px;
}

.demo-modal-tag {
  display: inline-block;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.12em;
  color: var(--pink-dark, #78555e);
  background: var(--pink-light, #fce4ec);
  padding: 4px 12px;
  border-radius: 999px;
  margin-bottom: 10px;
  text-transform: uppercase;
}

.demo-modal-title {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  letter-spacing: -0.03em;
  margin: 0 0 14px;
  line-height: 1.3;
}

.demo-modal-body {
  margin-bottom: 26px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.demo-modal-text {
  font-size: 14px;
  line-height: 1.6;
  color: var(--muted, #5d5f5f);
  margin: 0;
}

.demo-modal-text.highlight {
  font-size: 13px;
  background: var(--soft, #fdf6f7);
  padding: 10px 14px;
  border-radius: 12px;
  border-left: 3px solid var(--pink-dark, #78555e);
  text-align: left;
  color: var(--ink, #493339);
}

.demo-modal-actions {
  width: 100%;
}

.btn-understand {
  width: 100%;
  border: 0;
  border-radius: 9999px;
  padding: 14px 24px;
  background: var(--primary, #6B2D3F);
  color: var(--on-primary, #ffffff);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.22s ease;
  box-shadow: 0 6px 18px rgba(107, 45, 63, 0.28);
}

.btn-understand:hover {
  background: var(--primary-hover, #562332);
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(107, 45, 63, 0.36);
}

.btn-understand .material-symbols-outlined {
  font-size: 18px;
  transition: transform 0.2s ease;
}

.btn-understand:hover .material-symbols-outlined {
  transform: translateX(3px);
}

/* Modal Transition */
.demo-modal-fade-enter-active,
.demo-modal-fade-leave-active {
  transition: opacity 0.22s ease;
}

.demo-modal-fade-enter-active .demo-modal-card,
.demo-modal-fade-leave-active .demo-modal-card {
  transition: transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.22s ease;
}

.demo-modal-fade-enter-from,
.demo-modal-fade-leave-to {
  opacity: 0;
}

.demo-modal-fade-enter-from .demo-modal-card,
.demo-modal-fade-leave-to .demo-modal-card {
  opacity: 0;
  transform: scale(0.92) translateY(12px);
}
</style>
