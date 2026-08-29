<script setup lang="ts">
defineProps<{
  user: any
}>()
</script>

<template>
  <div class="profile-tab-card">
    <h3 class="tab-title">🏅 Hạng Hội Viên & Điểm Thưởng Aura</h3>

    <div class="membership-tier-card">
      <div class="tier-top">
        <span class="tier-badge">💎 GOLD ATHLETE</span>
        <span class="tier-points font-mono">1.250 Aura Points</span>
      </div>
      <p class="tier-desc">Ưu đãi giảm 10% khi đặt sân giờ cao điểm và miễn phí đồ uống điện tử.</p>
    </div>
  </div>
</template>

<style scoped>
.profile-tab-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.tab-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.membership-tier-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 18px;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(236, 72, 153, 0.1) 0%, rgba(219, 39, 119, 0.05) 100%);
  border: 1px solid rgba(236, 72, 153, 0.2);
}

.tier-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.tier-badge {
  font-size: 12px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
}

.tier-points {
  font-size: 14px;
  font-weight: 700;
}

.tier-desc {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}
</style>
