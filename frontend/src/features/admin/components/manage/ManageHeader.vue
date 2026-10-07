<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'

defineProps<{
  couponsCount: number
}>()

const emit = defineEmits<{
  (e: 'open-coupons'): void
  (e: 'refresh'): void
}>()

const { t } = useI18n()
</script>

<template>
  <header class="admin-page-header">
    <div class="header-left">
      <div class="header-title-row">
        <span class="material-symbols-outlined page-title-icon">grid_view</span>
        <h1 class="page-title">{{ t('admin.manage.title') }}</h1>
      </div>
      <p class="page-sub">{{ t('admin.manage.subtitle') }}</p>
    </div>

    <div class="header-right">
      <AuraButton variant="secondary" size="sm" @click="emit('open-coupons')">
        <span class="material-symbols-outlined icon-18">confirmation_number</span>
        <span>{{ t('admin.manage.couponsCount', { count: couponsCount }) }}</span>
      </AuraButton>
      <AuraButton variant="outline" size="sm" @click="emit('refresh')">
        <span class="material-symbols-outlined icon-18">refresh</span>
        <span>{{ t('admin.manage.refresh') }}</span>
      </AuraButton>
    </div>
  </header>
</template>

<style scoped>
.admin-page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.header-left {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-title-icon {
  font-size: 28px;
  color: var(--primary, #6B2D3F);
}

:global(html.dark) .page-title-icon {
  color: var(--accent, #c9a15a);
}
.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.header-right {
  display: flex;
  gap: 10px;
  align-items: center;
}

.icon-18 {
  font-size: 18px;
}
</style>
