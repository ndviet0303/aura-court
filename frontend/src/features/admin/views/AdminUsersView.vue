<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { AdminUser } from '../api/adminUserApi'
import AccountLockModal from '../components/users/AccountLockModal.vue'
import { RESTRICTED_STATUSES, useAdminUsers } from '../composables/useAdminUsers'

const { t, d } = useI18n()
const {
  users,
  page,
  lastPage,
  total,
  search,
  status,
  loading,
  saving,
  notice,
  error,
  load,
  setLock,
} = useAdminUsers()

const target = ref<AdminUser | null>(null)
const isRestricted = (user: AdminUser) => RESTRICTED_STATUSES.includes(user.status)

async function confirm(reason: string) {
  if (target.value && (await setLock(target.value, reason))) target.value = null
}

onMounted(() => load(1))
</script>

<template>
  <div class="admin-users-view">
    <header class="view-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined header-icon">group</span>
        <h1 class="page-title">{{ t('admin.users.title') }}</h1>
        <span class="total">{{ t('admin.users.total', { count: total }) }}</span>
      </div>
      <form class="filters" @submit.prevent="load(1)">
        <input
          v-model="search"
          type="search"
          class="search-input"
          maxlength="100"
          data-testid="user-search"
          :aria-label="t('admin.users.searchPlaceholder')"
          :placeholder="t('admin.users.searchPlaceholder')"
        />
        <select
          v-model="status"
          class="search-input"
          data-testid="user-status-filter"
          :aria-label="t('admin.users.statusLabel')"
          @change="load(1)"
        >
          <option value="">{{ t('admin.users.allStatuses') }}</option>
          <option v-for="s in ['active', ...RESTRICTED_STATUSES]" :key="s" :value="s">
            {{ t(`admin.users.status.${s}`) }}
          </option>
        </select>
        <AuraButton type="submit" variant="secondary" :disabled="loading">
          {{ t('admin.users.searchBtn') }}
        </AuraButton>
      </form>
    </header>

    <p v-if="notice" class="notice" role="status">{{ notice }}</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <div class="table-wrap">
      <table class="users-table">
        <thead>
          <tr>
            <th>{{ t('admin.users.colName') }}</th>
            <th>{{ t('admin.users.colEmail') }}</th>
            <th>{{ t('admin.users.colRole') }}</th>
            <th>{{ t('admin.users.statusLabel') }}</th>
            <th>{{ t('admin.users.colCreated') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" data-testid="user-row">
            <td>{{ user.name }}</td>
            <td>{{ user.email }}</td>
            <td>{{ user.role }}</td>
            <td>
              <span class="status-badge" :class="isRestricted(user) ? 'restricted' : 'active'">
                {{ t(`admin.users.status.${user.status}`, user.status) }}
              </span>
            </td>
            <td>{{ user.created_at ? d(new Date(user.created_at)) : '' }}</td>
            <td>
              <AuraButton
                size="sm"
                :variant="isRestricted(user) ? 'primary' : 'danger'"
                :data-testid="`toggle-lock-${user.id}`"
                @click="target = user"
              >
                {{ isRestricted(user) ? t('admin.users.unlockBtn') : t('admin.users.lockBtn') }}
              </AuraButton>
            </td>
          </tr>
          <tr v-if="!loading && users.length === 0">
            <td colspan="6" class="empty">{{ t('admin.users.empty') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="lastPage > 1" class="pager">
      <AuraButton variant="ghost" :disabled="loading || page <= 1" @click="load(page - 1)">
        {{ t('admin.users.prevPage') }}
      </AuraButton>
      <span>{{ page }} / {{ lastPage }}</span>
      <AuraButton variant="ghost" :disabled="loading || page >= lastPage" @click="load(page + 1)">
        {{ t('admin.users.nextPage') }}
      </AuraButton>
    </nav>

    <AccountLockModal
      :user="target"
      :locking="target ? !isRestricted(target) : true"
      :saving="saving"
      @close="target = null"
      @confirm="confirm"
    />
  </div>
</template>

<style scoped>
.admin-users-view {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1440px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.view-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--primary);
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  margin: 0;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.search-input {
  padding: 8px 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background: var(--surface);
  color: var(--ink);
  font: inherit;
}

.notice,
.error {
  margin: 0;
  color: #15803d;
}

.error {
  color: #dc2626;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
}

.users-table th,
.users-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--outline);
  white-space: nowrap;
}

.users-table th,
.total {
  font-size: 12px;
  color: var(--muted);
}

.status-badge {
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.status-badge.restricted {
  background: #fee2e2;
  color: #991b1b;
}

.empty {
  text-align: center;
  color: var(--muted);
}

.pager {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 12px;
}
</style>
