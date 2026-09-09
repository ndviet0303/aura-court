<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CustomerProfile } from '../types/pos'

const props = defineProps<{
  show: boolean
  customerDatabase: CustomerProfile[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'select', customer: CustomerProfile): void
}>()

const { t } = useI18n()
const customerQuery = ref('')

const filteredCustomers = computed(() => {
  const q = customerQuery.value.trim().toLowerCase()
  if (!q) return props.customerDatabase
  return props.customerDatabase.filter(
    (c) => c.name.toLowerCase().includes(q) || (c.phone || '').includes(q) || (c.tier ? c.tier.toLowerCase().includes(q) : false)
  )
})

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined modal-icon">person_search</span>
          <h3 class="modal-title">{{ t('admin.pos.customerSearch') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="search-input-wrap">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="customerQuery"
            type="text"
            class="form-input"
            :placeholder="t('admin.pos.customerQueryPlaceholder')"
            autofocus
          />
          <button
            v-if="customerQuery"
            type="button"
            class="clear-query-btn"
            @click="customerQuery = ''"
          >
            <span class="material-symbols-outlined icon-16">close</span>
          </button>
        </div>

        <div class="customer-list-box">
          <div v-if="filteredCustomers.length === 0" class="empty-customers">
            <span class="material-symbols-outlined icon-32">person_off</span>
            <p>{{ t('admin.pos.noCustomersFound') }}</p>
          </div>

          <div
            v-for="cust in filteredCustomers"
            :key="cust.id"
            class="customer-select-item"
            role="button"
            tabindex="0"
            @click="emit('select', cust)"
            @keydown.enter="emit('select', cust)"
          >
            <div class="cust-info">
              <div class="cust-header-line">
                <strong class="cust-name">{{ cust.name }}</strong>
                <span class="cust-phone">{{ cust.phone }}</span>
              </div>
              <div class="cust-badges-line">
                <span class="tier-pill" :class="(cust.tier || 'standard').toLowerCase()">
                  {{ cust.tier || 'Standard' }}
                </span>
                <span class="discount-pill">
                  -{{ Math.round(cust.discount_rate * 100) }}%
                </span>
                <span class="wallet-pill" v-if="cust.wallet_balance > 0">
                  <span class="material-symbols-outlined icon-12">account_balance_wallet</span>
                  {{ formatMoney(cust.wallet_balance) }}
                </span>
                <span class="points-pill" v-if="cust.loyalty_points > 0">
                  <span class="material-symbols-outlined icon-12">star</span>
                  {{ cust.loyalty_points }} pts
                </span>
              </div>
            </div>

            <button type="button" class="btn-select">
              <span class="material-symbols-outlined icon-16">check</span>
              <span>{{ t('admin.pos.select') }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 520px;
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px solid var(--outline, #e2e8f0);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  color: var(--ink, #0f172a);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  color: var(--primary, #10b981);
  font-size: 22px;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.modal-close-btn {
  background: transparent;
  border: none;
  color: var(--muted, #64748b);
  cursor: pointer;
  display: flex;
  align-items: center;
}

.modal-body {
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.search-input-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  background: var(--surface-hover, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline, #cbd5e1);
  border-radius: 10px;
}

.search-icon {
  color: var(--muted, #64748b);
  font-size: 20px;
}

.form-input {
  flex: 1;
  padding: 10px 0;
  background: transparent;
  border: none;
  outline: none;
  font-size: 13px;
  color: var(--ink, #0f172a);
}

.clear-query-btn {
  background: transparent;
  border: none;
  color: var(--muted, #64748b);
  cursor: pointer;
  display: flex;
  align-items: center;
}

.customer-list-box {
  max-height: 380px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.empty-customers {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 36px 10px;
  text-align: center;
  color: var(--muted, #64748b);
}

.empty-customers p {
  margin: 8px 0 0;
  font-size: 13px;
}

.customer-select-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--surface-hover, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline, #e2e8f0);
  cursor: pointer;
  transition: all 0.2s ease;
}

.customer-select-item:hover {
  background: rgba(16, 185, 129, 0.06);
  border-color: var(--primary, #10b981);
}

.cust-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.cust-header-line {
  display: flex;
  align-items: center;
  gap: 8px;
}

.cust-name {
  font-size: 13px;
  color: var(--ink, #0f172a);
}

.cust-phone {
  font-size: 12px;
  color: var(--muted, #64748b);
}

.cust-badges-line {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.tier-pill {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: #e2e8f0;
  color: #475569;
}

.tier-pill.vip {
  background: #fef3c7;
  color: #d97706;
}

.tier-pill.gold {
  background: #fef08a;
  color: #ca8a04;
}

.tier-pill.silver {
  background: #e2e8f0;
  color: #475569;
}

.discount-pill {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}

.wallet-pill,
.points-pill {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 10px;
  color: var(--muted, #64748b);
}

.btn-select {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 6px;
  background: var(--primary, #10b981);
  border: none;
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.btn-select:hover {
  background: #059669;
}

.icon-12 {
  font-size: 12px;
}

.icon-16 {
  font-size: 16px;
}

.icon-32 {
  font-size: 32px;
}
</style>
