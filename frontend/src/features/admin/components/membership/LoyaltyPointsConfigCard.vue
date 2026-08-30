<script setup lang="ts">
import { ref, computed } from 'vue'
import type { LoyaltyConversionConfig, MultiplierRule } from '../../types/membership'

const props = defineProps<{
  config: LoyaltyConversionConfig
  rules: MultiplierRule[]
}>()

const emit = defineEmits<{
  (e: 'open-create-rule'): void
  (e: 'edit-rule', rule: MultiplierRule): void
  (e: 'toggle-rule', ruleId: string): void
  (e: 'delete-rule', ruleId: string): void
  (e: 'update-rates', payload: Partial<LoyaltyConversionConfig>): void
}>()

// Rate Edit State
const isEditingRates = ref(false)
const editSpend = ref(props.config.spendAmountVnd)
const editSpendPts = ref(props.config.pointsEarned)
const editRedeemPts = ref(props.config.pointsRedeemed)
const editRedeemVnd = ref(props.config.rewardValueVnd)

function startEditRates() {
  editSpend.value = props.config.spendAmountVnd
  editSpendPts.value = props.config.pointsEarned
  editRedeemPts.value = props.config.pointsRedeemed
  editRedeemVnd.value = props.config.rewardValueVnd
  isEditingRates.value = true
}

function saveRates() {
  emit('update-rates', {
    spendAmountVnd: Number(editSpend.value),
    pointsEarned: Number(editSpendPts.value),
    pointsRedeemed: Number(editRedeemPts.value),
    rewardValueVnd: Number(editRedeemVnd.value),
  })
  isEditingRates.value = false
}

// Points Simulator
const simAmount = ref(500000)
const simTierMultiplier = ref(2.0)
const simRuleMultiplier = ref(1.5)

const calculatedPoints = computed(() => {
  const baseRate = props.config.pointsEarned / (props.config.spendAmountVnd || 1000)
  const basePts = simAmount.value * baseRate
  return Math.round(basePts * simTierMultiplier.value * simRuleMultiplier.value)
})

const calculatedDiscountValue = computed(() => {
  const rate = props.config.rewardValueVnd / (props.config.pointsRedeemed || 100)
  return Math.round(calculatedPoints.value * rate)
})

function formatVnd(val: number) {
  return new Intl.NumberFormat('vi-VN').format(val) + 'đ'
}
</script>

