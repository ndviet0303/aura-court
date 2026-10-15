import { describe, it, expect, beforeEach, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AdminUsersView from '../views/AdminUsersView.vue'
import { i18n } from '@/i18n'
import { adminUserApi } from '../api/adminUserApi'

vi.mock('../api/adminUserApi', () => ({
  adminUserApi: { list: vi.fn(), lock: vi.fn(), unlock: vi.fn() },
}))

const page = (users: Record<string, unknown>[]) => ({
  data: users,
  current_page: 1,
  last_page: 1,
  total: users.length,
})

const active = {
  id: 7,
  name: 'Lan',
  email: 'lan@example.com',
  role: 'customer',
  status: 'active',
  created_at: '2026-10-01T00:00:00Z',
}
const locked = {
  id: 8,
  name: 'Minh',
  email: 'minh@example.com',
  role: 'customer',
  status: 'locked',
  created_at: null,
}

const mountView = async () => {
  const wrapper = mount(AdminUsersView, { global: { plugins: [i18n] } })
  await flushPromises()
  return wrapper
}

describe('AdminUsersView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(adminUserApi.list).mockResolvedValue(page([{ ...active }, { ...locked }]))
  })

  it('locks an account only after a reason of at least 10 characters', async () => {
    vi.mocked(adminUserApi.lock).mockResolvedValue({
      message: 'Locked',
      user: { ...active, status: 'locked' },
    })
    const wrapper = await mountView()
    expect(wrapper.findAll('[data-testid="user-row"]')).toHaveLength(2)

    await wrapper.get('[data-testid="toggle-lock-7"]').trigger('click')
    const confirm = () => wrapper.get('[data-testid="confirm-lock"]')
    await wrapper.get('[data-testid="lock-reason"]').setValue('too short')
    expect(confirm().attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('9/1000')

    await wrapper.get('[data-testid="lock-reason"]').setValue('  Spam bookings reported  ')
    expect(confirm().attributes('disabled')).toBeUndefined()
    await confirm().trigger('click')
    await flushPromises()

    expect(adminUserApi.lock).toHaveBeenCalledWith(7, 'Spam bookings reported')
    expect(wrapper.find('[data-testid="confirm-lock"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="toggle-lock-7"]').text()).toBe(
      i18n.global.t('admin.users.unlockBtn'),
    )
    expect(wrapper.text()).toContain('Locked')
  })

  it('unlocks a locked account and keeps the modal open when the request fails', async () => {
    vi.mocked(adminUserApi.unlock).mockRejectedValue(new Error('boom'))
    const wrapper = await mountView()

    await wrapper.get('[data-testid="toggle-lock-8"]').trigger('click')
    await wrapper.get('[data-testid="lock-reason"]').setValue('Appeal accepted by support')
    await wrapper.get('[data-testid="confirm-lock"]').trigger('click')
    await flushPromises()

    expect(adminUserApi.unlock).toHaveBeenCalledWith(8, 'Appeal accepted by support')
    expect(adminUserApi.lock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="confirm-lock"]').exists()).toBe(true)
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  })

  it('searches and filters from the first page', async () => {
    const wrapper = await mountView()
    await wrapper.get('[data-testid="user-search"]').setValue(' lan ')
    await wrapper.get('[data-testid="user-status-filter"]').setValue('locked')
    await flushPromises()

    expect(adminUserApi.list).toHaveBeenLastCalledWith({ search: 'lan', status: 'locked', page: 1 })
  })
})
