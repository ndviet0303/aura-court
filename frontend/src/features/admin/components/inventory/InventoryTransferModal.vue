<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { SelectDropdown, AuraButton } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { InventoryProduct, StockTransferPayload } from '../../types/inventory'

const props = defineProps<{
  show: boolean
  /** Products with the source venue's stock. */
  products: InventoryProduct[]
  venueOptions: DropdownOption[]
  fromVenueId: number | null
  saving?: boolean
  /** Field errors of the last attempt, as the server sent them (422, or 409 on quantity). */
  errors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: Omit<StockTransferPayload, 'from_venue_id'>): void
}>()

const { t } = useI18n()

const productId = ref<number | null>(null)
const toVenueId = ref<number | null>(null)
const quantity = ref(1)
const note = ref('')

// ponytail: the cap is the venue's stock; the server also subtracts what pending QR orders and tabs hold.
const movable = computed(() =>
  props.products.filter((p) => p.is_active && !p.is_service && p.stock_quantity > 0),
)
const productOptions = computed<DropdownOption[]>(() =>
  movable.value.map((p) => ({
    value: p.id,
    label: `${resolveLocalizedText(p.name)} (${p.sku})`,
    icon: 'package_2',
  })),
)
const destinationOptions = computed(() =>
  props.venueOptions.filter((o) => o.value !== props.fromVenueId),
)
const fromLabel = computed(
  () => props.venueOptions.find((o) => o.value === props.fromVenueId)?.label ?? '',
)
const selected = computed(() => movable.value.find((p) => p.id === productId.value) ?? null)
const maxQuantity = computed(() => selected.value?.stock_quantity ?? 0)
const tooMany = computed(() => quantity.value > maxQuantity.value)
const canSubmit = computed(
  () =>
    !!selected.value && !!toVenueId.value && quantity.value >= 1 && !tooMany.value && !props.saving,
)
const fieldError = (field: string) => props.errors?.[field]?.[0] ?? ''

watch(
  () => props.show,
  (open) => {
    if (!open) return
    productId.value = movable.value[0]?.id ?? null
    toVenueId.value = Number(destinationOptions.value[0]?.value) || null
    quantity.value = 1
    note.value = ''
  },
  { immediate: true },
)

function submit() {
  if (!canSubmit.value || !productId.value || !toVenueId.value) return
  emit('submit', {
    product_id: productId.value,
    to_venue_id: toVenueId.value,
    quantity: quantity.value,
    note: note.value.trim() || undefined,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="transfer-title">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">local_shipping</span>
          <div>
            <h3 id="transfer-title" class="modal-title">
              {{ t('admin.inventory.transferTitle') }}
            </h3>
            <p class="modal-sub">{{ t('admin.inventory.transferSub', { venue: fromLabel }) }}</p>
          </div>
        </div>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.cancel')"
          @click="emit('close')"
        >
          ✕
        </button>
      </div>

      <form class="transfer-form" @submit.prevent="submit">
        <div class="modal-body">
          <div class="form-group">
            <span class="form-label">{{ t('admin.inventory.transferProductLabel') }} *</span>
            <SelectDropdown
              :model-value="productId"
              :options="productOptions"
              :aria-label="t('admin.inventory.transferProductLabel')"
              variant="bordered"
              @update:model-value="productId = Number($event)"
            />
            <p v-if="!productOptions.length" class="field-hint">
              {{ t('admin.inventory.transferNothingToMove') }}
            </p>
            <p v-if="fieldError('product_id')" class="field-error">
              {{ fieldError('product_id') }}
            </p>
          </div>

          <div class="form-group">
            <span class="form-label">{{ t('admin.inventory.transferToLabel') }} *</span>
            <SelectDropdown
              :model-value="toVenueId"
              :options="destinationOptions"
              :aria-label="t('admin.inventory.transferToLabel')"
              variant="bordered"
              @update:model-value="toVenueId = Number($event)"
            />
            <p v-if="fieldError('to_venue_id')" class="field-error">
              {{ fieldError('to_venue_id') }}
            </p>
          </div>

          <div class="form-group">
            <label class="form-label" for="transfer-quantity"
              >{{ t('admin.inventory.transferQtyLabel') }} *</label
            >
            <input
              id="transfer-quantity"
              v-model.number="quantity"
              type="number"
              min="1"
              :max="maxQuantity"
              class="form-input font-mono"
              required
            />
            <p class="field-hint">
              {{
                t('admin.inventory.transferAvailable', {
                  count: maxQuantity,
                  unit: selected?.unit ?? '',
                })
              }}
            </p>
            <p v-if="tooMany" class="field-error">
              {{ t('admin.inventory.transferTooMany', { count: maxQuantity }) }}
            </p>
            <p v-else-if="fieldError('quantity')" class="field-error">
              {{ fieldError('quantity') }}
            </p>
          </div>

          <div class="form-group">
            <label class="form-label" for="transfer-note">{{
              t('admin.inventory.transferNoteLabel')
            }}</label>
            <input
              id="transfer-note"
              v-model="note"
              type="text"
              maxlength="500"
              class="form-input"
              :placeholder="t('admin.inventory.transferNotePlaceholder')"
            />
            <p v-if="fieldError('note')" class="field-error">{{ fieldError('note') }}</p>
          </div>
        </div>

        <div class="modal-footer">
          <AuraButton variant="outline" @click="emit('close')">{{ t('common.cancel') }}</AuraButton>
          <AuraButton variant="primary" type="submit" :disabled="!canSubmit">
            <span v-if="saving" class="material-symbols-outlined icon-16 spin-anim"
              >progress_activity</span
            >
            <span>{{ saving ? t('common.processing') : t('admin.inventory.transferSubmit') }}</span>
          </AuraButton>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 520px;
  max-height: calc(100vh - 32px);
  overflow-y: auto;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-title-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.modal-sub {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: var(--muted);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
}

.modal-close-btn:hover {
  color: var(--ink);
}

.modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.form-input {
  width: 100%;
  height: 42px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  outline: none;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

.field-hint {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.field-error {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: #ef4444;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.015));
}

.icon-16 {
  font-size: 16px;
}

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
