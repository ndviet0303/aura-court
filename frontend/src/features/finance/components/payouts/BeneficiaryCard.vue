<script setup lang="ts">
import type { BeneficiaryInfo } from '../../composables/useAdminPayouts'

defineProps<{
  beneficiary: BeneficiaryInfo
}>()

const emit = defineEmits<{
  (e: 'update'): void
}>()
</script>

<template>
  <div class="beneficiary-card">
    <div class="card-left">
      <div class="bank-ico">🏦</div>
      <div class="bank-meta">
        <div class="title-row">
          <h3 class="bank-name">{{ beneficiary.bank_name }} ({{ beneficiary.bank_code }})</h3>
          <span class="kyc-badge">✓ KYC VERIFIED</span>
        </div>
        <div class="account-details">
          <span>STK: <strong class="font-mono">{{ beneficiary.account_number }}</strong></span> · 
          <span>Chủ TK: <strong>{{ beneficiary.account_holder }}</strong></span> · 
          <span>Mã số thuế: <strong class="font-mono">{{ beneficiary.tax_id }}</strong></span>
        </div>
      </div>
    </div>

    <button class="btn-update-beneficiary" @click="emit('update')">
      ✏️ Đổi Tài Khoản
    </button>
  </div>
</template>

<style scoped>
.beneficiary-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  flex-wrap: wrap;
  gap: 12px;
}

.card-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.bank-ico {
  font-size: 28px;
}

.bank-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.bank-name {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}

.kyc-badge {
  font-size: 10px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
}

.account-details {
  font-size: 12px;
  color: var(--muted);
}

.btn-update-beneficiary {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
</style>
