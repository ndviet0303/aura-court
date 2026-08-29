<script setup lang="ts">
import { onMounted } from 'vue'
import { useAdminPayouts } from '../composables/useAdminPayouts'

import PayoutMetricsBar from '../components/payouts/PayoutMetricsBar.vue'
import BeneficiaryCard from '../components/payouts/BeneficiaryCard.vue'
import PayoutStepper from '../components/payouts/PayoutStepper.vue'
import PayoutHistoryTable from '../components/payouts/PayoutHistoryTable.vue'
import CreatePayoutModal from '../components/payouts/CreatePayoutModal.vue'
import EditBeneficiaryModal from '../components/payouts/EditBeneficiaryModal.vue'

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
        <h1 class="page-title">💰 Báo cáo Doanh thu & Rút tiền</h1>
        <p class="page-sub">Theo dõi doanh thu thanh toán qua cổng điện tử, đối soát phí sàn và thực hiện lệnh rút tiền về tài khoản ngân hàng.</p>
      </div>

      <div class="header-actions">
        <button class="btn-create-payout btn-primary-action" @click="showCreateModal = true">
          💸 Tạo Lệnh Rút Tiền
        </button>
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

.page-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.btn-create-payout {
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
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
</style>
