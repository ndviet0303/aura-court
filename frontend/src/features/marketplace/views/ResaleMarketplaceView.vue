<script setup lang="ts">
import { useResaleMarketplace } from '../composables/useResaleMarketplace'

import MarketplaceHero from '../components/MarketplaceHero.vue'
import MarketplaceFiltersBar from '../components/MarketplaceFiltersBar.vue'
import ResaleListingCard from '../components/ResaleListingCard.vue'
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
  isBuyModalOpen,
  selectedListingToBuy,
  buyPaymentMethod,
  isSellModalOpen,
  selectedSlotToSell,
  askingPriceInput,
  userSlots,
  filteredListings,
  openBuyModal,
  openSellModal,
  confirmBuy,
  confirmSell,
} = useResaleMarketplace()
</script>

<template>
  <div class="resale-marketplace-view">
    <!-- Hero Banner & Tab Switcher -->
    <MarketplaceHero
      :active-tab="activeTab"
      @update:active-tab="activeTab = $event"
    />

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Buy Tab Content -->
    <div v-if="activeTab === 'buy'" class="buy-tab-container">
      <!-- Search & Filters Bar -->
      <MarketplaceFiltersBar
        :search="searchQuery"
        :time-period="selectedTimePeriod"
        :venue="selectedVenue"
        @update:search="searchQuery = $event"
        @update:time-period="selectedTimePeriod = $event"
        @update:venue="selectedVenue = $event"
      />

      <!-- Listings Grid -->
      <div v-if="filteredListings.length === 0" class="empty-state-card">
        <span>🏸</span>
        <h3>Chưa có slot nào phù hợp</h3>
        <p>Thử điều chỉnh bộ lọc hoặc bật thông báo khi có người pass sân mới.</p>
      </div>

      <div v-else class="listings-grid">
        <ResaleListingCard
          v-for="l in filteredListings"
          :key="l.id"
          :listing="l"
          @buy="openBuyModal"
        />
      </div>
    </div>

    <!-- Sell Tab Content -->
    <div v-else class="sell-tab-container">
      <div class="sell-intro-card">
        <h3 class="sell-title">📦 Danh Sách Lịch Đã Đặt Có Thể Pass Lại</h3>
        <p class="sell-sub">Chọn một lịch bạn đã thanh toán nhưng bận không đi được để niêm yết bán lại.</p>

        <div class="user-slots-list">
          <div v-for="slot in userSlots" :key="slot.id" class="user-slot-row">
            <div class="slot-details">
              <strong>{{ slot.courtName }}</strong>
              <span>📍 {{ slot.venueName }} · 📅 {{ slot.dateStr }} ({{ slot.timeSlot }})</span>
            </div>
            <button class="btn-resell" @click="openSellModal(slot)">
              🏷️ Pass Sân Này
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <MarketplaceBuyModal
      :show="isBuyModalOpen"
      :listing="selectedListingToBuy"
      :payment-method="buyPaymentMethod"
      :processing="processingAction"
      @close="isBuyModalOpen = false"
      @update:payment-method="buyPaymentMethod = $event"
      @confirm="confirmBuy"
    />

    <MarketplaceSellModal
      :show="isSellModalOpen"
      :slot="selectedSlotToSell"
      :asking-price="askingPriceInput"
      :processing="processingAction"
      @close="isSellModalOpen = false"
      @update:asking-price="askingPriceInput = $event"
      @confirm="confirmSell"
    />
  </div>
</template>

<style scoped>
.resale-marketplace-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1300px;
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

.buy-tab-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.listings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 18px;
}

.empty-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
}

.empty-state-card span {
  font-size: 48px;
  margin-bottom: 12px;
}

.sell-tab-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.sell-intro-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 24px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.sell-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sell-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.user-slots-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 8px;
}

.user-slot-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  gap: 12px;
}

.slot-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}

.slot-details span {
  font-size: 11px;
  color: var(--muted);
}

.btn-resell {
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}
</style>
