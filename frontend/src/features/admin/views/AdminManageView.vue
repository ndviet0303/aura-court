<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ErrorModal } from '@/shared/components'
import type { Court } from '@/features/court/types/court'
import { useAdminManageCourts } from '../composables/useAdminManageCourts'
import { useLifecycleCommands } from '../composables/useLifecycleCommands'
import type { LifecycleCommand } from '../types/lifecycle'

import ManageHeader from '../components/manage/ManageHeader.vue'
import ManageKpiBar from '../components/manage/ManageKpiBar.vue'
import AdminCourtsTable from '../components/manage/AdminCourtsTable.vue'
import CourtFormModal from '../components/manage/CourtFormModal.vue'
import OperationalStopDialog from '../components/lifecycle/OperationalStopDialog.vue'

// Courts and Main Operations Composable
const {
  venues,
  error,
  notice,
  saving,
  loading,
  searchQuery,
  selectedVenueFilter,
  statusFilter,
  showCourtModal,
  editingCourtId,
  courtForm,
  courtErrors,
  courtVenueOptions,
  filteredCourts,
  totalCourtsCount,
  activeCourtsCount,
  pausedCourtsCount,
  venueFilterOptions,
  statusFilterOptions,
  load,
  openCreateCourtModal,
  openEditCourtModal,
  saveCourt,
} = useAdminManageCourts()

const lifecycle = reactive(
  useLifecycleCommands(async (message) => {
    await load()
    notice.value = message
  }),
)

// The stop dialog offers the business kind only to the owner of this court's organization.
const commandCourt = ref<Court | null>(null)

function handleCommand(court: Court, command: LifecycleCommand) {
  commandCourt.value = court
  void lifecycle.run({ type: 'court', id: court.id }, command)
}

onMounted(() => {
  load()
})
</script>

<template>
  <div class="admin-manage-page">
    <!-- Top Header -->
    <ManageHeader @refresh="load" />

    <!-- KPI Stats Cards -->
    <ManageKpiBar
      :total-courts-count="totalCourtsCount"
      :active-courts-count="activeCourtsCount"
      :paused-courts-count="pausedCourtsCount"
      :loading="loading"
    />

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Courts Management Grid -->
    <AdminCourtsTable
      :courts="filteredCourts"
      :venues="venues"
      :selected-venue-filter="selectedVenueFilter"
      :status-filter="statusFilter"
      :search-query="searchQuery"
      :venue-filter-options="venueFilterOptions"
      :status-filter-options="statusFilterOptions"
      :loading="loading"
      :owner-of="lifecycle.isOwnerOf"
      :busy="lifecycle.busy"
      @update:selected-venue-filter="selectedVenueFilter = $event"
      @update:status-filter="statusFilter = $event"
      @update:search-query="searchQuery = $event"
      @create-court="openCreateCourtModal"
      @edit-court="openEditCourtModal"
      @command="handleCommand"
    />

    <!-- Modals -->
    <CourtFormModal
      v-model:code="courtForm.code"
      v-model:venue-id="courtForm.venue_id"
      v-model:sport-type="courtForm.sport_type"
      v-model:surface-type="courtForm.surface_type"
      v-model:capacity="courtForm.capacity"
      v-model:description="courtForm.description"
      v-model:images="courtForm.images"
      :show="showCourtModal"
      :editing-court-id="editingCourtId"
      :saving="saving"
      :venue-options="courtVenueOptions"
      :errors="courtErrors"
      @close="showCourtModal = false"
      @save="saveCourt"
    />

    <OperationalStopDialog
      :command="lifecycle.stopRequest?.command ?? null"
      target="court"
      :owner="lifecycle.isOwnerOf(commandCourt?.venue?.organization_id)"
      :loading="lifecycle.busy"
      @close="lifecycle.closeStop"
      @submit="lifecycle.submitStop"
    />
    <ErrorModal
      :open="lifecycle.failureOpen"
      v-bind="lifecycle.failureModal"
      @close="lifecycle.closeFailure"
    />
  </div>
</template>

<style scoped>
.admin-manage-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}
</style>
