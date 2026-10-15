import { ref } from 'vue'
import { errorMessage } from '@/core/utils/errorMessage'
import { adminUserApi, type AdminUser } from '../api/adminUserApi'

export const RESTRICTED_STATUSES = ['locked', 'suspended', 'banned']

export function useAdminUsers() {
  const users = ref<AdminUser[]>([])
  const page = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const search = ref('')
  const status = ref('')
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')

  async function load(targetPage = page.value) {
    loading.value = true
    error.value = ''
    try {
      const res = await adminUserApi.list({
        search: search.value.trim() || undefined,
        status: status.value || undefined,
        page: targetPage,
      })
      users.value = res.data
      page.value = res.current_page
      lastPage.value = res.last_page
      total.value = res.total
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách tài khoản.')
    } finally {
      loading.value = false
    }
  }

  async function setLock(user: AdminUser, reason: string): Promise<boolean> {
    saving.value = true
    notice.value = ''
    error.value = ''
    try {
      const locking = !RESTRICTED_STATUSES.includes(user.status)
      const res = locking
        ? await adminUserApi.lock(user.id, reason)
        : await adminUserApi.unlock(user.id, reason)
      user.status = res.user.status
      notice.value = res.message ?? ''
      return true
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể cập nhật trạng thái tài khoản.')
      return false
    } finally {
      saving.value = false
    }
  }

  return {
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
  }
}
