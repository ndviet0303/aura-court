import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const courtApi = vi.hoisted(() => ({ getById: vi.fn() }))
const route = vi.hoisted(() => ({ params: { id: '9' } }))
const store = vi.hoisted(() => ({ courts: [{ id: 1, code: 'A1' }] }))

vi.mock('vue-router', () => ({ useRoute: () => reactive(route) }))
vi.mock('@/stores/booking', () => ({ useBookingStore: () => store }))
vi.mock('../../api/courtApi', () => ({ courtApi }))

import { useCourtDetail } from '../useCourtDetail'

const flush = () => new Promise((resolve) => setTimeout(resolve))

describe('useCourtDetail', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows an error, not another court, when the requested court fails to load', async () => {
    courtApi.getById.mockRejectedValue(new Error('boom'))
    const detail = useCourtDetail()
    await flush()

    expect(courtApi.getById).toHaveBeenCalledWith('9')
    expect(detail.court.value).toBeNull()
    expect(detail.error.value).not.toBe('')
  })

  it('loads the requested court by id', async () => {
    courtApi.getById.mockResolvedValue({ id: 9, code: 'B2' })
    const detail = useCourtDetail()
    await flush()

    expect(detail.court.value).toEqual({ id: 9, code: 'B2' })
    expect(detail.error.value).toBe('')
  })
})
