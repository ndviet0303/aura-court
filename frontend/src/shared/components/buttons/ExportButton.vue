<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import AuraButton from './AuraButton.vue'

// Runs an export download; a failure stays on screen instead of claiming a file was made.
const props = defineProps<{ action: () => Promise<unknown> }>()

const { t } = useI18n()
const { loading, error, execute } = useApiCall(() => props.action(), {
  defaultErrorMessage: t('common.exportError'),
})
const run = () => execute().catch(() => null)
</script>

<template>
  <div class="export">
    <AuraButton variant="outline" size="sm" icon="download" :loading="loading" @click="run">
      {{ t('common.exportExcel') }}
    </AuraButton>
    <p v-if="error" class="export-error" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.export {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.export-error {
  margin: 0;
  font-size: 12px;
  color: var(--error-text);
}
</style>
