import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import NavbarSkeleton from '../NavbarSkeleton.vue'

describe('NavbarSkeleton', () => {
  it('renders with default fixed layout', () => {
    const wrapper = mount(NavbarSkeleton)
    expect(wrapper.find('.navbar-skeleton').exists()).toBe(true)
    expect(wrapper.classes()).toContain('is-fixed')
    expect(wrapper.find('.skeleton-brand').exists()).toBe(true)
    expect(wrapper.find('.skeleton-nav-pills').exists()).toBe(true)
    expect(wrapper.find('.skeleton-nav-right').exists()).toBe(true)
  })

  it('renders neutral skeleton nav pills without default active state', () => {
    const wrapper = mount(NavbarSkeleton)
    const pills = wrapper.findAll('.skel-pill')
    expect(pills.length).toBe(4)
    pills.forEach((pill) => {
      expect(pill.classes()).not.toContain('active')
      expect(pill.classes()).toContain('skel-item')
    })
  })

  it('renders with fixed=false', () => {
    const wrapper = mount(NavbarSkeleton, {
      props: {
        fixed: false,
      },
    })
    expect(wrapper.classes()).not.toContain('is-fixed')
  })
})
