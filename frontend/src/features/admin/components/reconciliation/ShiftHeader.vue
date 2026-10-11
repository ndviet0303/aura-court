<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'

defineProps<{
  loading: boolean
  saving: boolean
  hasActiveShift: boolean
  notice?: string
  error?: string
}>()

const emit = defineEmits<{
  (e: 'refresh'): void
  (e: 'openShift'): void
  (e: 'closeShift'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="shift-header-container">
    <div class="header-main-row">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">point_of_sale</span>
          <h1 class="page-title">{{ t('admin.shifts.title') }}</h1>
        </div>
        <p class="page-sub">{{ t('admin.shifts.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <AuraButton
          variant="secondary"
          :icon="loading ? undefined : 'refresh'"
          :loading="loading"
          :disabled="loading"
          @click="emit('refresh')"
        >
          {{ t('admin.shifts.refreshBtn') }}
        </AuraButton>

        <AuraButton
          v-if="!hasActiveShift"
          variant="primary"
          icon="play_arrow"
          :disabled="loading || saving"
          @click="emit('openShift')"
        >
          {{ t('admin.shifts.openShiftBtn') }}
        </AuraButton>

        <AuraButton
          v-else
          variant="primary"
          icon="lock_clock"
          :disabled="loading || saving"
          @click="emit('closeShift')"
        >
          {{ t('admin.shifts.closeShiftBtn') }}
        </AuraButton>
      </div>
    </div>

    <!-- Alert Notices -->
    <div v-if="notice" class="alert-pill alert-success">
      <span class="material-symbols-outlined icon-18">check_circle</span>
      <span>{{ notice }}</span>
    </div>

    <div v-if="error" class="alert-pill alert-error">
      <span class="material-symbols-outlined icon-18">error</span>
      <span>{{ error }}</span>
    </div>
  </div>
</template>

<style scoped>
.shift-header-container {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.header-main-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.02em;
}

.page-sub {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--muted);
  max-width: 760px;
  line-height: 1.4;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.icon-18 {
  font-size: 18px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.alert-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  animation: slide-in 0.2s ease-out;
}

.alert-success {
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #10b981;
}

.alert-error {
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #ef4444;
}

@keyframes slide-in {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
