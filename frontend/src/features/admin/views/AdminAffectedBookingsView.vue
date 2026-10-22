<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import PlatformFeePager from '@/features/platform-fees/components/PlatformFeePager.vue'
import { useAffectedCases } from '../composables/useAffectedCases'
import AffectedCaseFilters from '../components/affected/AffectedCaseFilters.vue'
import AffectedCasesTable from '../components/affected/AffectedCasesTable.vue'
import CaseActionModal from '../components/affected/CaseActionModal.vue'

// Tasks 4.14 / 4.17: the owner's and assigned staff's page for bookings affected by operational changes.
const { t } = useI18n()
const {
  filters,
  setFilter,
  venues,
  filterCourts,
  cases,
  page,
  lastPage,
  loading,
  error,
  notice,
  action,
  saving,
  formError,
  fieldErrors,
  isOwnerOfCase,
  moveTargets,
  load,
  open,
  submit,
} = useAffectedCases()

const targets = computed(() =>
  action.value?.kind === 'move-court' ? moveTargets(action.value.row) : [],
)
const owner = computed(() => !!action.value && isOwnerOfCase(action.value.row))
</script>

<template>
  <div class="affected-page">
    <header class="affected-header">
      <div>
        <h1 class="affected-title">{{ t('admin.affected.title') }}</h1>
        <p class="muted">{{ t('admin.affected.subtitle') }}</p>
      </div>
      <AuraButton variant="outline" size="sm" :loading="loading" @click="load(page)">
        {{ t('common.refresh') }}
      </AuraButton>
    </header>

    <section class="affected-card">
      <AffectedCaseFilters
        :filters="filters"
        :venues="venues"
        :courts="filterCourts"
        @change="setFilter"
      />
    </section>

    <p v-if="notice" class="affected-notice" role="status">{{ notice }}</p>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>

    <section class="affected-card">
      <p v-if="loading && !cases.length" class="muted">{{ t('common.loading') }}</p>
      <p v-else-if="!cases.length && !error" class="muted">{{ t('admin.affected.empty') }}</p>
      <AffectedCasesTable
        v-else-if="cases.length"
        :cases="cases"
        :is-owner-of-case="isOwnerOfCase"
        @action="open"
      />
    </section>

    <PlatformFeePager
      :page="page"
      :last-page="lastPage"
      :loading="loading"
      :label="t('admin.affected.title')"
      @go="load"
    />

    <CaseActionModal
      :action="action"
      :targets="targets"
      :owner="owner"
      :busy="saving"
      :error="formError"
      :field-errors="fieldErrors"
      @close="action = null"
      @submit="submit"
    />
  </div>
</template>

<style scoped>
.affected-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1280px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.affected-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.affected-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.muted {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 13px;
}

.affected-card {
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.affected-notice {
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.12);
  color: #047857;
  font-size: 13px;
  font-weight: 600;
}
</style>
