<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'

// UC-8.4 A-2: applying the common time again shows what it overwrites, own times included, before it does.
defineProps<{ time: string; count: number; custom: number }>()
const emit = defineEmits<{ confirm: []; close: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="apply-time-title">
      <div class="modal-header">
        <h3 id="apply-time-title" class="modal-title">{{ t('recurring.applyScopeTitle') }}</h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="modal-body apply-body">
        <p>{{ t('recurring.applyScope', { time, count }) }}</p>
        <p v-if="custom">{{ t('recurring.applyScopeCustom', { count: custom }) }}</p>
      </div>
      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">{{ t('common.cancel') }}</AuraButton>
        <AuraButton data-testid="apply-time-confirm" @click="emit('confirm')">
          {{ t('recurring.applyConfirm') }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.apply-body {
  display: grid;
  gap: 8px;
}
.apply-body p {
  margin: 0;
}
</style>
