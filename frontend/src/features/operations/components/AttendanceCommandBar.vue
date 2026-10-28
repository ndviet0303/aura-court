<script setup lang="ts">
import { ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useQrScanner } from '@/core/composables/useQrScanner'

type DeskCommand = 'check-in' | 'check-out'

/**
 * FR-103, FR-106, BR-124: pick check-in or check-out, then scan the customer's QR (sent at once) or type the booking
 * code and press Save.
 */
defineProps<{ busy?: boolean }>()
const emit = defineEmits<{ run: [command: DeskCommand, code: string] }>()
const { t } = useI18n()

const COMMANDS: DeskCommand[] = ['check-in', 'check-out']
const command = ref<DeskCommand>('check-in')
const mode = ref<'scan' | 'manual'>('manual')
const code = ref('')
const video = useTemplateRef<HTMLVideoElement>('video')
// BR-124, D7: only the customer's booking QR is sent; another QR, such as a payment QR, is refused.
const BOOKING_QR_PREFIX = 'AURA_BOOKING_'
const {
  error: scanError,
  start,
  stop,
} = useQrScanner((scanned) => {
  if (!scanned.startsWith(BOOKING_QR_PREFIX)) return false
  emit('run', command.value, scanned)
  return true
})

watch(mode, (value) => (value === 'scan' && video.value ? start(video.value) : stop()), {
  flush: 'post',
})

function save() {
  if (!code.value.trim()) return
  emit('run', command.value, code.value)
  code.value = ''
}
</script>

<template>
  <div class="command-bar">
    <div class="toggles">
      <div class="segmented" role="radiogroup" :aria-label="t('operations.desk.command')">
        <button
          v-for="item in COMMANDS"
          :key="item"
          type="button"
          role="radio"
          :aria-checked="command === item"
          :class="{ active: command === item }"
          @click="command = item"
        >
          {{ t(`operations.action.${item}`) }}
        </button>
      </div>
      <div class="segmented" role="radiogroup" :aria-label="t('operations.desk.mode')">
        <button
          v-for="item in ['manual', 'scan'] as const"
          :key="item"
          type="button"
          role="radio"
          :aria-checked="mode === item"
          :class="{ active: mode === item }"
          @click="mode = item"
        >
          {{ t(`operations.desk.${item}`) }}
        </button>
      </div>
    </div>

    <form v-if="mode === 'manual'" class="manual" @submit.prevent="save">
      <input
        v-model="code"
        class="form-input"
        :placeholder="t('operations.desk.codePlaceholder')"
        :aria-label="t('operations.desk.codeLabel')"
        maxlength="255"
        autocomplete="off"
      />
      <AuraButton type="submit" :loading="busy" :disabled="!code.trim()">
        {{ t('common.save') }}
      </AuraButton>
    </form>

    <div v-show="mode === 'scan'" class="scan">
      <video ref="video" class="scan-video" muted playsinline />
      <p v-if="scanError" class="form-error" role="alert">
        {{ t(`operations.desk.scanError.${scanError}`) }}
      </p>
      <p v-else class="muted">{{ t('operations.desk.scanHint') }}</p>
    </div>
  </div>
</template>

<style scoped>
.command-bar {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.toggles {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.segmented {
  display: inline-flex;
  padding: 3px;
  border: 1px solid var(--outline);
  border-radius: 10px;
}

.segmented button {
  padding: 6px 14px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.segmented button.active {
  background: var(--primary);
  color: #fff;
}

.manual {
  display: flex;
  gap: 8px;
  max-width: 480px;
}

.scan-video {
  width: 100%;
  max-width: 360px;
  border-radius: 12px;
  background: #000;
}

.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
