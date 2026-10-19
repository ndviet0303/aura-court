<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { Court, Venue } from '@/features/court/types/court'
import { AuraButton, SelectDropdown } from '@/shared/components'

const props = defineProps<{
  courts: Court[]
  venues: Venue[]
  selectedVenueFilter: number | 'all'
  statusFilter: 'all' | 'active' | 'inactive'
  searchQuery: string
  venueFilterOptions: Array<{ value: any; label: string; sublabel?: string; icon?: string }>
  statusFilterOptions: Array<{ value: any; label: string; icon?: string }>
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:selectedVenueFilter', val: number | 'all'): void
  (e: 'update:statusFilter', val: 'all' | 'active' | 'inactive'): void
  (e: 'update:searchQuery', val: string): void
  (e: 'createCourt'): void
  (e: 'editCourt', court: Court): void
  (e: 'openPriceModal', court: Court): void
  (e: 'openBlockModal', court: Court): void
  (e: 'toggleStatus', court: Court): void
}>()

const { t } = useI18n()

const PAGE_SIZE = 24
const currentPage = ref(1)

const totalCourts = computed(() => props.courts.length)
const totalPages = computed(() => Math.max(1, Math.ceil(totalCourts.value / PAGE_SIZE)))

watch(
  () => [props.courts, props.searchQuery, props.selectedVenueFilter, props.statusFilter],
  () => {
    currentPage.value = 1
  },
)

const paginatedCourts = computed(() => {
  const start = (currentPage.value - 1) * PAGE_SIZE
  return props.courts.slice(start, start + PAGE_SIZE)
})

function goToPage(page: number) {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    const el = document.querySelector('.courts-filter-toolbar')
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    }
  }
}
</script>

