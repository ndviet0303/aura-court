<script setup lang="ts">
import type { VenueWallet } from '../composables/useWallet'

defineProps<{
  wallets: VenueWallet[]
}>()

const emit = defineEmits<{
  (e: 'deposit', wallet?: VenueWallet): void
  (e: 'qr', wallet: VenueWallet): void
  (e: 'addPass'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="venue-wallets-section">
    <div class="section-hdr">
      <div class="hdr-text">
        <h3 class="section-title">
          <span class="material-symbols-outlined hdr-icon">account_balance</span>
          <span>{{ $t('wallet.venueWalletsTitle') }}</span>
        </h3>
        <p class="section-sub">
          {{ $t('wallet.venueWalletsSubtitle') }}
        </p>
      </div>

      <div class="hdr-count">
        <span class="count-tag font-mono">{{
          $t('wallet.availableCards', { count: wallets.length })
        }}</span>
      </div>
    </div>

    <div class="wallets-grid">
      <div
        v-for="w in wallets"
        :key="w.id"
        class="club-pass-card"
        :class="`theme-${w.accent_theme || 'burgundy'}`"
      >
        <!-- Card Top Bar: Chip & Contactless -->
        <div class="card-top-bar">
          <div class="emv-chip-box">
            <span class="chip-line"></span>
            <span class="chip-line horizontal"></span>
          </div>
          <div class="card-badges">
            <span class="tier-pill font-mono">{{ w.tier_label.toUpperCase() }}</span>
            <span class="material-symbols-outlined contactless-icon">contactless</span>
          </div>
        </div>

        <!-- Venue & Member Info -->
        <div class="card-body-info">
          <h4 class="club-title">{{ w.venue_name }}</h4>
          <p class="club-address">
            <span class="material-symbols-outlined pin-icon">location_on</span>
            <span>{{ w.address }}</span>
          </p>
          <div class="card-number-display font-mono">
            {{ w.card_number }}
          </div>
        </div>

        <!-- Card Balance & Points Footer -->
        <div class="card-balance-row">
          <div class="bal-box">
            <span class="bal-label">{{ $t('wallet.availableBalance') }}</span>
            <strong class="bal-value font-mono">{{ formatMoney(w.balance) }}</strong>
          </div>
          <div class="pts-box">
            <span class="pts-label">{{ $t('wallet.auraPoints') }}</span>
            <span class="pts-value font-mono">{{ (w.points || 0).toLocaleString() }} pts</span>
          </div>
        </div>

        <!-- Card Action Buttons -->
        <div class="card-actions-row">
          <button class="pass-btn pass-btn-primary" @click="emit('deposit', w)">
            <span class="material-symbols-outlined">add_card</span>
            <span>{{ $t('wallet.topupCardBtn') }}</span>
          </button>
          <button class="pass-btn pass-btn-ghost" @click="emit('qr', w)">
            <span class="material-symbols-outlined">qr_code_2</span>
            <span>{{ $t('wallet.payQrBtn') }}</span>
          </button>
        </div>
      </div>

      <!-- Add New Club Card / Activate Pass -->
      <div class="add-pass-card" @click="emit('deposit', wallets[0])">
        <div class="add-pass-inner">
          <div class="add-icon-circle">
            <span class="material-symbols-outlined">add</span>
          </div>
          <strong class="add-title">Liên Kết Cụm Sân Mới</strong>
          <p class="add-desc">
            Kích hoạt ví trả trước hoặc nhập mã thẻ hội viên tại cơ sở khác thuộc chuỗi Aura.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.venue-wallets-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-hdr {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 12px;
}

.hdr-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.section-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ink);
}

.hdr-icon {
  color: var(--accent, #c9a15a);
  font-size: 22px;
}

.section-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.count-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border);
  color: var(--muted);
}

:global(html.dark) .count-tag {
  background: var(--surface-subtle, #262024);
}

.wallets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 20px;
}

/* Luxury Club Pass Cards */
.club-pass-card {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 230px;
  padding: 24px;
  border-radius: 20px;
  color: #ffffff;
  overflow: hidden;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
  transition:
    transform 0.25s ease,
    box-shadow 0.25s ease;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.club-pass-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.2);
}