<template>
  <section class="loyalty-config-section">
    <div class="section-header">
      <div class="title-with-icon">
        <span class="icon-heart-badge material-symbols-outlined">settings_heart</span>
        <h2 class="section-title">Cấu hình Tích Điểm & Đổi Thưởng</h2>
      </div>
    </div>

    <div class="config-card">
      <div class="config-grid">
        <!-- Left Col: Tỷ lệ quy đổi -->
        <div class="conversion-column">
          <div class="col-header-row">
            <h3 class="col-title">TỶ LỆ QUY ĐỔI</h3>
            <button
              v-if="!isEditingRates"
              type="button"
              class="btn-edit-rates"
              @click="startEditRates"
            >
              <span class="material-symbols-outlined icon-16">edit</span>
              <span>Chỉnh sửa</span>
            </button>
          </div>

          <!-- View Mode -->
          <div v-if="!isEditingRates" class="rates-display-stack">
            <div class="conversion-box">
              <div class="box-left">
                <span class="material-symbols-outlined box-icon">account_balance_wallet</span>
                <span class="box-label">Tiêu dùng</span>
              </div>
              <div class="box-right">
                <span class="val-vnd">{{ formatVnd(config.spendAmountVnd) }}</span>
                <span class="material-symbols-outlined arrow-icon">arrow_forward</span>
                <span class="val-pts">
                  {{ config.pointsEarned }} <span class="pts-unit">điểm</span>
                </span>
              </div>
            </div>

            <div class="conversion-box">
              <div class="box-left">
                <span class="material-symbols-outlined box-icon">redeem</span>
                <span class="box-label">Đổi thưởng</span>
              </div>
              <div class="box-right">
                <span class="val-pts">
                  {{ config.pointsRedeemed }} <span class="pts-unit">điểm</span>
                </span>
                <span class="material-symbols-outlined arrow-icon">arrow_forward</span>
                <span class="val-vnd">{{ formatVnd(config.rewardValueVnd) }}</span>
              </div>
            </div>
          </div>

          <!-- Edit Mode -->
          <div v-else class="rates-edit-form">
            <div class="edit-rate-group">
              <label class="edit-label">Tiêu dùng tích điểm</label>
              <div class="edit-inputs-row">
                <input v-model.number="editSpend" type="number" step="1000" class="rate-input" />
                <span class="unit-text">VNĐ =</span>
                <input v-model.number="editSpendPts" type="number" step="1" class="rate-input-sm" />
                <span class="unit-text">Điểm</span>
              </div>
            </div>

            <div class="edit-rate-group">
              <label class="edit-label">Đổi thưởng voucher</label>
              <div class="edit-inputs-row">
                <input v-model.number="editRedeemPts" type="number" step="10" class="rate-input-sm" />
                <span class="unit-text">Điểm =</span>
                <input v-model.number="editRedeemVnd" type="number" step="1000" class="rate-input" />
                <span class="unit-text">VNĐ</span>
              </div>
            </div>

            <div class="edit-actions-row">
              <button type="button" class="btn-cancel" @click="isEditingRates = false">Hủy</button>
              <button type="button" class="btn-save" @click="saveRates">Lưu tỷ lệ</button>
            </div>
          </div>

          <!-- Points Simulator -->
          <div class="simulator-panel">
            <div class="sim-header">
              <span class="material-symbols-outlined icon-18 text-primary">calculate</span>
              <span class="sim-title">Giả lập tính điểm thực tế</span>
            </div>
            <div class="sim-inputs">
              <div class="sim-field">
                <span class="sim-field-label">Chi tiêu giả định:</span>
                <input v-model.number="simAmount" type="number" step="50000" class="sim-input" />
              </div>
              <div class="sim-result-row">
                <div class="sim-res-item">
                  <span class="sim-res-label">Điểm nhận được:</span>
                  <strong class="sim-res-pts">+{{ calculatedPoints.toLocaleString() }} pts</strong>
                </div>
                <div class="sim-res-item">
                  <span class="sim-res-label">Quy đổi voucher:</span>
                  <strong class="sim-res-val">{{ formatVnd(calculatedDiscountValue) }}</strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Col: Hệ số nhân (Multiplier) -->
        <div class="multiplier-column">
          <div class="col-header-row">
            <h3 class="col-title">HỆ SỐ NHÂN (MULTIPLIER)</h3>
            <span class="rules-count">{{ rules.length }} quy tắc</span>
          </div>

          <div class="multiplier-rules-list">
            <div
              v-for="rule in rules"
              :key="rule.id"
              class="rule-row-item"
              :class="{ 'is-disabled': !rule.isActive }"
            >
              <div class="rule-info-left">
                <div
                  class="status-dot"
                  :class="{ 'dot-active': rule.isActive, 'dot-inactive': !rule.isActive }"
                ></div>
                <div class="rule-text-block">
                  <div class="rule-name-row">
                    <span class="rule-name">{{ rule.name }}</span>
                    <span v-if="!rule.isActive" class="rule-paused-tag">Tạm dừng</span>
                  </div>
                  <span class="rule-schedule">{{ rule.description }}</span>
                </div>
              </div>

              <div class="rule-actions-right">
                <span class="multiplier-badge">x{{ rule.multiplier }} Điểm</span>
                <button
                  type="button"
                  class="btn-toggle-rule"
                  :title="rule.isActive ? 'Tạm dừng quy tắc' : 'Kích hoạt quy tắc'"
                  @click="emit('toggle-rule', rule.id)"
                >
                  <span class="material-symbols-outlined icon-18">
                    {{ rule.isActive ? 'pause_circle' : 'play_circle' }}
                  </span>
                </button>
                <button
                  type="button"
                  class="btn-edit-rule"
                  title="Chỉnh sửa quy tắc"
                  @click="emit('edit-rule', rule)"
                >
                  <span class="material-symbols-outlined icon-18">edit</span>
                </button>
                <button
                  type="button"
                  class="btn-delete-rule"
                  title="Xóa quy tắc"
                  @click="emit('delete-rule', rule.id)"
                >
                  <span class="material-symbols-outlined icon-18">delete</span>
                </button>
              </div>
            </div>
          </div>

          <button
            type="button"
            class="btn-add-rule"
            @click="emit('open-create-rule')"
          >
            <span class="material-symbols-outlined icon-18">add</span>
            <span>Thêm quy tắc nhân điểm</span>
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.loyalty-config-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.section-header {
  display: flex;
  align-items: center;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.icon-heart-badge {
  font-size: 20px;
  padding: 8px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

.section-title {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .section-title {
  color: #ffffff;
}

.config-card {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 20px;
  padding: 24px;
  border: 1px solid var(--outline-variant, #e1e3e4);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

:global(html.dark) .config-card {
  background: #1e2025;
  border-color: #353942;
}

.config-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
}

.col-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.col-title {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.06em;
  color: var(--on-surface-variant, #4f4446);
  text-transform: uppercase;
}

.btn-edit-rates {
  border: none;
  background: transparent;
  color: var(--primary, #78555e);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 4px;
}

.btn-edit-rates:hover {
  text-decoration: underline;
}

.rates-display-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.conversion-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 18px;
  border-radius: 14px;
  background: var(--surface-container-low, #f3f4f5);
  transition: background 0.2s ease;
}

:global(html.dark) .conversion-box {
  background: #262930;
}

.conversion-box:hover {
  background: var(--surface-container, #edeeef);
}

:global(html.dark) .conversion-box:hover {
  background: #2e323b;
}

.box-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.box-icon {
  font-size: 20px;
  color: var(--on-surface-variant, #4f4446);
}

.box-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .box-label {
  color: #e2e8f0;
}

.box-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.val-vnd {
  font-size: 16px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .val-vnd {
  color: #ffffff;
}

.arrow-icon {
  font-size: 16px;
  color: var(--on-surface-variant, #4f4446);
}

.val-pts {
  font-size: 18px;
  font-weight: 800;
  color: var(--primary, #78555e);
}

:global(html.dark) .val-pts {
  color: #f8b4c4;
}

.pts-unit {
  font-size: 13px;
  font-weight: 500;
  color: var(--on-surface-variant, #4f4446);
}

/* Edit Mode */
.rates-edit-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
  border-radius: 14px;
  background: var(--surface-container-low, #f3f4f5);
}

:global(html.dark) .rates-edit-form {
  background: #262930;
}

.edit-rate-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.edit-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--on-surface-variant, #4f4446);
}

.edit-inputs-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.rate-input {
  width: 110px;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  font-size: 14px;
  font-weight: 700;
}

.rate-input-sm {
  width: 70px;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  font-size: 14px;
  font-weight: 700;
}

.unit-text {
  font-size: 13px;
  font-weight: 600;
  color: var(--on-surface-variant, #4f4446);
}

.edit-actions-row {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 6px;
}

.btn-cancel {
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  background: transparent;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.btn-save {
  padding: 6px 14px;
  border-radius: 8px;
  border: none;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

/* Simulator */
.simulator-panel {
  margin-top: 18px;
  padding: 14px 16px;
  border-radius: 14px;
  background: rgba(120, 85, 94, 0.05);
  border: 1px dashed var(--primary-fixed-dim, #e7bbc6);
}

:global(html.dark) .simulator-panel {
  background: rgba(248, 180, 196, 0.05);
  border-color: #53333e;
}

.sim-header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 10px;
}

.sim-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--primary, #78555e);
}

:global(html.dark) .sim-title {
  color: #f8b4c4;
}

.sim-field {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.sim-field-label {
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
}

.sim-input {
  width: 120px;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  font-size: 13px;
  font-weight: 700;
  text-align: right;
}

.sim-result-row {
  display: flex;
  justify-content: space-between;
  padding-top: 8px;
  border-top: 1px solid rgba(120, 85, 94, 0.1);
}

.sim-res-item {
  display: flex;
  flex-direction: column;
}

.sim-res-label {
  font-size: 11px;
  color: var(--on-surface-variant, #4f4446);
}

.sim-res-pts {
  font-size: 15px;
  font-weight: 800;
  color: var(--primary, #78555e);
}

:global(html.dark) .sim-res-pts {
  color: #f8b4c4;
}

.sim-res-val {
  font-size: 14px;
  font-weight: 700;
  color: #10b981;
}

/* Multipliers */
.multiplier-rules-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rule-row-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-container-low, #f3f4f5);
  transition: all 0.15s ease;
}

:global(html.dark) .rule-row-item {
  background: #262930;
}

.rule-row-item.is-disabled {
  opacity: 0.55;
}

.rule-info-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 9999px;
  flex-shrink: 0;
}

.status-dot.dot-active {
  background: var(--primary, #78555e);
}

.status-dot.dot-inactive {
  background: var(--muted, #94a3b8);
}

.rule-text-block {
  display: flex;
  flex-direction: column;
}

.rule-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .rule-name {
  color: #ffffff;
}

.rule-paused-tag {
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 4px;
  background: #ef4444;
  color: #ffffff;
  font-weight: 700;
}

.rule-schedule {
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
}

.rule-actions-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.multiplier-badge {
  padding: 4px 10px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  font-size: 12px;
  font-weight: 700;
}

.btn-toggle-rule,
.btn-edit-rule,
.btn-delete-rule {
  border: none;
  background: transparent;
  color: var(--on-surface-variant, #4f4446);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
}

.btn-toggle-rule:hover,
.btn-edit-rule:hover {
  color: var(--primary, #78555e);
  background: rgba(120, 85, 94, 0.08);
}

.btn-delete-rule:hover {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.08);
}

.btn-add-rule {
  margin-top: 14px;
  border: none;
  background: transparent;
  color: var(--primary, #78555e);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 0;
}

:global(html.dark) .btn-add-rule {
  color: #f8b4c4;
}

.btn-add-rule:hover {
  text-decoration: underline;
}

@media (max-width: 960px) {
  .config-grid {
    grid-template-columns: 1fr;
    gap: 24px;
  }
}
</style>
