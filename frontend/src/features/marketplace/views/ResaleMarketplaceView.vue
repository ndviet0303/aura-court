<script setup lang="ts">
import { useResaleMarketplace } from '../composables/useResaleMarketplace'
import { SkeletonLoader, AuraButton } from '@/shared/components'
import { formatCurrency } from '@/i18n'

import MarketplaceHero from '../components/MarketplaceHero.vue'
import MarketplaceFiltersBar from '../components/MarketplaceFiltersBar.vue'
import ResaleListingCard from '../components/ResaleListingCard.vue'
import MarketplaceMyListings from '../components/MarketplaceMyListings.vue'
import MarketplaceBuyModal from '../components/MarketplaceBuyModal.vue'
import MarketplaceSellModal from '../components/MarketplaceSellModal.vue'

const {
  activeTab,
  loading,
  processingAction,
  notice,
  error,
  selectedTimePeriod,
  selectedVenue,
  searchQuery,
  sortBy,
  availableVenues,
  selectedListingToBuy,
  isSellModalOpen,
  selectedSlotToSell,
  askingPriceInput,
  listings,
  myListings,
  userSlots,
  filteredListings,
  refreshAll,
  loadMyListings,
  resetFilters,
  openBuyModal,
  closeBuyModal,
  openSellModal,
  confirmSell,
  cancelListing,
} = useResaleMarketplace()
</script>

<template>
  <main class="resale-marketplace-view">
    <!-- Hero Banner & Tab Switcher -->
    <MarketplaceHero
      :active-tab="activeTab"
      :active-listings-count="listings.length"
      :my-listings-count="myListings.length"
      :eligible-slots-count="userSlots.filter((s) => s.eligible).length"
      :loading="loading"
      @update:active-tab="activeTab = $event"
      @refresh="refreshAll"
    />

    <!-- Feedback Notice Toasts -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill-success">
        <span class="material-symbols-outlined alert-icon">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="alert-pill-error">
        <span class="material-symbols-outlined alert-icon">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Buy Tab Content: Marketplace Grid -->
    <section v-if="activeTab === 'buy'" class="tab-pane">
      <MarketplaceFiltersBar
        :search="searchQuery"
        :time-period="selectedTimePeriod"
        :venue="selectedVenue"
        :sort-by="sortBy"
        :venues-list="availableVenues"
        @update:search="searchQuery = $event"
        @update:time-period="selectedTimePeriod = $event"
        @update:venue="selectedVenue = $event"
        @update:sort-by="sortBy = $event"
        @reset="resetFilters"
      />

      <!-- Skeleton Loading State -->
      <div v-if="loading" class="listings-grid">
        <div v-for="i in 6" :key="i" class="resale-card-skeleton">
          <div class="skeleton-header">
            <SkeletonLoader type="rect" width="130px" height="14px" rounded="4px" />
            <SkeletonLoader type="rect" width="76px" height="24px" rounded="999px" />
          </div>

          <SkeletonLoader type="title" width="65%" height="20px" />

          <div class="skeleton-schedule-box">
            <div class="skeleton-meta-row">
              <SkeletonLoader type="rect" width="45%" height="28px" rounded="6px" />
              <SkeletonLoader type="rect" width="45%" height="28px" rounded="6px" />
            </div>
            <div class="skeleton-seller-row">
              <SkeletonLoader type="rect" width="120px" height="16px" rounded="4px" />
              <SkeletonLoader type="rect" width="85px" height="16px" rounded="4px" />
            </div>
          </div>

          <SkeletonLoader type="rect" width="100%" height="28px" rounded="6px" />

          <div class="skeleton-footer">
            <div class="skeleton-price-block">
              <SkeletonLoader type="rect" width="60px" height="12px" rounded="4px" />
              <SkeletonLoader type="rect" width="100px" height="22px" rounded="4px" />
            </div>
            <SkeletonLoader type="rect" width="94px" height="34px" rounded="10px" />
          </div>
        </div>
      </div>

      <!-- Listings Grid / Empty State -->
      <div v-else-if="filteredListings.length === 0" class="empty-state-card">
        <span class="material-symbols-outlined empty-icon">sports_tennis</span>
        <h3>{{ $t('resale.emptyListingsTitle') }}</h3>
        <p>{{ $t('resale.emptyListingsDesc') }}</p>
        <AuraButton
          variant="outline"
          size="sm"
          icon="filter_alt_off"
          class="btn-reset-empty"
          @click="resetFilters"
        >
          {{ $t('resale.resetAndShowAll') }}
        </AuraButton>
      </div>

      <div v-else class="listings-grid">
        <ResaleListingCard
          v-for="item in filteredListings"
          :key="item.id"
          :listing="item"
          @buy="openBuyModal"
        />
      </div>
    </section>

    <!-- Sell Tab Content: User's Eligible Slots -->
    <section v-else-if="activeTab === 'sell'" class="tab-pane">
      <div class="sell-intro-card">
        <div class="sell-intro-head">
          <div class="sell-head-meta">
            <h2 class="sell-title">{{ $t('resale.sellIntroTitle') }}</h2>
            <p class="sell-sub">
              {{ $t('resale.sellIntroSub') }}
            </p>
          </div>
        </div>

        <!-- Skeleton Loading for Sell tab -->
        <div v-if="loading" class="user-slots-grid">
          <div v-for="i in 3" :key="i" class="user-slot-card skeleton-user-slot">
            <SkeletonLoader type="rect" width="100%" height="110px" rounded="12px" />
          </div>
        </div>

        <!-- Empty user slots -->
        <div v-else-if="userSlots.length === 0" class="empty-slots-box">
          <span class="material-symbols-outlined empty-icon">event_busy</span>
          <p>{{ $t('resale.emptyUserSlots') }}</p>
          <AuraButton to="/booking" variant="primary" size="md" icon="add_circle">
            {{ $t('resale.bookNowBtn') }}
          </AuraButton>
        </div>

        <div v-else class="user-slots-grid">
          <div
            v-for="slot in userSlots"
            :key="slot.id"
            class="user-slot-card"
            :class="{ disabled: !slot.eligible }"
          >
            <div class="slot-card-body">
              <div class="slot-card-head">
                <strong class="slot-court-name">{{ slot.courtName }}</strong>
                <span class="slot-paid-badge font-mono">{{
                  formatCurrency(slot.maxResalePrice)
                }}</span>
              </div>

              <div class="slot-card-meta">
                <span class="meta-item">
                  <span class="material-symbols-outlined icon-14">location_on</span>
                  <span>{{ slot.venueName }}</span>
                </span>
                <span class="meta-item">
                  <span class="material-symbols-outlined icon-14">calendar_today</span>
                  <span>{{ slot.dateStr }}</span>
                </span>
                <span class="meta-item highlight">
                  <span class="material-symbols-outlined icon-14">schedule</span>
                  <span class="font-mono">{{ slot.timeSlot }}</span>
                </span>
              </div>

              <p v-if="slot.ineligibleReason" class="ineligible-text">
                <span class="material-symbols-outlined icon-14">warning</span>
                <span>{{ slot.ineligibleReason }}</span>
              </p>
            </div>

            <div class="slot-card-action">
              <AuraButton
                variant="gold"
                size="sm"
                icon="sell"
                block
                :disabled="!slot.eligible"
                @click="openSellModal(slot)"
              >
                {{ $t('resale.passSlotBtn') }}
              </AuraButton>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- My Listings Tab Content -->
    <section v-else-if="activeTab === 'my-listings'" class="tab-pane">
      <MarketplaceMyListings
        :listings="myListings"
        :processing="processingAction"
        @withdraw="cancelListing"
        @create-new="activeTab = 'sell'"
        @refresh="loadMyListings"
      />
    </section>

    <!-- Modals -->
    <MarketplaceBuyModal
      :listing="selectedListingToBuy"
      @close="closeBuyModal"
      @changed="refreshAll"
    />

    <MarketplaceSellModal
      :show="isSellModalOpen"
      :booking-slot="selectedSlotToSell"
      :asking-price="askingPriceInput"
      :processing="processingAction"
      @close="isSellModalOpen = false"
      @update:asking-price="askingPriceInput = $event"
      @confirm="confirmSell"
    />
  </main>
