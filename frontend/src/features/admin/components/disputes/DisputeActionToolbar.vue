<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { DisputeCase } from '../../types/dispute'

defineProps<{
  item: DisputeCase
  isSubmitting?: boolean
}>()

const emit = defineEmits<{
  (e: 'openArbitration'): void
  (e: 'dismiss'): void
  (e: 'relocate'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="action-toolbar">
    <div class="toolbar-content">
      <!-- Button 1: Dismiss -->
      <button
        type="button"
        class="btn-dismiss"
        :disabled="isSubmitting || item.status === 'resolved' || item.status === 'dismissed'"
        @click="emit('dismiss')"
      >
        <span class="material-symbols-outlined icon-18">close</span>
        <span>{{ t('admin.disputes.btnDismiss') }}</span>
      </button>

      <!-- Button 2: Relocate Court -->
      <button
        type="button"
        class="btn-relocate"
        :disabled="isSubmitting || item.status === 'resolved'"
        @click="emit('relocate')"
      >
        <span class="material-symbols-outlined icon-18">swap_horiz</span>
        <span>{{ t('admin.disputes.btnRelocate') }}</span>
      </button>

      <!-- Button 3: Main Action - Arbitrate & Refund -->
      <button
        type="button"
        class="btn-arbitrate"
        :disabled="isSubmitting"
        @click="emit('openArbitration')"
      >
        <span class="material-symbols-outlined icon-18">gavel</span>
        <span>{{ t('admin.disputes.btnArbitrate') }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.action-toolbar {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 16px;
  padding: 16px 24px;
  box-shadow: var(--shadow-sm, 0 4px 20px rgba(0, 0, 0, 0.04));
  color: var(--ink);
}

.toolbar-content {
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
}

.btn-dismiss {
  flex: 1;
  min-width: 150px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  border-radius: 12px;
  padding: 12px 18px;
  font-size: 13px;
  font-weight: 700;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-dismiss:hover:not(:disabled) {
  background: var(--soft);
  color: var(--ink);
}

.btn-relocate {
  flex: 1.2;
  min-width: 170px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--primary-container);
  border: 1px solid var(--primary);
  border-radius: 12px;
  padding: 12px 18px;
  font-size: 13px;
  font-weight: 700;
  color: var(--primary);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-relocate:hover:not(:disabled) {
  filter: brightness(1.08);
}

.btn-arbitrate {
  flex: 1.8;
  min-width: 220px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--primary);
  border: none;
  border-radius: 12px;
  padding: 12px 24px;
  font-size: 14px;
  font-weight: 700;
  color: var(--on-primary, #ffffff);
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.25);
  transition: all 0.2s;
}

.btn-arbitrate:hover:not(:disabled) {
  background: var(--primary-hover, #5e3e47);
  transform: translateY(-1px);
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.icon-18 {
  font-size: 18px;
}
</style>
