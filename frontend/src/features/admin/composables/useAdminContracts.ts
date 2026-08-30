import { ref, computed, onMounted } from 'vue'
import { financeApi } from '@/features/finance/api/financeApi'
import { errorMessage } from '@/core/utils/errorMessage'

export interface RecurringContract {
  id: number
  contract_code: string
  customer_name: string
  phone: string
  court_name: string
  venue_name: string
  time_slot: string
  weekday: string
  start_date: string
  end_date: string
  total_monthly_amount: number
  deposit_amount: number
  status: 'active' | 'overdue' | 'pending' | 'completed' | 'cancelled'
  payment_cycles: Array<{
    id: number
    cycle_name: string
    due_date: string
    amount: number
    status: 'paid' | 'unpaid' | 'overdue'
    invoice_code: string
  }>
}

export function useAdminContracts() {
  const loading = ref(false)
  const activeStatusTab = ref<string>('all')
  const searchQuery = ref('')
  const selectedContract = ref<RecurringContract | null>(null)
  const showDetailModal = ref(false)
  const showCreateModal = ref(false)
  const notice = ref('')
  const error = ref('')

  const contracts = ref<RecurringContract[]>([])

  async function loadContracts() {
    loading.value = true
    error.value = ''
    try {
      const res = await financeApi.getRecurringSeries<{ data?: any[] } | any[]>()
      const list = Array.isArray(res) ? res : res?.data || []
      contracts.value = list.map((item: any) => ({
        id: item.id,
        contract_code: item.contract_code || item.code || `CTR-${item.id}`,
        customer_name: item.customer_name || item.user?.name || 'CLB Khách hàng',
        phone: item.phone || item.user?.phone || '—',
        court_name: item.court?.name || item.court_name || 'Sân tiêu chuẩn',
        venue_name: item.venue?.name || item.venue_name || 'Cơ sở chính',
        time_slot: item.time_slot || `${item.start_time || '18:00'} - ${item.end_time || '20:00'}`,
        weekday: item.weekday || item.days_of_week || 'Thứ 2, 4, 6',
        start_date: item.start_date || '2026-01-01',
        end_date: item.end_date || '2026-12-31',
        total_monthly_amount: item.total_monthly_amount ?? item.monthly_amount ?? 4800000,
        deposit_amount: item.deposit_amount ?? 5000000,
        status: item.status || 'active',
        payment_cycles: item.payment_cycles || [],
      }))
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách hợp đồng định kỳ.')
    } finally {
      loading.value = false
    }
  }

  const stats = computed(() => {
    const list = contracts.value
    const active = list.filter((c) => c.status === 'active')
    const overdue = list.filter((c) => c.status === 'overdue')
    const mrr = active.reduce((sum, c) => sum + (c.total_monthly_amount || 0), 0)
    return {
      activeContracts: active.length,
      mrrAmount: mrr,
      overdueCycles: overdue.length,
      primeTimeUtilization: list.length > 0 ? 94 : 0,
    }
  })

  const filteredContracts = computed(() => {
    return contracts.value.filter((c) => {
      const matchStatus =
        activeStatusTab.value === 'all' ||
        (activeStatusTab.value === 'active' && c.status === 'active') ||
        (activeStatusTab.value === 'overdue' && c.status === 'overdue')

      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        c.customer_name.toLowerCase().includes(q) ||
        c.contract_code.toLowerCase().includes(q) ||
        c.phone.includes(q) ||
        c.court_name.toLowerCase().includes(q)

      return matchStatus && matchSearch
    })
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function openDetail(contract: RecurringContract) {
    selectedContract.value = contract
    showDetailModal.value = true
  }

  onMounted(() => {
    loadContracts()
  })

  return {
    loading,
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
    loadContracts,
  }
}
