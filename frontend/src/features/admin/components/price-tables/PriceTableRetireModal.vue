<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, DateTimePicker, SelectDropdown } from '@/shared/components'
import { venueInstant } from '@/core/utils/venueTime'
import type { PriceTable, PriceTableDetail, RetirePriceTablePayload } from '../../types/priceTable'

// UC-3.6: retiring stops new links; its courts keep the prices until replaced. A-1 picks a replacement of the same
// kind for some of those courts and a switch time, saved with the retirement in one confirmation.
const props = defineProps<{
  table: PriceTableDetail
  tables: PriceTable[]
  saving: boolean
  errors: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: RetirePriceTablePayload): void
}>()

const { t } = useI18n()

const linkedCourts = computed(() => [
  ...new Map(props.table.links.map((link) => [link.court_id, link.court_name])).entries(),
])
const replacementOptions = computed(() => [
  { value: 0, label: t('admin.priceTables.retire.noReplacement') },
  ...props.tables
    .filter((other) => other.id !== props.table.id && other.status === 'active')
    .filter((other) => other.kind === props.table.kind)
    .map((other) => ({ value: other.id, label: other.name })),
])
const replacementId = ref(0)
const courtIds = ref(linkedCourts.value.map(([id]) => id))
const at = ref('')
const errorList = computed(() => Object.values(props.errors).flat())

function submit() {
  emit(
    'submit',
    replacementId.value
      ? {
          replacement_table_id: replacementId.value,
          court_ids: courtIds.value,
          starts_at: at.value ? venueInstant(at.value) : undefined,
        }
      : {},
  )
}
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <form class="modal-card" data-testid="price-table-retire" @submit.prevent="submit">
      <div class="modal-header">
        <h3 class="modal-title">{{ t('admin.priceTables.retire.title', { name: table.name }) }}</h3>
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
        <p class="form-hint">{{ t('admin.priceTables.retireConfirm', { name: table.name }) }}</p>
        <p v-if="linkedCourts.length === 0" class="form-hint">
          {{ t('admin.priceTables.retire.noCourts') }}
        </p>
        <template v-else>
          <div class="form-group">
            <span class="form-label">{{ t('admin.priceTables.retire.replacementLabel') }}</span>
            <SelectDropdown
              :model-value="replacementId"
              :options="replacementOptions"
              variant="bordered"
              :aria-label="t('admin.priceTables.retire.replacementLabel')"
              @update:model-value="replacementId = Number($event)"
            />
          </div>
          <template v-if="replacementId">
            <fieldset class="form-group court-picker">
              <legend class="form-label">{{ t('admin.priceTables.link.courtsLabel') }}</legend>
              <label v-for="[id, name] in linkedCourts" :key="id" class="court-option">
                <input v-model="courtIds" type="checkbox" :value="id" />
                <span>{{ name }}</span>
              </label>
            </fieldset>
            <div class="form-group">
              <span class="form-label">{{ t('admin.priceTables.link.switchAtLabel') }}</span>
              <DateTimePicker v-model="at" />
              <span class="form-hint">{{ t('admin.priceTables.link.nowHint') }}</span>
            </div>
          </template>
        </template>

        <p v-for="message in errorList" :key="message" class="form-error">{{ message }}</p>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" @click="emit('close')">
          {{ t('admin.priceTables.form.cancel') }}
        </AuraButton>
        <AuraButton
          type="submit"
          variant="danger"
          :loading="saving"
          :disabled="!!replacementId && courtIds.length === 0"
        >
          {{ t('admin.priceTables.retireBtn') }}
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
