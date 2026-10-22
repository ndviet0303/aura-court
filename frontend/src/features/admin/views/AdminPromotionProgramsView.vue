<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, ErrorModal, SelectDropdown } from '@/shared/components'
import { usePromotionPrograms } from '../composables/usePromotionPrograms'
import PromotionProgramList from '../components/promotion-programs/PromotionProgramList.vue'
import PromotionProgramDetailCard from '../components/promotion-programs/PromotionProgramDetailCard.vue'
import PromotionProgramFormModal from '../components/promotion-programs/PromotionProgramFormModal.vue'
import PromotionProgramCodesModal from '../components/promotion-programs/PromotionProgramCodesModal.vue'

const { t } = useI18n()
const {
  venues,
  venueId,
  courts,
  programs,
  detail,
  codes,
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
  openCodes,
  create,
  update,
  stop,
} = usePromotionPrograms()

// The form stays open on failure, so the entered terms survive the error dialog.
const form = ref<'create' | 'edit' | null>(null)

const venueOptions = computed(() =>
  venues.value.map((venue) => ({ value: venue.id, label: resolveLocalizedText(venue.name) })),
)

function openForm(mode: 'create' | 'edit') {
  fieldErrors.value = {}
  form.value = mode
}

async function submitCreate(payload: Parameters<typeof create>[0]) {
  if (await create(payload)) form.value = null
}

async function submitUpdate(payload: Parameters<typeof update>[0]) {
  if (await update(payload)) form.value = null
}

onMounted(loadVenues)
</script>

<template>
  <div class="admin-promotions-view">
    <header class="page-header">
      <div>
        <h1 class="page-title">{{ t('admin.promotions.title') }}</h1>
        <p class="page-sub">{{ t('admin.promotions.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <SelectDropdown
          v-if="venues.length > 0"
          :model-value="venueId"
          :options="venueOptions"
          variant="bordered"
          :aria-label="t('admin.promotions.venueLabel')"
          @update:model-value="venueId = Number($event)"
        />
        <AuraButton variant="secondary" icon="refresh" :disabled="loading" @click="loadVenue">
          {{ t('admin.promotions.refreshBtn') }}
        </AuraButton>
        <AuraButton variant="primary" icon="add" :disabled="!venueId" @click="openForm('create')">
          {{ t('admin.promotions.createBtn') }}
        </AuraButton>
      </div>
    </header>

    <div v-if="notice" class="alert-pill" role="status">{{ notice }}</div>
    <p v-if="!loading && venues.length === 0" class="page-sub">
      {{ t('admin.promotions.noVenues') }}
    </p>

    <div v-else class="promotions-layout">
      <PromotionProgramList
        :programs="programs"
        :selected-id="detail?.id ?? null"
        :loading="loading"
        @select="openDetail"
      />
      <PromotionProgramDetailCard
        v-if="detail"
        :program="detail"
        :saving="saving"
        @edit="openForm('edit')"
        @stop="stop"
        @codes="openCodes"
      />
      <p v-else-if="programs.length > 0" class="page-sub">{{ t('admin.promotions.selectHint') }}</p>
    </div>

    <PromotionProgramFormModal
      v-if="form === 'create'"
      :courts="courts"
      :saving="saving"
      :errors="fieldErrors"
      @close="form = null"
      @submit="submitCreate"
    />
    <PromotionProgramFormModal
      v-if="form === 'edit' && detail"
      :program="detail"
      :courts="courts"
      :saving="saving"
      :errors="fieldErrors"
      @close="form = null"
      @update="submitUpdate"
    />
    <PromotionProgramCodesModal
      v-if="codes && detail && codes.program_id === detail.id"
      :title="t('admin.promotions.codes.title', { name: detail.name })"
      :codes="codes"
      @close="codes = null"
    />
    <ErrorModal :open="errorOpen" v-bind="errorDialogProps" @close="closeError" />
  </div>
</template>

<style scoped>
.admin-promotions-view {
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

.promotions-layout {
  display: grid;
  grid-template-columns: minmax(240px, 1fr) 2fr;
  align-items: start;
  gap: 20px;
}

@media (max-width: 900px) {
  .promotions-layout {
    grid-template-columns: 1fr;
  }
}
</style>
