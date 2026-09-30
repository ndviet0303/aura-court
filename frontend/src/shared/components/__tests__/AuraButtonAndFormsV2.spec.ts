import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AuraButton from '../buttons/AuraButton.vue'

describe('AuraButton and Form Controls V2 Adaptation', () => {
  beforeEach(() => {
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
  })

  it('renders primary button with expected semantics and classes', () => {
    const wrapper = mount(AuraButton, {
      props: {
        variant: 'primary',
        size: 'md',
      },
      slots: {
        default: 'Submit Booking',
      },
    })

    const btn = wrapper.find('button')
    expect(btn.attributes('type')).toBe('button')
    expect(btn.classes()).toContain('aura-btn--primary')
    expect(btn.classes()).toContain('aura-btn--md')
    expect(wrapper.text()).toContain('Submit Booking')
  })

  it('handles loading state properly with spinner and disabled pointer', () => {
    const wrapper = mount(AuraButton, {
      props: {
        variant: 'primary',
        loading: true,
      },
      slots: {
        default: 'Processing...',
      },
    })

    const btn = wrapper.find('button')
    expect(btn.classes()).toContain('aura-btn--loading')
    expect(btn.classes()).toContain('aura-btn--disabled')
    expect(wrapper.find('.aura-btn__spinner').exists()).toBe(true)
  })

  it('handles disabled state properly and prevents click emission', async () => {
    const wrapper = mount(AuraButton, {
      props: {
        disabled: true,
      },
      slots: {
        default: 'Disabled Action',
      },
    })

    const btn = wrapper.find('button')
    expect(btn.attributes('disabled')).toBeDefined()
    expect(btn.classes()).toContain('aura-btn--disabled')

    await btn.trigger('click')
    expect(wrapper.emitted('click')).toBeFalsy()
  })

  it('renders link button with RouterLink when `to` prop is provided', () => {
    const wrapper = mount(AuraButton, {
      props: {
        to: '/courts',
        variant: 'secondary',
      },
      slots: {
        default: 'Explore Courts',
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a :href="to"><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    const link = wrapper.find('a')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/courts')
    expect(link.classes()).toContain('aura-btn--secondary')
  })
})
