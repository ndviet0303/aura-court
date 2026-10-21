<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, ErrorModal, SelectDropdown } from '@/shared/components'
import type { PriceTableLink } from '../types/priceTable'
import { usePriceTables } from '../composables/usePriceTables'
import { usePriceTableLabels } from '../composables/usePriceTableLabels'
import PriceTableList from '../components/price-tables/PriceTableList.vue'
import PriceTableDetailCard from '../components/price-tables/PriceTableDetailCard.vue'
import PriceTableCreateModal from '../components/price-tables/PriceTableCreateModal.vue'
import PriceTableLinkModal from '../components/price-tables/PriceTableLinkModal.vue'

const { t } = useI18n()
const { kind } = usePriceTableLabels()
const {
  venues,
  venueId,
  courts,
  tables,
  detail,
  loading,
  saving,
  notice,
  fieldErrors,
  errorOpen,
  errorDialogProps,
  closeError,
  loadVenues,
  loadVenue,
  openDetail,
  create,
  applyLinks,
  endLink,
  retire,
} = usePriceTables()

const showCreate = ref(false)
// The link modal stays open on failure, so the chosen courts and time survive the error dialog.
const linkTarget = ref<{ mode: 'apply' } | { mode: 'end'; link: PriceTableLink } | null>(null)

const venueOptions = computed(() =>
  venues.value.map((venue) => ({ value: venue.id, label: resolveLocalizedText(venue.name) })),
)
const linkTitle = computed(() => {
  const target = linkTarget.value
  if (!target || !detail.value) return ''
  return target.mode === 'apply'
    ? t('admin.priceTables.link.applyTitle', { name: detail.value.name })
    : t('admin.priceTables.link.endTitle', {
        kind: kind(detail.value.kind),
        court: target.link.court_name,
      })
})

function openCreate() {
  fieldErrors.value = {}
  showCreate.value = true
}

function openLink(target: NonNullable<typeof linkTarget.value>) {
  fieldErrors.value = {}
  linkTarget.value = target
}

async function submitCreate(payload: Parameters<typeof create>[0]) {
  if (await create(payload)) showCreate.value = false
}

async function submitLink(courtIds: number[], at: string) {
  const target = linkTarget.value
  if (!target) return
  const done =
    target.mode === 'apply'
      ? await applyLinks(courtIds, at)
      : await endLink(target.link.court_id, at)
  if (done) linkTarget.value = null
}

onMounted(loadVenues)
</script>

<template>
  <div class="admin-price-tables-view">
    <header class="page-header">
      <div>
        <h1 class="page-title">{{ t('admin.priceTables.title') }}</h1>
        <p class="page-sub">{{ t('admin.priceTables.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <SelectDropdown
          v-if="venues.length > 0"
          :model-value="venueId"
          :options="venueOptions"
          variant="bordered"
          :aria-label="t('admin.priceTables.venueLabel')"
          @update:model-value="venueId = Number($event)"
        />
        <AuraButton variant="secondary" icon="refresh" :disabled="loading" @click="loadVenue">
          {{ t('admin.priceTables.refreshBtn') }}
        </AuraButton>
        <AuraButton variant="primary" icon="add" :disabled="!venueId" @click="openCreate">
          {{ t('admin.priceTables.createBtn') }}
        </AuraButton>
      </div>
    </header>

    <div v-if="notice" class="alert-pill" role="status">{{ notice }}</div>
    <p v-if="!loading && venues.length === 0" class="page-sub">
      {{ t('admin.priceTables.noVenues') }}
    </p>

    <div v-else class="price-tables-layout">
      <PriceTableList
        :tables="tables"
        :selected-id="detail?.id ?? null"
        :loading="loading"
        @select="openDetail"
      />
      <PriceTableDetailCard
        v-if="detail"
        :table="detail"
        :saving="saving"
        @apply="openLink({ mode: 'apply' })"
        @end-link="openLink({ mode: 'end', link: $event })"
        @retire="retire"
      />
      <p v-else-if="tables.length > 0" class="page-sub">{{ t('admin.priceTables.selectHint') }}</p>
    </div>

    <PriceTableCreateModal
      v-if="showCreate"
      :saving="saving"
      :errors="fieldErrors"
      @close="showCreate = false"
      @submit="submitCreate"
    />
    <PriceTableLinkModal
      v-if="linkTarget"
      :mode="linkTarget.mode"
      :title="linkTitle"
      :courts="courts"
      :saving="saving"
      :errors="fieldErrors"
      @close="linkTarget = null"
      @submit="submitLink"
    />
    <ErrorModal :open="errorOpen" v-bind="errorDialogProps" @close="closeError" />
  </div>
</template>

<style scoped>
.admin-price-tables-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.page-header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}

.page-title {
  margin: 0;
  font-size: 22px;
}

.page-sub {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 14px;
}

.header-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.price-tables-layout {
  display: grid;
  grid-template-columns: minmax(240px, 1fr) 2fr;
  align-items: start;
  gap: 20px;
}

@media (max-width: 900px) {
  .price-tables-layout {
    grid-template-columns: 1fr;
  }
}
</style>
