import { ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const operationsApi = vi.hoisted(() => ({
  show: vi.fn(),
  adjustments: vi.fn(),
  command: vi.fn(),
  adjust: vi.fn(),
}))

vi.mock('vue-i18n', async (importOriginal) => ({
  ...(await importOriginal<typeof import('vue-i18n')>()),
  useI18n: () => ({ t: (key: string) => key }),
}))
vi.mock('@/features/admin/composables/useLifecycleCommands', () => ({
  useOwnerCheck: () => () => false,
}))
vi.mock('../../api/operationsApi', () => ({ operationsApi }))

import { useAttendanceDesk } from '../useAttendanceDesk'

// A transferred booking keeps its order code but checks in only by its new check-in code (D5).
const transferred = { id: 5, code: 'BK-OLD', check_in_code: 'CHKNEWCODE12345' }

describe('useAttendanceDesk D5 check-in code', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    operationsApi.show.mockResolvedValue({ reservation: transferred, payment: null })
    operationsApi.command.mockResolvedValue({ reservation: transferred })
  })

  it('reopens a transferred booking by its check-in code after a command', async () => {
    const onChanged = vi.fn()
    const desk = useAttendanceDesk(ref(3), onChanged)
    await desk.open(transferred.check_in_code)

    await desk.run('check-in', desk.selected.value!.check_in_code)

    expect(operationsApi.command).toHaveBeenCalledWith(3, 'check-in', 'CHKNEWCODE12345')
    expect(operationsApi.show).toHaveBeenLastCalledWith(3, 'CHKNEWCODE12345')
    expect(operationsApi.show).not.toHaveBeenCalledWith(3, 'BK-OLD')
    expect(onChanged).toHaveBeenCalled()
  })
})
