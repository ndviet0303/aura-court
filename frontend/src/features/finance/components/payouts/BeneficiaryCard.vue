<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { BeneficiaryAccount } from '../../types/finance'

defineProps<{
  beneficiary: BeneficiaryAccount
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="beneficiary-card">
    <div v-if="loading" class="skeleton-beneficiary">
      <div class="skeleton-shimmer sk-bank-icon"></div>
      <div class="sk-meta">
        <div class="skeleton-shimmer sk-bank-title"></div>
        <div class="skeleton-shimmer sk-bank-details"></div>
      </div>
      <div class="skeleton-shimmer sk-btn"></div>
    </div>

    <template v-else>
      <div class="card-left">
        <div class="bank-avatar">
          <span class="material-symbols-outlined icon-28">account_balance</span>
        </div>

        <div class="bank-meta">
          <div class="title-row">
            <h3 class="bank-name">
              {{ beneficiary.bank_name || beneficiary.bank_code || t('admin.payouts.noBeneficiary') }}
              <span v-if="beneficiary.bank_code" class="bank-code-tag">({{ beneficiary.bank_code }})</span>
            </h3>

            <span
              class="kyc-badge"
              :class="{
                'kyc-verified': beneficiary.is_verified || beneficiary.status === 'verified',
                'kyc-pending': !(beneficiary.is_verified || beneficiary.status === 'verified'),
              }"
            >
              <span class="material-symbols-outlined icon-14">
                {{ beneficiary.is_verified || beneficiary.status === 'verified' ? 'verified' : 'schedule' }}
              </span>
              <span>
                {{
                  beneficiary.is_verified || beneficiary.status === 'verified'
                    ? t('admin.payouts.kycVerified')
                    : t('admin.payouts.kycPending')
                }}
              </span>
            </span>
          </div>

          <div class="account-details">
            <span class="detail-item">
              <span class="detail-label">{{ t('admin.payouts.accountNumberLabel') }}</span>
              <strong class="font-mono text-ink">{{ beneficiary.account_number || '---' }}</strong>
            </span>
            <span class="divider">·</span>
            <span class="detail-item">
              <span class="detail-label">{{ t('admin.payouts.accountHolderLabel') }}</span>
              <strong class="text-ink">{{ beneficiary.account_holder || '---' }}</strong>
            </span>
            <span v-if="beneficiary.tax_id" class="divider">·</span>
            <span v-if="beneficiary.tax_id" class="detail-item">
              <span class="detail-label">{{ t('admin.payouts.taxIdLabel') }}</span>
              <strong class="font-mono text-ink">{{ beneficiary.tax_id }}</strong>
            </span>
          </div>
        </div>
      </div>

      <div class="card-right">
        <AuraButton variant="secondary" size="sm" class="btn-update-beneficiary" @click="emit('update')">
          <span class="material-symbols-outlined icon-16">edit_square</span>
          <span>{{ t('admin.payouts.editBeneficiaryBtn') }}</span>
        </AuraButton>
      </div>
    </template>
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
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
  flex-wrap: wrap;
  gap: 14px;
}

.card-left {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
  flex: 1;
}

.bank-avatar {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.08);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-28 {
  font-size: 28px;
}

.bank-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.bank-name {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 6px;
}

.bank-code-tag {
  font-size: 13px;
  color: var(--muted);
  font-weight: 500;
}

.kyc-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  letter-spacing: 0.3px;
}

.kyc-verified {
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.kyc-pending {
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.2);
}

.account-details {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 13px;
  color: var(--muted);
}

.detail-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.detail-label {
  color: var(--muted);
}

.text-ink {
  color: var(--ink);
}

.divider {
  color: var(--outline);
}

.card-right {
  display: flex;
  align-items: center;
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}

/* Skeleton */
.skeleton-beneficiary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 16px;
}

.skeleton-shimmer {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
  border-radius: 6px;
}

.skeleton-shimmer::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 209, 220, 0.45) 45%,
    rgba(255, 255, 255, 0.8) 55%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer-wave 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

:global(html.dark) .skeleton-shimmer {
  background-color: var(--soft, #2b2e36);
}

.sk-bank-icon {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  flex-shrink: 0;
}

.sk-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.sk-bank-title {
  width: 40%;
  height: 16px;
}

.sk-bank-details {
  width: 70%;
  height: 12px;
}

.sk-btn {
  width: 120px;
  height: 36px;
  border-radius: 8px;
}

@keyframes shimmer-wave {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

@media (max-width: 640px) {
  .beneficiary-card {
    flex-direction: column;
    align-items: flex-start;
  }
  .card-right {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
