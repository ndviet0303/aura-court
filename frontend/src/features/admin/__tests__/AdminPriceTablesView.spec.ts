import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import AdminPriceTablesView from '../views/AdminPriceTablesView.vue'
import PriceTableLinkModal from '../components/price-tables/PriceTableLinkModal.vue'
import PriceTableCreateModal from '../components/price-tables/PriceTableCreateModal.vue'
import { CurrencyInput, DateTimePicker } from '@/shared/components'
import { api } from '@/core/api/client'
import { formatCurrency, i18n, setAppLocale } from '@/i18n'
import type { PriceTable, PriceTableDetail } from '../types/priceTable'

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn<() => Promise<unknown>>(),
    post: vi.fn<() => Promise<unknown>>(),
    patch: vi.fn<() => Promise<unknown>>(),
    delete: vi.fn<() => Promise<unknown>>(),
  },
  errorMessage: (_err: unknown, fallback?: string) => fallback || 'Có lỗi xảy ra',
}))

const t = (key: string, values: Record<string, unknown> = {}) => i18n.global.t(key, values)

const table: PriceTable = {
  id: 5,
  venue_id: 10,
  name: 'Giờ thường 2026',
  kind: 'normal',
  status: 'active',
  links: [{ court_id: 1, court_name: 'Sân 1', starts_at: '2026-10-01T00:00:00Z', ends_at: null }],
}

const detail: PriceTableDetail = {
  ...table,
  versions: [
    {
      id: 7,
      published_at: '2026-09-30T03:00:00Z',
      effective_at: '2026-10-01T00:00:00Z',
      intervals: [
        {
          weekdays: [1, 2, 3, 4, 5],
          dates: null,
          start: '05:00',
          end: '17:00',
          price_per_hour: 120000,
        },
        { weekdays: [6, 7], dates: null, start: '05:00', end: '23:59', price_per_hour: 150000 },
      ],
    },
  ],
}

const coverageError = {
  message: 'Bảng giá chưa phủ kín giờ mở của sân.',
  status: 422,
  code: 'PRICE_COVERAGE_INCOMPLETE',
  missing_items: [
    { court_id: 2, court_name: 'Sân 2', weekday: 7, date: null, start: '05:00', end: '23:59' },
    {
      court_id: 2,
      court_name: 'Sân 2',
      weekday: null,
      date: '2027-01-01',
      start: '00:00',
      end: '05:00',
    },
  ],
}

let tables: PriceTable[] = [table]
let wrapper: VueWrapper | null = null

function stubGet() {
  vi.mocked(api.get).mockImplementation(((url: string) => {
    if (url === '/admin/venues') return Promise.resolve([{ id: 10, name: 'Cơ sở Cầu Giấy' }])
    if (url === '/admin/venues/10/price-tables') return Promise.resolve({ data: tables })
    if (url === '/admin/courts?venue_id=10') {
      return Promise.resolve([
        { id: 1, name: 'Sân 1' },
        { id: 2, name: 'Sân 2' },
      ])
    }
    if (url === '/admin/price-tables/5') return Promise.resolve({ data: detail })
    return Promise.reject(new Error(`unexpected GET ${url}`))
  }) as never)
}

async function mountView() {
  const container = document.createElement('div')
  document.body.appendChild(container)
  wrapper = mount(AdminPriceTablesView, { attachTo: container, global: { plugins: [i18n] } })
  await flushPromises()
  return wrapper
}

async function openDetail(view: VueWrapper) {
  await view.find('.price-table-item').trigger('click')
  await flushPromises()
}

function buttonByText(view: VueWrapper, text: string) {
  const button = view.findAll('button').find((b) => b.text().includes(text))
  if (!button) throw new Error(`no button "${text}"`)
  return button
}