/* Theme Variations */
.theme-burgundy {
  background: linear-gradient(135deg, #562332 0%, #30111a 60%, #1e0910 100%);
  border-color: rgba(201, 161, 90, 0.3);
}

.theme-gold {
  background: linear-gradient(135deg, #443725 0%, #261f14 60%, #17120a 100%);
  border-color: rgba(216, 180, 110, 0.35);
}

.theme-navy {
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 60%, #080d1a 100%);
  border-color: rgba(148, 163, 184, 0.25);
}

.theme-emerald {
  background: linear-gradient(135deg, #134e4a 0%, #042f2e 60%, #021a1a 100%);
  border-color: rgba(45, 212, 191, 0.3);
}

/* Card Top Bar */
.card-top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.emv-chip-box {
  position: relative;
  width: 38px;
  height: 28px;
  border-radius: 6px;
  background: linear-gradient(135deg, #e6c887 0%, #b88e45 100%);
  box-shadow: inset 0 0 4px rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.4);
}

.chip-line {
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  height: 1px;
  background: rgba(0, 0, 0, 0.25);
}

.chip-line.horizontal {
  top: 0;
  bottom: 0;
  left: 50%;
  right: auto;
  width: 1px;
  height: auto;
}

.card-badges {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tier-pill {
  font-size: 10px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 4px;
  background: rgba(201, 161, 90, 0.25);
  color: #f7e0b5;
  border: 1px solid rgba(201, 161, 90, 0.4);
  letter-spacing: 0.05em;
}

.contactless-icon {
  font-size: 20px;
  color: rgba(255, 255, 255, 0.7);
}

/* Card Body */
.card-body-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 14px;
}

.club-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #ffffff;
}

.club-address {
  margin: 0;
  font-size: 11.5px;
  color: rgba(255, 255, 255, 0.75);
  display: flex;
  align-items: center;
  gap: 4px;
}

.pin-icon {
  font-size: 13px;
  color: #c9a15a;
}

.card-number-display {
  margin-top: 6px;
  font-size: 12px;
  letter-spacing: 0.15em;
  color: rgba(255, 255, 255, 0.6);
}

/* Card Balance Row */
.card-balance-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.bal-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.bal-label {
  font-size: 10.5px;
  color: rgba(255, 255, 255, 0.65);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.bal-value {
  font-size: 22px;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.02em;
}

.pts-box {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.pts-label {
  font-size: 10px;
  color: rgba(255, 255, 255, 0.65);
}

.pts-value {
  font-size: 13px;
  font-weight: 700;
  color: #f7e0b5;
}

/* Card Action Buttons */
.card-actions-row {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}

.pass-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
  border: 0;
}

.pass-btn .material-symbols-outlined {
  font-size: 15px;
}

.pass-btn-primary {
  background: var(--accent, #c9a15a);
  color: #1c1202;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.pass-btn-primary:hover {
  background: #deb96c;
  transform: translateY(-1px);
}

.pass-btn-ghost {
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.25);
  backdrop-filter: blur(4px);
}

.pass-btn-ghost:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: translateY(-1px);
}

/* Add Pass Card */
.add-pass-card {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 230px;
  padding: 24px;
  border-radius: 20px;
  border: 2px dashed var(--border);
  background: var(--surface-subtle, #f7f3ed);
  cursor: pointer;
  transition: all 0.2s ease;
}

:global(html.dark) .add-pass-card {
  background: var(--surface-subtle, #262024);
  border-color: var(--outline);
}

.add-pass-card:hover {
  border-color: var(--accent, #c9a15a);
  background: var(--surface);
  transform: translateY(-2px);
}

.add-pass-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
}

.add-icon-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--surface);
  color: var(--accent, #c9a15a);
  border: 1px solid var(--border);
  font-size: 20px;
  box-shadow: var(--shadow-sm);
}

.add-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.add-desc {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  max-width: 240px;
  line-height: 1.4;
}
</style>
