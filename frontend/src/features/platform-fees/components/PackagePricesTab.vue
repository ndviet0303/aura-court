<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, CurrencyInput, DateTimePicker, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import { venueLocalToUtc } from '@/core/utils/venueTime'
import { usePackagePrices } from '../composables/usePackagePrices'
import { PACKAGE_TERMS } from '../types/platformFee'
import PackagePriceTable from './PackagePriceTable.vue'

const { t } = useI18n()
const { terms, loading, error, saving, notice, saveError, fieldErrors, schedule } =
  usePackagePrices()

const term = ref(30)
const amount = ref<number | null>(null)
// Venue wall-clock "YYYY-MM-DDTHH:mm"; empty means the price takes effect now.
const effectiveAt = ref('')

const termLabel = (days: number) => t('admin.platformFees.prices.termLabel', { days })
const termOptions = computed<DropdownOption[]>(() =>
  PACKAGE_TERMS.map((days) => ({ value: days, label: termLabel(days) })),
)

async function submit() {
  const saved = await schedule({
    term_days: term.value,
    amount: amount.value,
    effective_at: effectiveAt.value ? venueLocalToUtc(effectiveAt.value) : undefined,
  })
  if (saved) {
    amount.value = null
    effectiveAt.value = ''
  }
}
</script>

<template>
  <section class="prices-tab">
    <p v-if="notice" class="notice" role="status">{{ notice }}</p>
    <p v-if="saveError || error" class="error" role="alert">{{ saveError || error }}</p>

    <p v-if="loading && !terms.length" class="loading">{{ t('common.loading') }}</p>
    <PackagePriceTable v-else-if="terms.length" :terms="terms" />

    <form class="price-form" data-testid="package-price-form" @submit.prevent="submit">
      <h2 class="form-title">{{ t('admin.platformFees.prices.form.title') }}</h2>
      <div class="form-fields">
        <div class="form-group">
          <span class="form-label">{{ t('admin.platformFees.prices.form.term') }}</span>
          <SelectDropdown
            :model-value="term"
            :options="termOptions"
            variant="bordered"
            :aria-label="t('admin.platformFees.prices.form.term')"
            data-testid="package-price-term"
            @update:model-value="(value) => (term = Number(value))"
          />
          <p v-if="fieldErrors.term_days" class="form-error">{{ fieldErrors.term_days[0] }}</p>
        </div>

        <label class="form-group">
          <span class="form-label">{{ t('admin.platformFees.prices.form.amount') }}</span>
          <CurrencyInput v-model="amount" data-testid="package-price-amount" />
          <span v-if="fieldErrors.amount" class="form-error">{{ fieldErrors.amount[0] }}</span>
        </label>

        <div
          class="form-group"
          role="group"
          :aria-label="t('admin.platformFees.prices.form.effectiveAt')"
        >
          <span class="form-label">{{ t('admin.platformFees.prices.form.effectiveAt') }}</span>
          <DateTimePicker v-model="effectiveAt" data-testid="package-price-effective-at" />
          <p class="form-hint">{{ t('admin.platformFees.prices.form.effectiveAtHint') }}</p>
          <p v-if="fieldErrors.effective_at" class="form-error">
            {{ fieldErrors.effective_at[0] }}
          </p>
        </div>
      </div>
      <AuraButton type="submit" icon="sell" :loading="saving" data-testid="package-price-submit">
        {{ t('admin.platformFees.prices.form.submit') }}
      </AuraButton>
    </form>
  </section>
</template>

<style scoped>
.prices-tab {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.notice,
.error,
.loading {
  margin: 0;
}

.notice {
  color: var(--success-text);
}

.error {
  color: var(--error-text);
}

.loading {
  color: var(--muted);
}

.price-form {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.form-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.form-fields {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
  width: 100%;
}

.form-fields .form-group {
  margin-bottom: 0;
}
</style>