describe('AdminPriceTablesView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setAppLocale('vi')
    tables = [table]
    stubGet()
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    document.body.innerHTML = ''
    setAppLocale('vi')
  })

  it('lists the first venue tables and shows the chosen table in detail', async () => {
    const view = await mountView()

    expect(api.get).toHaveBeenCalledWith('/admin/venues/10/price-tables')
    const items = view.findAll('.price-table-item')
    expect(items).toHaveLength(1)
    expect(items[0]!.text()).toContain('Giờ thường 2026')

    await openDetail(view)

    const text = view.find('.price-table-detail').text()
    expect(text).toContain(t('admin.priceTables.kindNormal'))
    expect(text).toContain(t('admin.priceTables.statusActive'))
    expect(text).toContain('Sân 1')
    expect(text).toContain(t('admin.priceTables.versionLabel', { id: 7 }))
    expect(text).toContain('05:00–17:00')
    expect(text).toContain(formatCurrency(120000))
    expect(text).toContain(formatCurrency(150000))
  })

  it('shows the empty state when the venue has no price table', async () => {
    tables = []
    const view = await mountView()

    expect(view.find('.price-table-empty').text()).toContain(t('admin.priceTables.empty'))
  })

  it('creates a table with the chosen weekdays and a venue-clock effective time', async () => {
    vi.mocked(api.post).mockResolvedValue({
      data: { ...detail, id: 6, name: 'Cuối tuần' },
    } as never)
    const view = await mountView()

    await buttonByText(view, t('admin.priceTables.createBtn')).trigger('click')
    const modal = view.findComponent(PriceTableCreateModal)
    await modal.find('input[name="name"]').setValue('  Cuối tuần ')
    modal.findComponent(DateTimePicker).vm.$emit('update:modelValue', '2026-11-01T06:00')
    modal.findComponent(CurrencyInput).vm.$emit('update:modelValue', 150000)
    const chips = modal.findAll('.day-chip')
    await chips[chips.length - 1]!.trigger('click')
    await modal.find('form').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/admin/venues/10/price-tables', {
      name: 'Cuối tuần',
      kind: 'normal',
      effective_at: '2026-11-01T06:00:00+07:00',
      intervals: [
        { weekdays: [1, 2, 3, 4, 5, 6], start: '00:00', end: '23:59', price_per_hour: 150000 },
      ],
    })
    expect(view.find('form[data-testid="price-table-create"]').exists()).toBe(false)
    expect(view.text()).toContain(t('admin.priceTables.createdNotice', { name: 'Cuối tuần' }))
  })

  it('keeps the create form open with inline 422 errors', async () => {
    vi.mocked(api.post).mockRejectedValue({
      message: 'Dữ liệu không hợp lệ.',
      status: 422,
      code: 'VALIDATION_ERROR',
      errors: {
        name: ['Tên bảng giá đã tồn tại.'],
        'intervals.0.end': ['Giờ kết thúc phải sau giờ bắt đầu.'],
      },
    })
    const view = await mountView()

    await buttonByText(view, t('admin.priceTables.createBtn')).trigger('click')
    await view.find('input[name="name"]').setValue('Giờ thường 2026')
    await view.find('form[data-testid="price-table-create"]').trigger('submit')
    await flushPromises()

    const form = view.find('form[data-testid="price-table-create"]')
    expect(form.exists()).toBe(true)
    expect(form.text()).toContain('Tên bảng giá đã tồn tại.')
    expect(view.find('fieldset[data-testid="price-interval"]').text()).toContain(
      'Giờ kết thúc phải sau giờ bắt đầu.',
    )
    expect((view.find('input[name="name"]').element as HTMLInputElement).value).toBe(
      'Giờ thường 2026',
    )
    expect(document.body.textContent).not.toContain(t('errorModal.defaultTitle'))
  })

  it('applies the table to courts from a switch time', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: detail } as never)
    const view = await mountView()
    await openDetail(view)

    await buttonByText(view, t('admin.priceTables.applyBtn')).trigger('click')
    const modal = view.findComponent(PriceTableLinkModal)
    await modal.find('input[type="checkbox"][value="2"]').setValue(true)
    modal.findComponent(DateTimePicker).vm.$emit('update:modelValue', '2026-11-01T00:00')
    await modal.find('form').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/admin/price-tables/5/links', {
      court_ids: [2],
      starts_at: '2026-11-01T00:00:00+07:00',
    })
    expect(view.findComponent(PriceTableLinkModal).exists()).toBe(false)
    expect(view.text()).toContain(t('admin.priceTables.appliedNotice'))
  })

  it('ends a court link now when no end time is chosen', async () => {
    vi.mocked(api.post).mockResolvedValue({
      data: { court_id: 1, kind: 'normal', ends_at: '2026-10-21T03:00:00Z' },
    } as never)
    const view = await mountView()
    await openDetail(view)

    await view.find('.link-end-btn').trigger('click')
    await view.findComponent(PriceTableLinkModal).find('form').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/admin/courts/1/price-table-links/end', {
      kind: 'normal',
      ends_at: undefined,
    })
    expect(view.text()).toContain(t('admin.priceTables.endedNotice'))
  })

  it('retires the table after confirmation', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.mocked(api.post).mockResolvedValue({ data: { ...detail, status: 'retired' } } as never)
    const view = await mountView()
    await openDetail(view)

    await buttonByText(view, t('admin.priceTables.retireBtn')).trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/admin/price-tables/5/retire')
    const text = view.find('.price-table-detail').text()
    expect(text).toContain(t('admin.priceTables.statusRetired'))
    expect(text).not.toContain(t('admin.priceTables.applyBtn'))
  })

  it.each([
    ['vi', 'Sân 2 · Chủ Nhật · 05:00–23:59'],
    ['en', 'Sân 2 · Sunday · 05:00–23:59'],
  ] as const)(
    'lists coverage gaps in the error dialog and keeps the chosen courts (%s)',
    async (locale, sundayGap) => {
      setAppLocale(locale)
      vi.mocked(api.post).mockRejectedValue(coverageError)
      const view = await mountView()
      await openDetail(view)

      await buttonByText(view, t('admin.priceTables.applyBtn')).trigger('click')
      await view.find('input[type="checkbox"][value="2"]').setValue(true)
      await view.findComponent(PriceTableLinkModal).find('form').trigger('submit')
      await flushPromises()

      const body = document.body.textContent ?? ''
      expect(body).toContain(t('admin.priceTables.coverage.title'))
      expect(body).toContain(t('admin.priceTables.coverage.message'))
      expect(body).toContain(sundayGap)
      const holiday = i18n.global.d(new Date('2027-01-01T00:00:00+07:00'), 'date')
      expect(body).toContain(`Sân 2 · ${holiday} · 00:00–05:00`)
      expect(body).toContain('PRICE_COVERAGE_INCOMPLETE')

      const modal = view.findComponent(PriceTableLinkModal)
      expect(modal.exists()).toBe(true)
      const checkbox = modal.find('input[type="checkbox"][value="2"]').element as HTMLInputElement
      expect(checkbox.checked).toBe(true)
    },
  )
})
