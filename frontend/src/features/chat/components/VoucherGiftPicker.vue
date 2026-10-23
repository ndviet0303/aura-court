<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { AuraButton, SelectDropdown } from '@/shared/components'
import { chatApi } from '../api/chatApi'
import type { GiftedVoucher } from '../types/chat'

/** UC-7.3, BR-143: the owner picks a free code of the venue's running private program. */
const props = defineProps<{ venueId: number; busy: boolean }>()
const emit = defineEmits<{ gift: [code: string, note: string]; close: [] }>()
const { t } = useI18n()

const codes = ref<GiftedVoucher[]>([])
const code = ref('')
const note = ref('')
const error = ref('')
const loaded = ref(false)

const options = computed(() =>
  codes.value.map((c) => ({ value: c.code, label: `${c.code} · ${c.program}` })),
)

onMounted(async () => {
  try {
    codes.value = (await chatApi.giftable(props.venueId)).data
  } catch (e) {
    error.value = errorMessage(e, t('chat.loadError'))
  } finally {
    loaded.value = true
  }
})
</script>

<template>
  <form class="picker" @submit.prevent="code && emit('gift', code, note)">
    <p class="picker-title">{{ t('chat.voucher.pick') }}</p>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <p v-else-if="loaded && !codes.length" class="muted">{{ t('chat.voucher.none') }}</p>
    <template v-else>
      <SelectDropdown
        :model-value="code"
        :options="options"
        :placeholder="t('chat.voucher.pick')"
        variant="bordered"
        :aria-label="t('chat.voucher.pick')"
        @update:model-value="code = String($event)"
      />
      <input v-model="note" class="note" maxlength="2000" :placeholder="t('chat.voucher.note')" />
    </template>
    <div class="actions">
      <AuraButton type="button" size="sm" variant="outline" @click="emit('close')">
        {{ t('common.cancel') }}
      </AuraButton>
      <AuraButton type="submit" size="sm" :disabled="!code" :loading="busy">
        {{ t('chat.voucher.send') }}
      </AuraButton>
    </div>
  </form>
</template>

<style scoped>
.picker {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
}

.picker-title {
  margin: 0;
  font-weight: 700;
}

.note {
  padding: 8px 10px;
  border: 1px solid var(--outline);
  border-radius: 10px;
  background: var(--surface);
  color: var(--ink);
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
