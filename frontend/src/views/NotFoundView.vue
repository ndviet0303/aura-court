<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <section class="page-shell not-found">
    <div class="not-found-art" aria-hidden="true">
      <strong>{{ t('notFound.code') }}</strong>
      <span>{{ t('notFound.badge') }}</span>
    </div>
    <div class="eyebrow">404</div>
    <h1 class="page-title">{{ t('notFound.title') }}</h1>
    <p class="muted">{{ t('notFound.subtitle') }}</p>
    <div class="actions">
      <RouterLink to="/" class="btn">{{ t('notFound.backHome') }}</RouterLink>
      <RouterLink to="/courts" class="btn secondary">{{ t('courts.title') }}</RouterLink>
    </div>
  </section>
</template>
