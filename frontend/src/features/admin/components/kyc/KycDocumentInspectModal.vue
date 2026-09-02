<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DocumentInspectPayload } from '../../types/kyc'
import { AuraButton } from '@/shared/components'

const props = defineProps<{
  show: boolean
  inspectData: DocumentInspectPayload | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const { t } = useI18n()

const zoomLevel = ref(1)
const rotationAngle = ref(0)

watch(
  () => props.show,
  (isOpen) => {
    if (isOpen) {
      zoomLevel.value = 1
      rotationAngle.value = 0
    }
  }
)

function zoomIn() {
  if (zoomLevel.value < 2.5) {
    zoomLevel.value = Number((zoomLevel.value + 0.25).toFixed(2))
  }
}

function zoomOut() {
  if (zoomLevel.value > 0.6) {
    zoomLevel.value = Number((zoomLevel.value - 0.25).toFixed(2))
  }
}

function rotate() {
  rotationAngle.value = (rotationAngle.value + 90) % 360
}

function resetTransform() {
  zoomLevel.value = 1
  rotationAngle.value = 0
}
</script>

<template>
  <teleport to="body">
    <transition name="modal-fade">
      <div v-if="show && inspectData" class="inspect-modal-overlay" @click.self="emit('close')">
        <div class="inspect-modal-container" role="dialog" aria-modal="true">
          <!-- Header Bar -->
          <div class="modal-header">
            <div class="header-left">
              <span class="material-symbols-outlined header-icon">zoom_in</span>
              <div>
                <h3 class="modal-title">{{ inspectData.title }}</h3>
                <span class="modal-subtitle">{{ t('admin.kyc.inspectModalTitle') }}</span>
              </div>
            </div>

            <!-- Toolbar Controls -->
            <div class="header-toolbar">
              <button
                type="button"
                class="tool-btn"
                :title="t('admin.kyc.inspectRotate')"
                @click="rotate"
              >
                <span class="material-symbols-outlined icon-18">rotate_right</span>
                <span>{{ t('admin.kyc.inspectRotate') }}</span>
              </button>

              <button type="button" class="tool-btn" title="Zoom Out" @click="zoomOut">
                <span class="material-symbols-outlined icon-18">zoom_out</span>
              </button>

              <span class="zoom-indicator">{{ Math.round(zoomLevel * 100) }}%</span>

              <button type="button" class="tool-btn" title="Zoom In" @click="zoomIn">
                <span class="material-symbols-outlined icon-18">zoom_in</span>
              </button>

              <button
                type="button"
                class="tool-btn"
                :title="t('admin.kyc.inspectResetZoom')"
                @click="resetTransform"
              >
                <span class="material-symbols-outlined icon-18">restart_alt</span>
              </button>

              <AuraButton variant="secondary" size="sm" @click="emit('close')">
                <span class="material-symbols-outlined icon-18">close</span>
                <span>{{ t('admin.kyc.inspectClose') }}</span>
              </AuraButton>
            </div>
          </div>

          <!-- Document Preview Stage -->
          <div class="modal-preview-stage">
            <div class="image-wrapper">
              <img
                :src="inspectData.imageUrl"
                :alt="inspectData.title"
                class="inspect-image"
                :style="{
                  transform: `scale(${zoomLevel}) rotate(${rotationAngle}deg)`,
                }"
              />
            </div>
          </div>

          <!-- Footer Metadata Banner -->
          <div v-if="inspectData.docMeta" class="modal-footer-meta">
            <div
              v-for="(val, key) in inspectData.docMeta"
              :key="key"
              class="meta-pill"
            >
              <span class="meta-key">{{ key }}:</span>
              <span class="meta-val">{{ val }}</span>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.inspect-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.inspect-modal-container {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 20px;
  width: 100%;
  max-width: 1040px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
  background: var(--surface, #ffffff);
  gap: 16px;
  flex-wrap: wrap;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  font-size: 26px;
  color: var(--primary, #78555e);
}

.modal-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.modal-subtitle {
  font-size: 12px;
  color: var(--muted, #817476);
}

.header-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tool-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--surface-container-low, #f8f9fa);
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.2s;
}

.tool-btn:hover {
  background: var(--surface-container, #edeeef);
}

.zoom-indicator {
  font-size: 12px;
  font-weight: 700;
  font-family: monospace;
  color: var(--muted, #817476);
  min-width: 44px;
  text-align: center;
}

.modal-preview-stage {
  flex: 1;
  min-height: 460px;
  max-height: calc(90vh - 160px);
  overflow: auto;
  background: #0f172a;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  position: relative;
}

.image-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: 100%;
}

.inspect-image {
  max-width: 100%;
  max-height: 70vh;
  object-fit: contain;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
  transition: transform 0.2s ease;
  user-select: none;
}

.modal-footer-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 24px;
  border-top: 1px solid var(--outline, #e2e8f0);
  background: var(--surface, #ffffff);
  overflow-x: auto;
}

.meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-container-low, #f8f9fa);
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
}

.meta-key {
  color: var(--muted, #817476);
  font-weight: 500;
}

.meta-val {
  color: var(--ink, #191c1d);
  font-weight: 700;
}

.icon-18 {
  font-size: 18px;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.25s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
