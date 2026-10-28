import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, h, nextTick, type App } from 'vue'
import { i18n } from '@/i18n'
import DatePicker from '../forms/DatePicker.vue'

describe('DatePicker', () => {
  let app: App | null = null

  afterEach(() => {
    app?.unmount()
    app = null
    document.body.innerHTML = ''
    vi.restoreAllMocks()
  })

  async function openAt(left: number) {
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({ left } as DOMRect)
    const host = document.createElement('div')
    document.body.appendChild(host)
    app = createApp({ render: () => h(DatePicker, { modelValue: '2026-10-28' }) }).use(i18n)
    app.mount(host)
    host.querySelector<HTMLButtonElement>('.date-picker-trigger')!.click()
    await nextTick()
    await nextTick()
    return host.querySelector('.date-picker-popover')!
  }

  it('flips the popover to the left edge when right alignment overflows the viewport', async () => {
    expect((await openAt(-106)).classList.contains('align-start')).toBe(true)
  })

  it('keeps the right alignment when the popover fits', async () => {
    expect((await openAt(40)).classList.contains('align-start')).toBe(false)
  })
})
