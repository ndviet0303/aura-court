<script setup lang="ts">
const specs = [
  { icon: '🏸', label: 'Mặt Thảm', val: 'Taraflex BWF Approved' },
  { icon: '📏', label: 'Chiều Cao Trần', val: '9.5 mét tiêu chuẩn' },
  { icon: '💡', label: 'Hệ Thống Đèn', val: 'LED 500 Lux chống chói' },
  { icon: '❄️', label: 'Không Khí', val: 'Điều hòa & Thông gió liên tục' },
]
</script>

<template>
  <div class="court-specs-section">
    <h3 class="section-title">📐 Thông Số Kỹ Thuật Sân Đấu</h3>
    <div class="specs-grid">
      <div v-for="s in specs" :key="s.label" class="spec-card">
        <span class="s-icon">{{ s.icon }}</span>
        <span class="s-lbl">{{ s.label }}</span>
        <strong class="s-val">{{ s.val }}</strong>
      </div>
    </div>
  </div>
</template>

<style scoped>
.court-specs-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.specs-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.spec-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.s-icon {
  font-size: 20px;
}

.s-lbl {
  font-size: 11px;
  color: var(--muted);
}

.s-val {
  font-size: 13px;
}

@media (max-width: 768px) {
  .specs-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
