import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AuraUiVersionSwitch from '../controls/AuraUiVersionSwitch.vue'
import { i18n } from '@/i18n'

describe('AuraUiVersionSwitch', () => {
  function createWrapper(props = {}) {
    return mount(AuraUiVersionSwitch, {
      props,
      global: {
        plugins: [i18n],
      },
    })
  }

  it('renders with type="button" and role="switch" by default', () => {
    const wrapper = createWrapper()
    const button = wrapper.find('button')
    expect(button.attributes('type')).toBe('button')
    expect(button.attributes('role')).toBe('switch')
    expect(button.attributes('aria-checked')).toBe('false')
    expect(wrapper.text()).toContain('V1')
  })

  it('renders checked state when modelValue is true', () => {
    const wrapper = createWrapper({ modelValue: true })
    const button = wrapper.find('button')
    expect(button.attributes('aria-checked')).toBe('true')
    expect(button.classes()).toContain('is-checked')
    expect(wrapper.text()).toContain('V2')
  })

  it('emits update:modelValue and change events when clicked', async () => {
    const wrapper = createWrapper({ modelValue: false })
    await wrapper.find('button').trigger('click')

    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')![0]).toEqual([true])

    expect(wrapper.emitted('change')).toBeTruthy()
    expect(wrapper.emitted('change')![0]).toEqual([true])
  })

  it('supports keyboard navigation with Enter and Space', async () => {
    const wrapper = createWrapper({ modelValue: false })
    const button = wrapper.find('button')

    await button.trigger('keydown', { key: ' ' })
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])

    await button.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')?.[1]).toEqual([true])
  })

  it('does not emit events when disabled', async () => {
    const wrapper = createWrapper({ modelValue: false, disabled: true })
    const button = wrapper.find('button')

    expect(button.attributes('disabled')).toBeDefined()
    expect(button.classes()).toContain('is-disabled')

    await button.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeFalsy()

    await button.trigger('keydown', { key: ' ' })
    expect(wrapper.emitted('update:modelValue')).toBeFalsy()
  })

  it('does not submit when placed inside an HTML form', async () => {
    const submitSpy = vi.fn((e: Event) => e.preventDefault())
    const formWrapper = mount(
      {
        components: { AuraUiVersionSwitch },
        template: `
          <form @submit="onSubmit">
            <AuraUiVersionSwitch />
          </form>
        `,
        methods: {
          onSubmit: submitSpy,
        },
      },
      {
        global: {
          plugins: [i18n],
        },
      }
    )

    const button = formWrapper.find('button')
    expect(button.attributes('type')).toBe('button')

    await button.trigger('click')
    expect(submitSpy).not.toHaveBeenCalled()
  })

  it('supports role="menuitemcheckbox" for use inside menus', () => {
    const wrapper = createWrapper({ role: 'menuitemcheckbox', modelValue: true })
    const button = wrapper.find('button')
    expect(button.attributes('role')).toBe('menuitemcheckbox')
    expect(button.attributes('aria-checked')).toBe('true')
  })

  it('renders custom label when provided, or defaults to localized label', () => {
    const customWrapper = createWrapper({ label: 'Custom Switch Label' })
    expect(customWrapper.text()).toContain('Custom Switch Label')

    const defaultWrapper = createWrapper()
    expect(defaultWrapper.text()).toContain('Giao diện Erabook V2')
  })
})
