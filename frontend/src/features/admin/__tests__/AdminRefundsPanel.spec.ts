import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminRefundsPanel from '@/features/admin/components/manage/AdminRefundsPanel.vue'
import { i18n } from '@/i18n'
import type { Refund } from '@/features/booking/types/booking'

const mockRefunds: Refund[] = [
  {
    id: 1,
    amount: 150000,
    status: 'pending',
    note: 'Yêu cầu hoàn tiền lịch sử',
    booking: {
      id: 101,
      code: 'BK-HIST-01',
      total_amount: 150000,
      status: 'cancelled',
      user: {
        id: 10,
        name: 'Nguyen Van A',
        phone: '0901234567',
      },
    } as any,
  },
  {
    id: 2,
    amount: 200000,
    status: 'completed',
    note: 'Đã hoàn tất chuyển khoản ví',
    booking: {
      id: 102,
      code: 'BK-HIST-02',
      total_amount: 200000,
      status: 'refunded',
      user: {
        id: 11,
        name: 'Tran Thi B',
        phone: '0907654321',
      },
    } as any,
  },
  {
    id: 3,
    amount: 100000,
    status: 'rejected',
    note: 'Lý do hoàn tiền không hợp lệ',
    booking: {
      id: 103,
      code: 'BK-HIST-03',
      total_amount: 100000,
      status: 'confirmed',
      user: {
        id: 12,
        name: 'Le Van C',
        phone: '0912345678',
      },
    } as any,
  },
]

describe('AdminRefundsPanel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders read-only historical refund records and does not provide approve or reject buttons', () => {
    const wrapper = mount(AdminRefundsPanel, {
      props: {
        refunds: mockRefunds,
      },
      global: {
        plugins: [i18n],
      },
    })

    // 1. Title and retired notice
    expect(wrapper.find('.panel-headline').text()).toContain('Lịch Sử Hoàn Tiền')
    expect(wrapper.find('.panel-sub').text()).toContain('chỉ xem')
    expect(wrapper.find('.panel-sub').text()).toContain('quy trình duyệt/từ chối hoàn tiền huỷ sân đã ngừng hỗ trợ')

    // 2. No interactive action buttons (approve / reject)
    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Duyệt')
    expect(wrapper.text()).not.toContain('Phê duyệt')
    expect(wrapper.text()).not.toContain('Approve')
    expect(wrapper.text()).not.toContain('Reject')

    // 3. Render 3 historical cards with appropriate labels
    const cards = wrapper.findAll('.refund-card')
    expect(cards.length).toBe(3)
    expect(cards[0]?.text()).toContain('BK-HIST-01')
    expect(cards[0]?.text()).toContain('Chờ hoàn tiền')

    expect(cards[1]?.text()).toContain('BK-HIST-02')
    expect(cards[1]?.text()).toContain('Đã hoàn tiền')

    expect(cards[2]?.text()).toContain('BK-HIST-03')
    expect(cards[2]?.text()).toContain('Đã từ chối')
  })

  it('renders empty state when there are no historical refunds', () => {
    const wrapper = mount(AdminRefundsPanel, {
      props: {
        refunds: [],
      },
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.empty-refunds-box').exists()).toBe(true)
    expect(wrapper.find('.empty-refunds-box').text()).toContain('Không có bản ghi hoàn tiền nào.')
  })
})
