import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, h, nextTick, type App } from 'vue'
import SelectDropdown from '../forms/SelectDropdown.vue'

describe('SelectDropdown', () => {
  let app: App | null = null

  afterEach(() => {
    app?.unmount()
    app = null
    document.body.innerHTML = ''
    vi.restoreAllMocks()
  })

  it('scrolls the opened menu into view so modal bodies do not clip it', async () => {
    const scrollIntoView = vi.fn()
    Element.prototype.scrollIntoView = scrollIntoView
    const host = document.createElement('div')
    document.body.appendChild(host)
    app = createApp({ render: () => h(SelectDropdown, { options: ['A', 'B', 'C'] }) })
    app.mount(host)

    host.querySelector<HTMLButtonElement>('.custom-dropdown-trigger')!.click()
    await nextTick()
    await nextTick()

    expect(host.querySelectorAll('[role=option]')).toHaveLength(3)
    expect(scrollIntoView).toHaveBeenCalledWith({ block: 'nearest' })
  })
})
