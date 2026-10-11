<script setup lang="ts">
defineProps<{
  totalBalance: number
  totalPoints: number
  walletsCount: number
}>()

const emit = defineEmits<{
  (e: 'deposit'): void
  (e: 'qr'): void
  (e: 'scrollHistory'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="total-balance-card">
    <div class="card-main-content">
      <div class="balance-left">
        <div class="pass-badge">
          <span class="material-symbols-outlined badge-icon">workspace_premium</span>
          <span class="badge-text font-mono">AURA ATHLETE MULTI-PASS</span>
          <span class="status-indicator">
            <span class="status-dot"></span>
            <span>{{ $t('wallet.activeCard') }}</span>
          </span>
        </div>

        <div class="balance-meta">
          <span class="balance-label">{{ $t('wallet.totalAvailable') }}</span>
          <div class="balance-amount-row">
            <span class="balance-val font-mono">{{ formatMoney(totalBalance) }}</span>
          </div>
        </div>

        <div class="sub-stats-row">
          <div class="stat-pill">
            <span class="material-symbols-outlined pill-icon">account_balance</span>
            <span>{{ $t('wallet.linkedVenues', { count: walletsCount }) }}</span>
          </div>
          <div class="stat-pill highlight-gold">
            <span class="material-symbols-outlined pill-icon">stars</span>
            <span>{{
              $t('wallet.accumulatedPoints', { points: totalPoints.toLocaleString() })
            }}</span>
          </div>
        </div>
      </div>

      <div class="balance-actions">
        <button class="btn btn-primary btn-cta" @click="emit('deposit')">
          <span class="material-symbols-outlined">add_card</span>
          <span>{{ $t('wallet.depositBtn') }}</span>
        </button>

        <button class="btn btn-outline" @click="emit('qr')">
          <span class="material-symbols-outlined">qr_code_2</span>
          <span>{{ $t('wallet.modalQrTitle') }}</span>
        </button>
      </div>
    </div>

    <!-- Luxury Club Perks Strip -->
    <div class="perks-strip">
      <div class="perk-item">
        <span class="material-symbols-outlined perk-icon">bolt</span>
        <div class="perk-info">
          <strong>Nạp tiền tức thì 24/7</strong>
          <span>Tự động cộng số dư qua VietQR Napas247</span>
        </div>
      </div>

      <div class="perk-item">
        <span class="material-symbols-outlined perk-icon">loyalty</span>
        <div class="perk-info">
          <strong>Tích lũy điểm hội viên</strong>
          <span>Nhận Aura Points đổi nước & phụ kiện</span>
        </div>
      </div>

      <div class="perk-item">
        <span class="material-symbols-outlined perk-icon">verified_user</span>
        <div class="perk-info">
          <strong>Bảo mật chuẩn quốc tế</strong>
          <span>Hoàn tiền 100% khi huỷ lịch đúng hạn</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.total-balance-card {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  overflow: hidden;
  box-shadow: var(--shadow-sm, 0 4px 20px rgba(107, 45, 63, 0.04));
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.total-balance-card:hover {
  border-color: var(--outline);
  box-shadow: var(--shadow-md, 0 8px 28px rgba(107, 45, 63, 0.08));
}

.card-main-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 24px;
  padding: 28px 32px;
}

.balance-left {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pass-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 4px 12px;
  border-radius: 999px;
  background: var(--primary-container, rgba(107, 45, 63, 0.08));
  color: var(--primary, #6b2d3f);
  font-size: 11.5px;
  font-weight: 700;
  width: fit-content;
  border: 1px solid rgba(107, 45, 63, 0.15);
}

:global(html.dark) .pass-badge {
  background: rgba(229, 142, 159, 0.12);
  color: var(--primary, #e58e9f);
  border-color: rgba(229, 142, 159, 0.25);
}

.badge-icon {
  font-size: 16px;
}

.status-indicator {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding-left: 8px;
  border-left: 1px solid rgba(107, 45, 63, 0.2);
  font-size: 11px;
  font-weight: 600;
  color: #10b981;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 8px #10b981;
}

.balance-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.balance-label {
  font-size: 13px;
  color: var(--muted);
  font-weight: 500;
}

.balance-amount-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.balance-val {
  font-size: 34px;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--primary, #6b2d3f);
  line-height: 1.15;
}

:global(html.dark) .balance-val {
  color: var(--primary, #e58e9f);
}

.sub-stats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 4px;
}

.stat-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 8px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border);
  font-size: 12px;
  color: var(--ink);
}

:global(html.dark) .stat-pill {
  background: var(--surface-subtle, #262024);
}

.stat-pill.highlight-gold {
  background: var(--accent-container, rgba(201, 161, 90, 0.1));
  border-color: rgba(201, 161, 90, 0.3);
  color: var(--on-accent-container, #684f17);
}

:global(html.dark) .stat-pill.highlight-gold {
  color: var(--accent, #d8b46e);
  background: rgba(216, 180, 110, 0.12);
}

.pill-icon {
  font-size: 15px;
  color: var(--accent, #c9a15a);
}

.balance-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 220px;
}

.btn-cta {
  font-size: 14px;
  padding: 12px 24px;
  box-shadow: 0 4px 16px rgba(107, 45, 63, 0.2);
}

.perks-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  padding: 18px 32px;
  background: var(--surface-subtle, #f7f3ed);
  border-top: 1px solid var(--border);
}

:global(html.dark) .perks-strip {
  background: rgba(30, 25, 28, 0.6);
}

.perk-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.perk-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--surface);
  color: var(--accent, #c9a15a);
  border: 1px solid var(--border);
  font-size: 18px;
  flex-shrink: 0;
}

.perk-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.perk-info strong {
  font-size: 12.5px;
  color: var(--ink);
  font-weight: 700;
}

.perk-info span {
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.3;
}

@media (max-width: 860px) {
  .card-main-content {
    padding: 20px 24px;
  }
  .balance-actions {
    width: 100%;
  }
  .perks-strip {
    grid-template-columns: 1fr;
    padding: 16px 24px;
    gap: 12px;
  }
}
</style>
