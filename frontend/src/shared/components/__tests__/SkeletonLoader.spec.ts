import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SkeletonLoader from '../SkeletonLoader.vue'

describe('SkeletonLoader', () => {
  it('renders basic text skeleton', () => {
    const wrapper = mount(SkeletonLoader, {
      props: {
        type: 'text',
      },
    })
    expect(wrapper.find('.type-text').exists()).toBe(true)
  })

  it('renders court-card skeleton with count', () => {
    const wrapper = mount(SkeletonLoader, {
      props: {
        type: 'court-card',
        count: 3,
      },
    })
    const cards = wrapper.findAll('.skeleton-court-card')
    expect(cards).toHaveLength(3)
  })

  it('renders slot skeleton with count', () => {
    const wrapper = mount(SkeletonLoader, {
      props: {
        type: 'slot',
        count: 5,
      },
    })
    const slots = wrapper.findAll('.skeleton-slot')
    expect(slots).toHaveLength(5)
    expect(wrapper.find('.skeleton-slot-time').exists()).toBe(true)
    expect(wrapper.find('.skeleton-slot-price').exists()).toBe(true)
  })

  it('renders court-detail skeleton', () => {
    const wrapper = mount(SkeletonLoader, {
      props: {
        type: 'court-detail',
      },
    })
    expect(wrapper.find('.skeleton-court-detail').exists()).toBe(true)
    expect(wrapper.find('.skeleton-hero-title').exists()).toBe(true)
  })
})
