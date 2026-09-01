import { describe, it, expect, beforeEach } from 'vitest'
import { useGroupSplitBill } from '../composables/useGroupSplitBill'

describe('useGroupSplitBill', () => {
  let split: ReturnType<typeof useGroupSplitBill>

  beforeEach(() => {
    split = useGroupSplitBill()
  })

  it('initializes with default booking and calculates equal split correctly', () => {
    expect(split.bookingInfo.value.bookingCode).toBe('ORD18')
    expect(split.members.value.length).toBe(4)
    expect(split.items.value.length).toBe(3)
    expect(split.totalBill.value).toBe(600000)

    split.calculateSplits()
    expect(split.members.value[0]?.amount).toBe(150000)
    expect(split.members.value[1]?.amount).toBe(150000)
    expect(split.members.value[2]?.amount).toBe(150000)
    expect(split.members.value[3]?.amount).toBe(150000)
    expect(split.allocatedTotal.value).toBe(600000)
    expect(split.allocationDiff.value).toBe(0)
  })

  it('calculates itemized split when items are assigned to specific members', () => {
    split.splitMode.value = 'itemized'
    // Assign drinks (item-2, 80k) only to member 1 and member 2
    if (split.items.value[1]) split.items.value[1].assignedMemberIds = [1, 2]
    // Assign shuttlecocks (item-3, 120k) only to member 3 and member 4
    if (split.items.value[2]) split.items.value[2].assignedMemberIds = [3, 4]

    split.calculateSplits()

    expect(split.members.value[0]?.amount).toBe(140000)
    expect(split.members.value[1]?.amount).toBe(140000)
    expect(split.members.value[2]?.amount).toBe(160000)
    expect(split.members.value[3]?.amount).toBe(160000)
    expect(split.allocatedTotal.value).toBe(600000)
  })

  it('calculates time-based split according to playing minutes', () => {
    split.splitMode.value = 'time'
    if (split.members.value[0]) split.members.value[0].timeMinutes = 120
    if (split.members.value[1]) split.members.value[1].timeMinutes = 120
    if (split.members.value[2]) split.members.value[2].timeMinutes = 120
    if (split.members.value[3]) split.members.value[3].timeMinutes = 60

    split.calculateSplits()

    const m1Amount = split.members.value[0]?.amount ?? 0
    const m4Amount = split.members.value[3]?.amount ?? 0
    expect(m1Amount).toBeGreaterThan(m4Amount)
  })

  it('allows adding a member and recalculates splits', () => {
    split.addMember('Trần Văn Bình', '0933 111 222')
    expect(split.members.value.length).toBe(5)
    expect(split.members.value[4]?.name).toBe('Trần Văn Bình')

    // 600k chia 5 = 120k mỗi người
    expect(split.members.value[0]?.amount).toBe(120000)
    expect(split.members.value[4]?.amount).toBe(120000)
  })

  it('allows adding and removing service items', () => {
    split.addServiceItem('Khăn lau Aura', 'service', 2, 25000)
    expect(split.items.value.length).toBe(4)
    expect(split.totalBill.value).toBe(650000)

    const addedItem = split.items.value[3]
    if (addedItem) {
      split.removeServiceItem(addedItem.id)
    }
    expect(split.items.value.length).toBe(3)
    expect(split.totalBill.value).toBe(600000)
  })

  it('marks member as paid and handles shortfall cover by host', async () => {
    expect(split.totalPaid.value).toBe(300000)
    expect(split.remainingAmount.value).toBe(300000)
    expect(split.isFullyPaid.value).toBe(false)

    // Mark member 3 as paid
    await split.markAsPaid(3)
    expect(split.members.value[2]?.paid).toBe(true)
    expect(split.totalPaid.value).toBe(450000)
    expect(split.remainingAmount.value).toBe(150000)

    // Host covers shortfall
    await split.coverShortfall()
    expect(split.members.value[3]?.paid).toBe(true)
    expect(split.isFullyPaid.value).toBe(true)
    expect(split.remainingAmount.value).toBe(0)
  })
})
