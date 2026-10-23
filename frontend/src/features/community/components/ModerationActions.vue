<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { MODERATION_NOTE_MAX, type ModerationCommand } from '../types/moderation'

// Restore applies only to hidden content; hide only to visible content.
defineProps<{ hidden: boolean; busy: boolean }>()
const emit = defineEmits<{
  act: [command: ModerationCommand, note: string, done: (ok: boolean) => void]
}>()

const { t } = useI18n()
const note = ref('')

function act(command: ModerationCommand) {
  emit('act', command, note.value, (ok) => {
    if (ok) note.value = ''
  })
}
</script>

<template>
  <section class="admin-detail-card moderation-actions">
    <h2 class="admin-detail-section-title">{{ t('moderation.decide') }}</h2>
    <label class="form-group">
      <span class="form-label">{{ t('moderation.note') }} *</span>
      <textarea v-model="note" rows="3" :maxlength="MODERATION_NOTE_MAX" class="form-textarea" />
      <span class="form-hint">{{ note.length }}/{{ MODERATION_NOTE_MAX }}</span>
    </label>
    <div class="moderation-buttons">
      <AuraButton
        v-if="!hidden"
        variant="danger"
        icon="visibility_off"
        :loading="busy"
        :disabled="!note.trim()"
        @click="act('hide')"
      >
        {{ t('moderation.commands.hide') }}
      </AuraButton>
      <AuraButton
        v-else
        variant="outline"
        icon="visibility"
        :loading="busy"
        :disabled="!note.trim()"
        @click="act('restore')"
      >
        {{ t('moderation.commands.restore') }}
      </AuraButton>
      <AuraButton
        variant="secondary"
        icon="check"
        :loading="busy"
        :disabled="!note.trim()"
        @click="act('keep')"
      >
        {{ t('moderation.commands.keep') }}
      </AuraButton>
    </div>
  </section>
</template>

<style scoped>
.moderation-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.moderation-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
