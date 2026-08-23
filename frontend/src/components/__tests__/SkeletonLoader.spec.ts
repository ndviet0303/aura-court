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
})
