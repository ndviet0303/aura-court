<script setup lang="ts">
import { onMounted } from 'vue'
import { useCreateMatch } from '../composables/useCreateMatch'

import SportCategorySelector from '../components/match/SportCategorySelector.vue'
import MatchLocationTimeForm from '../components/match/MatchLocationTimeForm.vue'
import MatchSettingsForm from '../components/match/MatchSettingsForm.vue'
import MatchInviteNetworkCard from '../components/match/MatchInviteNetworkCard.vue'

const {
  loading,
  submitting,
  notice,
  error,
  courts,
  selectedSport,
  selectedVenueName,
  selectedCourtId,
  matchDate,
  matchTime,
  duration,
  matchFormat,
  skillLevel,
  splitCost,
  costPerPerson,
  maxPlayers,
  quickVenues,
  friends,
  loadCourts,
  toggleFriend,
  submitMatch,
} = useCreateMatch()

onMounted(() => {
  loadCourts()
})
</script>

<template>
  <div class="create-match-view-page">
    <!-- Header Hero -->
    <header class="match-hero">
      <div class="hero-titles">
        <span class="pill-badge font-mono">INITIATE PLAY</span>
        <h1 class="page-title">Khởi Tạo Kèo Mới & Ghép Trận</h1>
        <p class="page-sub">Tìm kiếm đồng đội, mở kèo giao lưu câu lạc bộ và chia sẻ chi phí sân tự động.</p>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <form class="create-match-form" @submit.prevent="submitMatch">
      <!-- Sport Selector -->
      <SportCategorySelector
        :selected-sport="selectedSport"
        @update:selected-sport="selectedSport = $event"
      />

      <!-- Location & Time -->
      <MatchLocationTimeForm
        :courts="courts"
        :selected-venue-name="selectedVenueName"
        :selected-court-id="selectedCourtId"
        :match-date="matchDate"
        :match-time="matchTime"
        :duration="duration"
        :quick-venues="quickVenues"
        @update:selected-venue-name="selectedVenueName = $event"
        @update:selected-court-id="selectedCourtId = $event"
        @update:match-date="matchDate = $event"
        @update:match-time="matchTime = $event"
        @update:duration="duration = $event"
      />

      <!-- Match Parameters -->
      <MatchSettingsForm
        :format="matchFormat"
        :skill-level="skillLevel"
        :split-cost="splitCost"
        :cost-per-person="costPerPerson"
        :max-players="maxPlayers"
        @update:format="matchFormat = $event"
        @update:skill-level="skillLevel = $event"
        @update:split-cost="splitCost = $event"
        @update:cost-per-person="costPerPerson = $event"
        @update:max-players="maxPlayers = $event"
      />

      <!-- Friend Network Invites -->
      <MatchInviteNetworkCard
        :friends="friends"
        @toggle="toggleFriend"
      />

      <!-- Submit CTA -->
      <div class="form-submit-row">
        <button type="submit" class="btn-create-submit" :disabled="submitting">
          <span v-if="submitting">Đang khởi tạo trận đấu...</span>
          <span v-else>🚀 Mở Kèo & Xuất Bản Lên Cộng Đồng</span>
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.create-match-view-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 900px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.match-hero {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.pill-badge {
  font-size: 11px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
  width: fit-content;
}

.page-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
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

.create-match-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-submit-row {
  display: flex;
  justify-content: center;
}

.btn-create-submit {
  width: 100%;
  padding: 14px;
  border-radius: 12px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  font-size: 15px;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(219, 39, 119, 0.25);
}
</style>
