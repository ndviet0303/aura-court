import { ref, computed, onMounted } from 'vue'
import { apiUrl } from '@/core/api/client'
import { contractsApi } from '../api/contractsApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { RecurringContractItem, ContractStats } from '../types/contract'

export function useAdminContracts() {
  const loading = ref(false)
  const actionLoading = ref(false)
  const activeStatusTab = ref<string>('all')
  const searchQuery = ref('')
  const selectedVenueId = ref<number | undefined>(undefined)

  const selectedContract = ref<RecurringContractItem | null>(null)

  const showDetailModal = ref(false)

  const notice = ref('')
  const error = ref('')

  const contracts = ref<RecurringContractItem[]>([])
  const venues = ref<any[]>([])

  const rawStats = ref<ContractStats>({
    active_contracts: 0,
    total_contracts: 0,
    mrr_amount: 0,
    overdue_cycles: 0,
  })

  function mapContract(item: any): RecurringContractItem {
    const meta = item.metadata || {}
    const court = item.court || {}
    const venue = court.venue || {}
    const user = item.user || {}

    const invoices = (item.invoices || []).map((inv: any) => ({
      id: inv.id,
      uuid: inv.uuid,
      period_start: inv.period_start ? String(inv.period_start).substring(0, 10) : '',
      period_end: inv.period_end ? String(inv.period_end).substring(0, 10) : '',
      due_at: inv.due_at ? String(inv.due_at).substring(0, 10) : '',
      amount: Number(inv.amount || 0),
      status: inv.status || 'pending',
      payment_reference: inv.payment_reference || null,
      paid_at: inv.paid_at || null,
    }))

    const occurrences = (item.occurrences || []).map((occ: any) => ({
      id: occ.id,
      sequence: occ.sequence || 1,
      starts_at: occ.starts_at || '',
      ends_at: occ.ends_at || '',
      amount: Number(occ.amount || 0),
      status: occ.status || 'confirmed',
      reservation_id: occ.reservation_id,
    }))

    // Determine normalized status
    let status = item.status || 'confirmed'
    const hasOverdueInvoice = invoices.some((i: any) => i.status === 'overdue')
    if (status === 'confirmed' || status === 'active') {
      status = hasOverdueInvoice ? 'overdue' : 'active'
    }

    return {
      id: item.id,
      contract_code:
        item.contract_code || meta.contract_code || `CTR-2026-${String(item.id).padStart(4, '0')}`,
      customer_name: item.customer_name || meta.customer_name || user.name || 'Khách hàng HĐ',
      phone: item.phone || meta.phone || user.phone || '—',
      email: item.email || user.email,
      court_id: court.id || item.court_id,
      court_name: item.court_name || court.code || 'Sân tiêu chuẩn',
      venue_name: item.venue_name || venue.name || 'Cơ sở chính',
      venue_id: item.venue_id || venue.id,
      time_slot:
        item.time_slot ||
        meta.time_slot ||
        `${item.starts_at ? new Date(item.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '18:00'} - 20:00`,
      weekday: item.weekday || meta.weekday || 'Thứ 2, 4, 6',
      frequency: item.frequency || 'weekly',
      start_date:
        item.start_date ||
        (item.starts_at ? String(item.starts_at).substring(0, 10) : '2026-01-01'),
      end_date:
        item.end_date || (item.ends_at ? String(item.ends_at).substring(0, 10) : '2026-12-31'),
      total_monthly_amount:
        item.total_monthly_amount ?? meta.monthly_amount ?? (invoices[0]?.amount || 4800000),
      deposit_amount: item.deposit_amount ?? meta.deposit_amount ?? 5000000,
      status,
      occurrences_count: item.occurrence_count || item.occurrences_count || occurrences.length,
      notes: item.notes || meta.notes || '',
      invoices,
      occurrences,
    }
  }

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [contractsRes, venuesRes] = await Promise.all([
        contractsApi.list({ status: 'all' }),
        contractsApi.getVenues().catch(() => []),
      ])

      const list = Array.isArray(contractsRes) ? contractsRes : contractsRes?.data || []
      contracts.value = list.map(mapContract)

      if (contractsRes?.stats) {
        rawStats.value = contractsRes.stats
      }

      venues.value = Array.isArray(venuesRes) ? venuesRes : (venuesRes as any)?.data || []
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách hợp đồng định kỳ.')
    } finally {
      loading.value = false
    }
  }

  const stats = computed<ContractStats>(() => {
    return {
      active_contracts:
        rawStats.value.active_contracts ||
        contracts.value.filter((c) => c.status === 'active' || c.status === 'confirmed').length,
      total_contracts: rawStats.value.total_contracts || contracts.value.length,
      mrr_amount:
        rawStats.value.mrr_amount ||
        contracts.value
          .filter((c) => c.status === 'active' || c.status === 'confirmed')
          .reduce((sum, c) => sum + c.total_monthly_amount, 0),
      overdue_cycles:
        rawStats.value.overdue_cycles ||
        contracts.value.filter((c) => c.status === 'overdue').length,
    }
  })

  const filteredContracts = computed(() => {
    return contracts.value.filter((c) => {
      const matchStatus =
        activeStatusTab.value === 'all' ||
        (activeStatusTab.value === 'active' && c.status === 'active') ||
        (activeStatusTab.value === 'overdue' && c.status === 'overdue') ||
        (activeStatusTab.value === 'cancelled' && c.status === 'cancelled')

      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        c.customer_name.toLowerCase().includes(q) ||
        c.contract_code.toLowerCase().includes(q) ||
        c.phone.includes(q) ||
        c.court_name.toLowerCase().includes(q) ||
        c.venue_name.toLowerCase().includes(q)

      const matchVenue = !selectedVenueId.value || c.venue_id === selectedVenueId.value

      return matchStatus && matchSearch && matchVenue
    })
  })

  async function cancelContract(contractId: number, reason?: string) {
    actionLoading.value = true
    error.value = ''
    try {
      await contractsApi.cancel(contractId, reason)
      showNotice('Đã chấm dứt hiệu lực hợp đồng.')
      if (selectedContract.value && selectedContract.value.id === contractId) {
        selectedContract.value.status = 'cancelled'
      }
      await loadData()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể huỷ hợp đồng.')
    } finally {
      actionLoading.value = false
    }
  }

  function exportExcel() {
    window.open(apiUrl('/admin/contracts.csv'), '_blank')
    showNotice('Đang tải xuống tệp danh sách hợp đồng định kỳ...')
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  function openDetail(contract: RecurringContractItem) {
    selectedContract.value = contract
    showDetailModal.value = true
  }

  onMounted(() => {
    loadData()
  })

  return {
    loading,
    actionLoading,
    activeStatusTab,
    searchQuery,
    selectedVenueId,
    selectedContract,
    showDetailModal,
    notice,
    error,
    contracts,
    venues,
    stats,
    filteredContracts,
    loadData,
    cancelContract,
    exportExcel,
    showNotice,
    openDetail,
  }
}