<template>
  <div class="courts-table-wrapper">
    <!-- Search & Filter Controls -->
    <div class="courts-filter-toolbar">
      <div class="search-input-wrap">
        <span class="material-symbols-outlined search-ico">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="court-search-input"
          :placeholder="t('admin.manage.searchCourtPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="clear-search-btn"
          :title="t('common.clear') || 'Xoá'"
          @click="emit('update:searchQuery', '')"
        >
          <span class="material-symbols-outlined icon-14">close</span>
        </button>
      </div>

      <div class="filter-dropdowns-group">
        <div class="filter-dropdown-wrap venue-filter-wrap">
          <SelectDropdown
            :model-value="selectedVenueFilter"
            :options="venueFilterOptions"
            @update:model-value="
              emit('update:selectedVenueFilter', $event === 'all' ? 'all' : Number($event))
            "
          />
        </div>

        <div class="filter-dropdown-wrap status-filter-wrap">
          <SelectDropdown
            :model-value="statusFilter"
            :options="statusFilterOptions"
            @update:model-value="
              emit('update:statusFilter', $event as 'all' | 'active' | 'inactive')
            "
          />
        </div>

        <AuraButton variant="primary" class="add-court-btn" @click="emit('createCourt')">
          <span class="material-symbols-outlined icon-18">add</span>
          <span>{{ t('admin.manage.addNewCourt') }}</span>
        </AuraButton>
      </div>
    </div>

    <!-- Skeleton Loading Grid -->
    <div v-if="loading" class="courts-admin-grid">
      <div v-for="i in 6" :key="i" class="court-admin-card skeleton-card">
        <div class="court-card-img skeleton-item skeleton-img">
          <div class="skeleton-tag skeleton-item"></div>
        </div>
        <div class="court-card-body">
          <div class="court-title-row">
            <div class="skeleton-item skeleton-title"></div>
            <div class="skeleton-item skeleton-venue"></div>
          </div>
          <div class="skeleton-item skeleton-desc"></div>
          <div class="skeleton-item skeleton-desc short"></div>
          <div class="court-stats-row">
            <div class="skeleton-item skeleton-stat"></div>
            <div class="skeleton-item skeleton-stat"></div>
          </div>
          <div class="court-actions-footer">
            <div class="skeleton-item skeleton-btn"></div>
            <div class="skeleton-item skeleton-btn"></div>
            <div class="skeleton-item skeleton-btn"></div>
            <div class="skeleton-item skeleton-btn-toggle"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="courts.length === 0" class="empty-courts-state">
      <span class="material-symbols-outlined empty-ico">sports_tennis</span>
      <h3>{{ t('admin.manage.noCourtsFound') }}</h3>
      <p>{{ t('admin.manage.noCourtsSub') }}</p>
    </div>

    <!-- Real Courts Cards Grid -->
    <div v-else class="courts-admin-grid">
      <div
        v-for="court in paginatedCourts"
        :key="court.id"
        class="court-admin-card"
        :class="{ inactive: court.is_active === false }"
      >
        <div class="court-card-img">
          <img
            v-if="court.image_url"
            :src="court.image_url"
            :alt="resolveLocalizedText(court.name)"
            loading="lazy"
          />
          <div v-else class="no-img-box">
            <span class="material-symbols-outlined fallback-icon">sports_tennis</span>
          </div>

          <span
            class="status-chip"
            :class="court.is_active !== false ? 'chip-active' : 'chip-inactive'"
          >
            {{
              court.is_active !== false
                ? t('admin.manage.statusOpen')
                : t('admin.manage.statusClosed')
            }}
          </span>

          <span class="sport-chip">
            {{ t(`sportTypes.${court.sport_type}`) }}
          </span>
        </div>

        <div class="court-card-body">
          <div class="court-title-row">
            <h4 class="court-name">{{ resolveLocalizedText(court.name) }}</h4>
            <span class="venue-name">
              <span class="material-symbols-outlined icon-14">location_on</span>
              <span>{{ resolveLocalizedText(court.venue?.name) }}</span>
            </span>
          </div>

          <p class="court-desc">
            {{ resolveLocalizedText(court.description) || t('admin.manage.standardDesc') }}
          </p>

          <div v-if="court.surface_type || court.capacity" class="court-specs">
            <span v-if="court.surface_type" class="spec-badge">{{
              t(
                `admin.manage.surface${court.surface_type.charAt(0).toUpperCase()}${court.surface_type.slice(1)}`,
              )
            }}</span>
            <span v-if="court.capacity" class="spec-badge">{{
              t('admin.manage.courtCapacityValue', { count: court.capacity })
            }}</span>
          </div>

          <div class="court-stats-row">
            <span class="stat-badge">
              <span class="material-symbols-outlined icon-14">schedule</span>
              <span>{{
                t('admin.manage.priceSlotsCount', {
                  count: court.price_slots_count ?? court.price_slots?.length ?? 0,
                })
              }}</span>
            </span>
            <span class="stat-badge text-yellow">
              <span class="material-symbols-outlined icon-14">build</span>
              <span>{{ t('admin.manage.blocksCount', { count: court.blocks?.length || 0 }) }}</span>
            </span>
          </div>

          <div class="court-actions-footer">
            <button
              type="button"
              class="btn-court-action"
              :title="t('admin.manage.btnPricing')"
              @click="emit('openPriceModal', court)"
            >
              <span class="material-symbols-outlined icon-14">price_change</span>
              <span>{{ t('admin.manage.btnPricing') }}</span>
            </button>
            <button
              type="button"
              class="btn-court-action"
              :title="t('admin.manage.btnBlock')"
              @click="emit('openBlockModal', court)"
            >
              <span class="material-symbols-outlined icon-14">lock</span>
              <span>{{ t('admin.manage.btnBlock') }}</span>
            </button>
            <button
              type="button"
              class="btn-court-action"
              :title="t('admin.manage.btnEdit')"
              @click="emit('editCourt', court)"
            >
              <span class="material-symbols-outlined icon-14">edit</span>
              <span>{{ t('admin.manage.btnEdit') }}</span>
            </button>
            <button
              type="button"
              class="btn-court-action btn-toggle"
              :title="
                court.is_active !== false
                  ? t('admin.manage.statusClosed')
                  : t('admin.manage.statusOpen')
              "
              @click="emit('toggleStatus', court)"
            >
              <span class="material-symbols-outlined icon-16">{{
                court.is_active !== false ? 'pause' : 'play_arrow'
              }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination Navigation Controls -->
    <div v-if="!loading && totalCourts > PAGE_SIZE" class="courts-pagination-bar">
      <div class="pagination-info">
        {{
          t('admin.manage.showingCourts', {
            from: (currentPage - 1) * PAGE_SIZE + 1,
            to: Math.min(currentPage * PAGE_SIZE, totalCourts),
            total: totalCourts,
          })
        }}
      </div>

      <div class="pagination-controls">
        <button
          type="button"
          class="btn-page"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >
          <span class="material-symbols-outlined icon-16">chevron_left</span>
          <span>{{ t('admin.manage.prevPage') }}</span>
        </button>

        <div class="page-pills-list">
          <button
            v-for="p in totalPages"
            :key="p"
            type="button"
            class="page-pill-btn"
            :class="{ active: p === currentPage }"
            v-show="p === 1 || p === totalPages || Math.abs(p - currentPage) <= 2"
            @click="goToPage(p)"
          >
            {{ p }}
          </button>
        </div>

        <button
          type="button"
          class="btn-page"
          :disabled="currentPage === totalPages"
          @click="goToPage(currentPage + 1)"
        >
          <span>{{ t('admin.manage.nextPage') }}</span>
          <span class="material-symbols-outlined icon-16">chevron_right</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.courts-table-wrapper {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.courts-filter-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 10px 14px;
  border-radius: 14px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.search-input-wrap {
  position: relative;
  flex: 1 1 260px;
  min-width: 220px;
  display: flex;
  align-items: center;
}

.search-ico {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
  color: var(--muted);
  pointer-events: none;
}

.court-search-input {
  width: 100%;
  height: 40px;
  padding: 0 34px 0 38px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 13px;
  outline: none;
  transition: all 0.2s ease;
}

.court-search-input:focus {
  border-color: var(--primary, #6b2d3f);
  background: var(--surface);
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.1);
}

:global(html.dark) .court-search-input {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.1);
}

:global(html.dark) .court-search-input:focus {
  border-color: var(--accent, #c9a15a);
  box-shadow: 0 0 0 3px rgba(201, 161, 90, 0.15);
}

.clear-search-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 50%;
  transition: all 0.15s;
}

.clear-search-btn:hover {
  color: var(--ink);
  background: var(--surface-subtle);
}

.filter-dropdowns-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-dropdown-wrap {
  position: relative;
}

.venue-filter-wrap {
  width: 220px;
  flex-shrink: 0;
}

.status-filter-wrap {
  width: 160px;
  flex-shrink: 0;
}

.filter-dropdown-wrap :deep(.custom-dropdown-trigger) {
  height: 40px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  font-size: 13px;
  font-weight: 600;
  padding: 0 12px;
  transition: all 0.2s ease;
}

.filter-dropdown-wrap :deep(.custom-dropdown.open .custom-dropdown-trigger),
.filter-dropdown-wrap :deep(.custom-dropdown-trigger:hover) {
  border-color: var(--primary, #6b2d3f);
  background: var(--surface);
}

:global(html.dark) .filter-dropdown-wrap :deep(.custom-dropdown-trigger) {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.1);
}

:global(html.dark) .filter-dropdown-wrap :deep(.custom-dropdown.open .custom-dropdown-trigger),
:global(html.dark) .filter-dropdown-wrap :deep(.custom-dropdown-trigger:hover) {
  border-color: var(--accent, #c9a15a);
}

.add-court-btn {
  height: 40px !important;
  border-radius: 10px !important;
  padding: 0 16px !important;
  font-size: 13px !important;
  font-weight: 700 !important;
  white-space: nowrap !important;
}

:global(html.dark) .add-court-btn {
  background-color: var(--primary, #6b2d3f) !important;
  color: #faf7f3 !important;
  border: 1px solid rgba(201, 161, 90, 0.35) !important;
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.4) !important;
}

:global(html.dark) .add-court-btn:hover {
  background-color: #8b3a52 !important;
  border-color: var(--accent, #c9a15a) !important;
}

.empty-courts-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
}

.empty-ico {
  font-size: 48px;
  color: var(--muted);
  margin-bottom: 12px;
}

.courts-admin-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.court-admin-card {
  display: flex;
  flex-direction: column;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.court-admin-card:hover {
  transform: translateY(-3px);
  border-color: var(--border);
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.07);
}

:global(html.dark) .court-admin-card {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
  border-color: rgba(255, 255, 255, 0.08);
}

:global(html.dark) .court-admin-card:hover {
  border-color: var(--accent, #c9a15a);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
}

.court-admin-card.inactive {
  opacity: 0.65;
}

.court-card-img {
  position: relative;
  width: 100%;
  height: 160px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
}

.court-card-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.no-img-box {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}

.fallback-icon {
  font-size: 44px;
  color: var(--muted);
  opacity: 0.4;
}

.status-chip {
  position: absolute;
  top: 10px;
  left: 10px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 700;
  backdrop-filter: blur(8px);
}

.chip-active {
  background: rgba(16, 185, 129, 0.9);
  color: #fff;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.chip-inactive {
  background: rgba(239, 68, 68, 0.9);
  color: #fff;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.sport-chip {
  position: absolute;
  top: 10px;
  right: 10px;
  padding: 4px 10px;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.court-card-body {
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
}

.court-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.court-name {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: var(--ink);
}

.venue-name {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 12px;
  color: var(--muted);
}

.court-desc {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.court-specs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.spec-badge {
  border: 1px solid var(--outline);
  border-radius: 6px;
  padding: 3px 8px;
  color: var(--muted);
  font-size: 11px;
}

.court-stats-row {
  display: flex;
  gap: 8px;
  font-size: 11px;
  margin-top: auto;
}

.stat-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 8px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
  font-size: 11.5px;
  font-weight: 600;
  color: var(--ink);
}

:global(html.dark) .stat-badge {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.08);
}

.court-actions-footer {
  display: flex;
  gap: 6px;
  padding-top: 12px;
  border-top: 1px solid var(--outline);
  margin-top: auto;
}

.btn-court-action {
  flex: 1;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 0 8px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.btn-court-action:hover {
  border-color: var(--primary, #6b2d3f);
  color: var(--primary, #6b2d3f);
  background: var(--surface);
}

:global(html.dark) .btn-court-action {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.1);
  color: #ded7ce;
}

:global(html.dark) .btn-court-action:hover {
  border-color: var(--accent, #c9a15a);
  color: var(--accent, #c9a15a);
  background: rgba(201, 161, 90, 0.1);
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}

/* Skeleton Shimmer Styling */
.skeleton-card {
  pointer-events: none;
}

.skeleton-item {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
  border-radius: 6px;
}

.skeleton-item::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 209, 220, 0.45) 45%,
    rgba(255, 255, 255, 0.8) 55%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer-wave 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

:global(html.dark) .skeleton-item {
  background-color: var(--soft, #2b2e36);
}

:global(html.dark) .skeleton-item::after {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(120, 85, 94, 0.35) 45%,
    rgba(255, 255, 255, 0.1) 55%,
    rgba(255, 255, 255, 0) 100%
  );
}

.skeleton-img {
  border-radius: 0;
}

.skeleton-tag {
  position: absolute;
  top: 10px;
  left: 10px;
  width: 70px;
  height: 20px;
  border-radius: 6px;
}

.skeleton-title {
  width: 55%;
  height: 16px;
}

.skeleton-venue {
  width: 35%;
  height: 14px;
}

.skeleton-desc {
  width: 90%;
  height: 12px;
}

.skeleton-desc.short {
  width: 65%;
}

.skeleton-stat {
  width: 80px;
  height: 22px;
  border-radius: 6px;
}

.skeleton-btn {
  flex: 1;
  height: 28px;
  border-radius: 8px;
}

.skeleton-btn-toggle {
  flex: 0 0 34px;
  height: 28px;
  border-radius: 8px;
}

@keyframes shimmer-wave {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}
/* Pagination Styling */
.courts-pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 16px 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 14px;
  margin-top: 8px;
}

.pagination-info {
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-page {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-page:hover:not(:disabled) {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.btn-page:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-pills-list {
  display: flex;
  align-items: center;
  gap: 4px;
}

.page-pill-btn {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.page-pill-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.page-pill-btn.active {
  background: var(--pink-dark, #db2777);
  border-color: var(--pink-dark, #db2777);
  color: #fff;
}
</style>
