import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AuraButton from '../AuraButton.vue'

describe('AuraButton.vue', () => {
  it('renders default button with text slot', () => {
    const wrapper = mount(AuraButton, {
      slots: {
        default: 'Thêm sân mới',
      },
    })
    const btn = wrapper.find('button')
    expect(btn.exists()).toBe(true)
    expect(btn.text()).toContain('Thêm sân mới')
    expect(btn.classes()).toContain('aura-btn')
    expect(btn.classes()).toContain('aura-btn--primary')
    expect(btn.classes()).toContain('aura-btn--md')
  })

  it('renders with icon and variant', () => {
    const wrapper = mount(AuraButton, {
      props: {
        variant: 'danger',
        size: 'pill',
        icon: 'delete',
      },
      slots: {
        default: 'Xóa',
      },
    })
    const btn = wrapper.find('button')
    expect(btn.classes()).toContain('aura-btn--danger')
    expect(btn.classes()).toContain('aura-btn--pill')
    expect(btn.find('.aura-btn__icon--left').text()).toBe('delete')
  })

  it('handles loading state with spinner and disabled', () => {
    const wrapper = mount(AuraButton, {
      props: {
        loading: true,
      },
      slots: {
        default: 'Đang lưu...',
      },
    })
    const btn = wrapper.find('button')
    expect(btn.classes()).toContain('aura-btn--loading')
    expect(btn.attributes('disabled')).toBeDefined()
    expect(btn.find('.aura-btn__spinner').text()).toBe('sync')
  })
})
