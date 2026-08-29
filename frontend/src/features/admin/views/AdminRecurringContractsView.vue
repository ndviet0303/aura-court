<script setup lang="ts">
import { useAdminContracts } from '../composables/useAdminContracts'

import ContractsTable from '../components/contracts/ContractsTable.vue'
import ContractDetailModal from '../components/contracts/ContractDetailModal.vue'
import ContractCreateModal from '../components/contracts/ContractCreateModal.vue'

const {
  activeStatusTab,
  searchQuery,
  selectedContract,
  showDetailModal,
  showCreateModal,
  notice,
  error,
  contracts,
  stats,
  filteredContracts,
  showNotice,
  openDetail,
} = useAdminContracts()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function handleCreateContract(payload: any) {
  contracts.value.unshift({
    id: Date.now(),
    contract_code: `CTR-2026-00${contracts.value.length + 1}`,
    customer_name: payload.customer_name,
    phone: payload.phone,
    court_name: payload.court_name,
    venue_name: 'Downtown Arena (Quận 1)',
    time_slot: payload.time_slot,
    weekday: payload.weekday,
    start_date: '2026-04-01',
    end_date: '2027-04-01',
    total_monthly_amount: payload.total_monthly_amount,
    deposit_amount: 5000000,
    status: 'active',
    payment_cycles: [],
  })
  showCreateModal.value = false
  showNotice(`Đã tạo thành công hợp đồng định kỳ mới cho ${payload.customer_name}!`)
}
</script>

<template>
  <div class="admin-contracts-view">
    <!-- Header Section -->
    <header class="contracts-header">
      <div class="header-titles">
        <h1 class="page-title">📅 Quản Lý Hợp Đồng Đặt Sân Định Kỳ</h1>
        <p class="page-sub">Theo dõi hợp đồng cố định hàng tháng, lịch thu tiền định kỳ và tỷ lệ lấp đầy sân.</p>
      </div>

      <div class="header-actions">
        <button class="btn-export-excel" @click="showNotice('Đang xuất file Excel hợp đồng...')">
          📊 Xuất Báo Cáo Excel
        </button>
        <button class="btn-create-contract btn-primary-pink" @click="showCreateModal = true">
          ➕ Tạo Hợp Đồng Mới
        </button>
      </div>
    </header>

    <!-- 4 KPI Stat Cards -->
    <div class="contracts-kpi-grid">
      <div class="kpi-card">
        <span class="kpi-icon">📝</span>
        <span class="kpi-label">Tổng Hợp Đồng Đang Chạy</span>
        <strong class="kpi-value">{{ stats.activeContracts }} Hợp đồng</strong>
        <span class="kpi-trend text-green">↑ Tăng 12% so với tháng trước</span>
      </div>

      <div class="kpi-card">
        <span class="kpi-icon">💰</span>
        <span class="kpi-label">Doanh Thu Định Kỳ (MRR)</span>
        <strong class="kpi-value text-pink">{{ formatMoney(stats.mrrAmount) }}</strong>
        <span class="kpi-trend">Đóng góp 65% tổng doanh thu</span>
      </div>

      <div class="kpi-card">
        <span class="kpi-icon">⚠️</span>
        <span class="kpi-label">Kỳ Thanh Toán Cần Xử Lý</span>
        <strong class="kpi-value text-yellow">{{ stats.overdueCycles }} Kỳ cần thu</strong>
        <span class="kpi-trend text-red">Quá hạn thanh toán</span>
      </div>

      <div class="kpi-card">
        <span class="kpi-icon">⚡</span>
        <span class="kpi-label">Lấp Đầy Giờ Vàng</span>
        <strong class="kpi-value text-green">{{ stats.primeTimeUtilization }}%</strong>
        <span class="kpi-trend">Khung 17:00 - 21:00</span>
      </div>
    </div>

    <!-- Alert Notices -->
    <div v-if="notice" class="alert-pill toast-floating-banner">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Main Table -->
    <ContractsTable
      :contracts="filteredContracts"
      :active-status-tab="activeStatusTab"
      :search-query="searchQuery"
      @update:active-status-tab="activeStatusTab = $event"
      @update:search-query="searchQuery = $event"
      @view-detail="openDetail"
      @open-create="showCreateModal = true"
    />

    <!-- Modals -->
    <ContractDetailModal
      :show="showDetailModal"
      :contract="selectedContract"
      @close="showDetailModal = false"
    />

    <ContractCreateModal
      :show="showCreateModal"
      @close="showCreateModal = false"
      @create="handleCreateContract"
    />
  </div>
</template>

<style scoped>
.admin-contracts-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.contracts-header {
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

.header-actions {
  display: flex;
  gap: 10px;
}

.btn-export-excel {
  padding: 10px 16px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-create-contract {
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
}

.contracts-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.kpi-icon {
  font-size: 22px;
}

.kpi-label {
  font-size: 12px;
  color: var(--muted);
}

.kpi-value {
  font-size: 18px;
}

.kpi-trend {
  font-size: 11px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }

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

@media (max-width: 900px) {
  .contracts-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
