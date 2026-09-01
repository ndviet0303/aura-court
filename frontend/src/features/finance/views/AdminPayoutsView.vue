<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminPayouts } from '../composables/useAdminPayouts'

import PayoutMetricsBar from '../components/payouts/PayoutMetricsBar.vue'
import BeneficiaryCard from '../components/payouts/BeneficiaryCard.vue'
import PayoutStepper from '../components/payouts/PayoutStepper.vue'
import PayoutHistoryTable from '../components/payouts/PayoutHistoryTable.vue'
import CreatePayoutModal from '../components/payouts/CreatePayoutModal.vue'
import EditBeneficiaryModal from '../components/payouts/EditBeneficiaryModal.vue'

const { t } = useI18n()

const {
  loading,
  notice,
  error,
  settlements,
  payouts,
  beneficiary,
  metrics,
  showCreateModal,
  showEditBeneficiaryModal,
  loadData,
  createPayout,
  updateBeneficiary,
} = useAdminPayouts()

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="admin-payouts-view">
    <!-- Header Section -->
    <header class="payouts-header">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">account_balance_wallet</span>
          <h1 class="page-title">{{ t('admin.payouts.title') }}</h1>
        </div>
        <p class="page-sub">{{ t('admin.payouts.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <AuraButton variant="primary" class="btn-primary-action" @click="showCreateModal = true">
          <span class="material-symbols-outlined icon-18">payments</span>
          <span>{{ t('admin.payouts.createPayoutBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Settlement Metrics -->
    <PayoutMetricsBar
      :gross="metrics.gross"
      :fee="metrics.fee"
      :net="metrics.net"
    />

    <!-- Beneficiary Account Card -->
    <BeneficiaryCard
      :beneficiary="beneficiary"
      @update="showEditBeneficiaryModal = true"
    />

    <!-- Visual 5-Step Payout Stepper -->
    <PayoutStepper />

    <!-- Payout History Table -->
    <PayoutHistoryTable :payouts="payouts" />

    <!-- Modals -->
    <CreatePayoutModal
      :show="showCreateModal"
      :available-net="metrics.net"
      @close="showCreateModal = false"
      @submit="createPayout"
    />

    <EditBeneficiaryModal
      :show="showEditBeneficiaryModal"
      :beneficiary="beneficiary"
      @close="showEditBeneficiaryModal = false"
      @submit="updateBeneficiary"
    />
  </div>
</template>

<style scoped>
.admin-payouts-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.payouts-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.header-actions {
  display: flex;
  align-items: center;
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

.icon-18 {
  font-size: 18px;
}
</style>
