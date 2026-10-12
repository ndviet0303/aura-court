<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  alertsCount: number
}>()

const emit = defineEmits<{
  (e: 'auto-resolve'): void
  (e: 'open-investigation'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div v-if="alertsCount > 0" class="alert-banner">
    <div class="banner-left">
      <div class="warning-badge">
        <span class="material-symbols-outlined warning-icon">warning</span>
      </div>
      <div class="banner-content">
        <div class="banner-title">
          {{ t('admin.reconciliation.alertBannerTitle', { count: alertsCount }) }}
        </div>
      </div>
    </div>

    <div class="banner-actions">
      <button class="btn-auto-fix" type="button" @click="emit('auto-resolve')">
        <span class="material-symbols-outlined">rule</span>
        {{ t('admin.reconciliation.autoResolveSafeBtn') }}
      </button>
      <button class="btn-open-investigate" type="button" @click="emit('open-investigation')">
        <span class="material-symbols-outlined">manage_search</span>
        {{ t('admin.reconciliation.openInvestigationBtn') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.alert-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  background: var(--surface, #ffffff);
  border-left: 4px solid #ba1a1a;
  border-radius: 14px;
  padding: 16px 20px;
  border-top: 1px solid #ffdad6;
  border-right: 1px solid #ffdad6;
  border-bottom: 1px solid #ffdad6;
  box-shadow: 0 2px 10px rgba(186, 26, 26, 0.04);
}

:global(html.dark) .alert-banner {
  background: #251618;
  border-top-color: #5c2025;
  border-right-color: #5c2025;
  border-bottom-color: #5c2025;
}

.banner-left {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1;
  min-width: 320px;
}

.warning-badge {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: #ffdad6;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

:global(html.dark) .warning-badge {
  background: #49171b;
}

.warning-icon {
  font-size: 22px;
  color: #ba1a1a;
  font-variation-settings: 'FILL' 1;
}

.banner-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.banner-title {
  font-size: 14.5px;
  font-weight: 700;
  color: #ba1a1a;
}

.banner-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-auto-fix {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  border: none;
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-auto-fix:hover {
  background: var(--pink-deep, #be185d);
  transform: translateY(-1px);
}

.btn-open-investigate {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  border: 1px solid var(--outline, #e1e3e4);
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-open-investigate:hover {
  background: var(--soft, #f3f4f5);
}
</style>
