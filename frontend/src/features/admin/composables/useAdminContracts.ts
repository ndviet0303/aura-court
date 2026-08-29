import { ref, computed } from 'vue'

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
  const activeStatusTab = ref<string>('all')
  const searchQuery = ref('')
  const selectedContract = ref<RecurringContract | null>(null)
  const showDetailModal = ref(false)
  const showCreateModal = ref(false)
  const notice = ref('')
  const error = ref('')

  const contracts = ref<RecurringContract[]>([
    {
      id: 1,
      contract_code: 'CTR-2026-001',
      customer_name: 'CLB Cầu Lông Passion',
      phone: '0909 112 233',
      court_name: 'Sân 1 (Sân Huấn Luyện)',
      venue_name: 'Downtown Arena (Quận 1)',
      time_slot: '18:00 - 20:00',
      weekday: 'Thứ 2, 4, 6',
      start_date: '2026-01-01',
      end_date: '2026-12-31',
      total_monthly_amount: 4800000,
      deposit_amount: 5000000,
      status: 'active',
      payment_cycles: [
        { id: 101, cycle_name: 'Kỳ 01/2026', due_date: '05/01/2026', amount: 4800000, status: 'paid', invoice_code: 'INV-2026-0101' },
        { id: 102, cycle_name: 'Kỳ 02/2026', due_date: '05/02/2026', amount: 4800000, status: 'paid', invoice_code: 'INV-2026-0201' },
        { id: 103, cycle_name: 'Kỳ 03/2026', due_date: '05/03/2026', amount: 4800000, status: 'paid', invoice_code: 'INV-2026-0301' },
      ],
    },
    {
      id: 2,
      contract_code: 'CTR-2026-002',
      customer_name: 'CLB Smash Pro',
      phone: '0918 334 455',
      court_name: 'Sân 4 (Thảm Yonex Đạt Chuẩn)',
      venue_name: 'Downtown Arena (Quận 1)',
      time_slot: '19:00 - 21:00',
      weekday: 'Thứ 3, 5, 7',
      start_date: '2026-02-01',
      end_date: '2026-08-01',
      total_monthly_amount: 5200000,
      deposit_amount: 5000000,
      status: 'overdue',
      payment_cycles: [
        { id: 201, cycle_name: 'Kỳ 02/2026', due_date: '05/02/2026', amount: 5200000, status: 'paid', invoice_code: 'INV-2026-0202' },
        { id: 202, cycle_name: 'Kỳ 03/2026', due_date: '05/03/2026', amount: 5200000, status: 'overdue', invoice_code: 'INV-2026-0302' },
      ],
    },
    {
      id: 3,
      contract_code: 'CTR-2026-003',
      customer_name: 'Công ty FPT Software',
      phone: '0988 776 655',
      court_name: 'Sân 2 (Sân Thi Đấu VIP)',
      venue_name: 'Westside Courts (Bình Thạnh)',
      time_slot: '17:30 - 19:30',
      weekday: 'Thứ 4, Thứ 6',
      start_date: '2026-03-01',
      end_date: '2027-03-01',
      total_monthly_amount: 3600000,
      deposit_amount: 4000000,
      status: 'active',
      payment_cycles: [
        { id: 301, cycle_name: 'Kỳ 03/2026', due_date: '05/03/2026', amount: 3600000, status: 'paid', invoice_code: 'INV-2026-0303' },
      ],
    },
  ])

  const stats = computed(() => ({
    activeContracts: 42,
    mrrAmount: 184500000,
    overdueCycles: 3,
    primeTimeUtilization: 94,
  }))

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

  return {
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
  }
}
