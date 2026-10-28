<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton } from '@/shared/components'
import TransferCandidateList from '../components/TransferCandidateList.vue'
import { NOTE_MAX, useTransferListingForm } from '../composables/useTransferListingForm'

// UC-9.1, UC-9.2: list one, several or all remaining bookings of a recurring schedule, each priced.
const { t } = useI18n()
const {
  candidates,
  selected,
  prices,
  refusals,
  problems,
  note,
  total,
  groupRemaining,
  loading,
  submitting,
  error,
  canSubmit,
  select,
  selectGroup,
  submit,
} = useTransferListingForm()
</script>

<template>
  <main class="resale-page">
    <RouterLink :to="{ name: 'resale-marketplace' }" class="back"
      >← {{ t('resale.back') }}</RouterLink
    >
    <header>
      <h1>{{ t('resale.form.title') }}</h1>
      <p class="muted">{{ t('resale.form.subtitle') }}</p>
    </header>

    <p v-if="loading" class="muted">{{ t('common.loading') }}</p>
    <p v-else-if="!candidates.length && !error" class="muted">{{ t('resale.form.empty') }}</p>
    <template v-else>
      <div v-if="groupRemaining.length > 1" class="group-row">
        <AuraButton variant="outline" size="sm" :disabled="submitting" @click="selectGroup">
          {{ t('resale.form.selectGroup', { count: groupRemaining.length }) }}
        </AuraButton>
      </div>
      <TransferCandidateList
        :candidates="candidates"
        :selected="selected"
        :prices="prices"
        :problems="problems"
        :refusals="refusals"
        :disabled="submitting"
        @select="select"
        @price="(id, price) => (prices[id] = price)"
      />
      <label class="form-group">
        <span class="form-label">{{ t('resale.note') }}</span>
        <textarea
          v-model="note"
          rows="3"
          class="form-textarea"
          :maxlength="NOTE_MAX"
          :placeholder="t('resale.form.notePlaceholder')"
        />
        <span class="form-hint">{{ note.length }}/{{ NOTE_MAX }}</span>
      </label>
      <div class="submit-row">
        <span>{{
          t('resale.form.selected', { count: selected.length, amount: formatCurrency(total) })
        }}</span>
        <AuraButton
          :loading="submitting"
          :disabled="!canSubmit"
          data-testid="listing-submit"
          @click="submit"
        >
          {{ t('resale.form.submit') }}
        </AuraButton>
      </div>
      <p class="muted">{{ t('resale.form.notice') }}</p>
    </template>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
  </main>
</template>

<style scoped>
.resale-page {
  display: grid;
  gap: 14px;
  max-width: 880px;
  margin: 0 auto;
  padding: 24px 16px 48px;
  color: var(--ink);
}
.back {
  color: var(--muted);
  font-size: 13px;
}
h1 {
  margin: 0 0 4px;
  font-size: 22px;
}
.group-row,
.submit-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  justify-content: space-between;
}
.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
