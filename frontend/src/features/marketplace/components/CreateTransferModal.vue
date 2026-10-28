<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton, CurrencyInput, SelectDropdown } from '@/shared/components'
import { useCreateTransfer, type CreateTransferContext } from '../composables/useCreateTransfer'
import TransferBookingLine from './TransferBookingLine.vue'

// UC-9.7: opened from the listing or from the chat; chat wording never changes what is sent (BR-183).
const props = defineProps<CreateTransferContext>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()

const {
  listing,
  contacts,
  available,
  buyerId,
  selected,
  prices,
  refusals,
  problems,
  total,
  loading,
  submitting,
  error,
  canSubmit,
  toggle,
  pickBuyer,
  submit,
} = useCreateTransfer(props)

const buyers = computed(() => contacts.value.map((c) => ({ value: c.user.id, label: c.user.name })))
const problemText = (id: number, max: number) =>
  problems.value[id]
    ? t(`resale.priceError.${problems.value[id]}`, { amount: formatCurrency(max) })
    : ''
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div
      class="modal-card modal-lg"
      role="dialog"
      aria-modal="true"
      aria-labelledby="create-transfer-title"
    >
      <div class="modal-header">
        <h3 id="create-transfer-title" class="modal-title">{{ t('resale.create.title') }}</h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body create-body">
        <p v-if="loading" class="muted">{{ t('common.loading') }}</p>
        <template v-else-if="listing?.is_mine">
          <div class="form-group">
            <span class="form-label">{{ t('resale.create.buyer') }} *</span>
            <p v-if="!buyers.length" class="muted">{{ t('resale.create.noContacts') }}</p>
            <SelectDropdown
              v-else
              :model-value="buyerId"
              :options="buyers"
              variant="bordered"
              :placeholder="t('resale.create.buyerPick')"
              :aria-label="t('resale.create.buyer')"
              @update:model-value="pickBuyer(Number($event))"
            />
          </div>

          <span class="form-label">{{ t('resale.create.items') }} *</span>
          <p v-if="!available.length" class="muted">{{ t('resale.create.noItems') }}</p>
          <ul class="create-items">
            <li v-for="item in available" :key="item.reservation_id" class="create-item">
              <label class="create-pick">
                <input
                  type="checkbox"
                  :checked="selected.includes(item.reservation_id)"
                  :aria-label="t('resale.pickItem')"
                  @change="toggle(item.reservation_id)"
                />
                <TransferBookingLine
                  :venue="item.venue"
                  :court="item.court"
                  :starts-at="item.starts_at"
                  :ends-at="item.ends_at"
                />
              </label>
              <label v-if="selected.includes(item.reservation_id)" class="create-price">
                <span class="muted">{{ t('resale.create.agreedPrice') }}</span>
                <CurrencyInput
                  v-model="prices[item.reservation_id]"
                  :max="item.paid_amount"
                  size="sm"
                />
                <span class="muted">{{
                  t('resale.form.maxPrice', { amount: formatCurrency(item.paid_amount) })
                }}</span>
              </label>
              <p v-if="problemText(item.reservation_id, item.paid_amount)" class="refusal">
                {{ problemText(item.reservation_id, item.paid_amount) }}
              </p>
              <p v-if="refusals[item.reservation_id]" class="refusal" role="alert">
                {{ refusals[item.reservation_id] }}
              </p>
            </li>
          </ul>
          <p class="muted">{{ t('resale.create.hint') }}</p>
        </template>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      </div>

      <div class="modal-footer create-footer">
        <strong v-if="selected.length">{{
          t('resale.create.total', { amount: formatCurrency(total) })
        }}</strong>
        <AuraButton variant="outline" :disabled="submitting" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          :loading="submitting"
          :disabled="!canSubmit"
          data-testid="create-transfer-submit"
          @click="submit"
        >
          {{ t('resale.create.submit') }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.create-body {
  display: grid;
  gap: 12px;
}
.create-items {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.create-item {
  display: grid;
  gap: 8px;
  padding: 10px 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
}
.create-pick,
.create-price {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 10px;
  align-items: center;
  min-width: 0;
}
.create-footer {
  flex-wrap: wrap;
}
.create-footer strong {
  margin-right: auto;
}
.muted {
  margin: 0;
  color: var(--muted);
  font-size: 12px;
}
.refusal {
  margin: 0;
  color: #dc2626;
  font-size: 12px;
}
</style>
