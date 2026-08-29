<script setup lang="ts">
defineProps<{
  totalCount: number
}>()
</script>

<template>
  <div class="courts-hero-header">
    <div class="hero-titles">
      <span class="badge font-mono">🏸 AURA BADMINTON VENUES</span>
      <h1 class="page-title">Khám Phá Cụm Sân & Sân Đấu Chuyên Nghiệp</h1>
      <p class="page-sub">Hơn {{ totalCount }} sân thi đấu chuẩn BWF với thảm Taraflex, đèn chống chói và điều hòa nhiệt độ.</p>
    </div>
  </div>
</template>

<style scoped>
.courts-hero-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.hero-titles {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.badge {
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
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}
</style>