</template>

<style scoped>
.resale-marketplace-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 28px 20px 60px;
  color: var(--ink);
}

.tab-pane {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.listings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 18px;
}

/* Skeleton Card Style */
.resale-card-skeleton {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  box-shadow: var(--shadow-sm);
}

.skeleton-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.skeleton-schedule-box {
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  border-radius: 14px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.skeleton-meta-row {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.skeleton-seller-row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding-top: 8px;
  border-top: 1px dashed var(--outline);
}

.skeleton-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: auto;
  padding-top: 12px;
}

.skeleton-price-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.alert-pill-success {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 14px;
  background: var(--success-bg, #e8f5e9);
  color: var(--success-text, #2e7d32);
  border: 1px solid rgba(46, 125, 50, 0.2);
  font-size: 13px;
  font-weight: 700;
}

.alert-pill-error {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 14px;
  background: var(--error-bg, #ffebee);
  color: var(--error-text, #c62828);
  border: 1px solid rgba(198, 40, 40, 0.2);
  font-size: 13px;
  font-weight: 700;
}

.alert-icon {
  font-size: 18px;
}

.empty-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 60px 24px;
  border-radius: 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
  gap: 14px;
}

.empty-icon {
  font-size: 52px;
  color: var(--muted);
}

.empty-state-card h3 {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
}

.empty-state-card p {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  max-width: 440px;
}

.btn-reset-empty {
  margin-top: 4px;
}

.sell-intro-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
  box-shadow: var(--shadow-sm);
}

.sell-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.sell-sub {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.empty-slots-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 20px;
  text-align: center;
  gap: 14px;
  color: var(--muted);
}

.user-slots-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 14px;
}

.user-slot-card {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 14px;
  padding: 16px;
  border-radius: 16px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  transition: all 0.2s ease;
}

.user-slot-card:hover:not(.disabled) {
  border-color: var(--accent, #c9a15a);
  background: var(--surface);
  box-shadow: var(--shadow-sm);
}

.user-slot-card.disabled {
  opacity: 0.6;
}

.slot-card-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.slot-court-name {
  font-size: 15px;
  color: var(--ink);
}

.slot-paid-badge {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--muted);
}

.slot-card-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 8px;
  font-size: 12px;
  color: var(--muted);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.meta-item.highlight {
  color: var(--primary, #6b2d3f);
  font-weight: 700;
}

:global(html.dark) .meta-item.highlight {
  color: var(--accent, #c9a15a);
}

.icon-14 {
  font-size: 14px;
  color: var(--accent, #c9a15a);
}

.ineligible-text {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin: 6px 0 0;
  font-size: 11px;
  color: #ef4444;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
