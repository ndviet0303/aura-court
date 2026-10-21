<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, DateTimePicker } from '@/shared/components'
import type { Court } from '@/features/court/types/court'

// Apply: pick courts and a switch time. End: only the time, for the court the parent names.
const props = defineProps<{
  mode: 'apply' | 'end'
  title: string
  courts: Court[]
  saving: boolean
  errors: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', courtIds: number[], at: string): void
}>()

const { t } = useI18n()

const courtIds = ref<number[]>([])
const at = ref('')
const errorList = computed(() => Object.values(props.errors).flat())
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <form
      class="modal-card"
      data-testid="price-table-link"
      @submit.prevent="emit('submit', courtIds, at)"
    >
      <div class="modal-header">
        <h3 class="modal-title">{{ title }}</h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('admin.priceTables.form.cancel')"
          @click="emit('close')"
        >
          ✕
        </button>
      </div>

      <div class="modal-body">
        <fieldset v-if="mode === 'apply'" class="form-group court-picker">
          <legend class="form-label">{{ t('admin.priceTables.link.courtsLabel') }}</legend>
          <p v-if="courts.length === 0" class="form-hint">
            {{ t('admin.priceTables.link.noCourts') }}
          </p>
          <label v-for="court in courts" :key="court.id" class="court-option">
            <input v-model="courtIds" type="checkbox" :value="court.id" />
            <span>{{ resolveLocalizedText(court.name) }}</span>
          </label>
        </fieldset>

        <div class="form-group">
          <span class="form-label">{{
            t(
              mode === 'apply'
                ? 'admin.priceTables.link.switchAtLabel'
                : 'admin.priceTables.link.endAtLabel',
            )
          }}</span>
          <DateTimePicker v-model="at" />
          <span class="form-hint">{{
            t(
              mode === 'apply'
                ? 'admin.priceTables.link.nowHint'
                : 'admin.priceTables.link.endNowHint',
            )
          }}</span>
          <span class="form-hint">{{
            t(
              mode === 'apply'
                ? 'admin.priceTables.link.applyHint'
                : 'admin.priceTables.link.endHint',
            )
          }}</span>
        </div>

        <p v-for="message in errorList" :key="message" class="form-error">{{ message }}</p>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" @click="emit('close')">
          {{ t('admin.priceTables.form.cancel') }}
        </AuraButton>
        <AuraButton
          type="submit"
          :variant="mode === 'apply' ? 'primary' : 'danger'"
          :loading="saving"
          :disabled="mode === 'apply' && courtIds.length === 0"
        >
          {{
            t(
              mode === 'apply'
                ? 'admin.priceTables.link.submitApply'
                : 'admin.priceTables.link.submitEnd',
            )
          }}
        </AuraButton>
      </div>
    </form>
  </div>
</template>

<style scoped>
.court-picker {
  margin: 0 0 16px;
  padding: 0;
  border: 0;
}

.court-option {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  cursor: pointer;
}
</style>
