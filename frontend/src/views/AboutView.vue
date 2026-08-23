<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="page-shell about">
    <div class="eyebrow">{{ t('common.appName') }}</div>
    <h1 class="page-title">{{ t('home.heroTitle') }} {{ t('home.heroTitleEmphasis') }}</h1>
    <p class="muted" style="max-width: 600px; margin-top: 14px;">
      {{ t('home.heroSubtitle') }}
    </p>
  </div>
</template>

<style scoped>
.about {
  min-height: calc(100vh - 200px);
  display: flex;
  flex-direction: column;
  justify-content: center;
}
</style>
