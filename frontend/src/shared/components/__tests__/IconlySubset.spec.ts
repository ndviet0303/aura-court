import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import {
  IconHome,
  IconCalendar,
  IconSearch,
  IconUser,
  IconWallet,
  IconFilter,
  IconCheck,
  IconClose,
  IconArrowLeft,
  IconArrowRight,
  IconLocation,
  iconProvenanceMap,
} from '../icons'

describe('Iconly Subset & SVG Safety', () => {
  const iconComponents = [
    { name: 'IconHome', component: IconHome, key: 'home' },
    { name: 'IconCalendar', component: IconCalendar, key: 'calendar' },
    { name: 'IconSearch', component: IconSearch, key: 'search' },
    { name: 'IconUser', component: IconUser, key: 'user' },
    { name: 'IconWallet', component: IconWallet, key: 'wallet' },
    { name: 'IconFilter', component: IconFilter, key: 'filter' },
    { name: 'IconCheck', component: IconCheck, key: 'check' },
    { name: 'IconClose', component: IconClose, key: 'close' },
    { name: 'IconArrowLeft', component: IconArrowLeft, key: 'arrowLeft' },
    { name: 'IconArrowRight', component: IconArrowRight, key: 'arrowRight' },
    { name: 'IconLocation', component: IconLocation, key: 'location' },
  ]

  it('renders every icon safely without scripts, event handlers or external URLs', () => {
    for (const { component } of iconComponents) {
      const wrapper = mount(component)
      const html = wrapper.html()

      // SVG safety checks
      expect(html).not.toContain('<script')
      expect(html).not.toMatch(/on[a-z]+=/i)
      expect(html).not.toMatch(/href=["']https?:\/\//i)
      expect(html).not.toMatch(/src=["']https?:\/\//i)
      expect(html).not.toMatch(/url\(["']?https?:\/\//i)

      const svg = wrapper.find('svg')
      expect(svg.exists()).toBe(true)
      expect(svg.attributes('width')).toBe('24')
      expect(svg.attributes('height')).toBe('24')
      expect(svg.attributes('role')).toBe('img')
    }
  })

  it('supports custom size and color props', () => {
    const wrapper = mount(IconHome, {
      props: {
        size: 32,
        color: '#F89300',
      },
    })

    const svg = wrapper.find('svg')
    expect(svg.attributes('width')).toBe('32')
    expect(svg.attributes('height')).toBe('32')
    expect(svg.attributes('fill')).toBe('#F89300')
  })

  it('renders multiple instances without ID collisions', () => {
    const parentWrapper = mount({
      components: { IconHome, IconCalendar, IconCheck },
      template: `
        <div>
          <IconHome class="first" />
          <IconHome class="second" />
          <IconCalendar class="third" />
          <IconCheck class="fourth" />
        </div>
      `,
    })

    const svgs = parentWrapper.findAll('svg')
    expect(svgs).toHaveLength(4)

    // Collect all element IDs inside the container
    const ids: string[] = []
    parentWrapper.element.querySelectorAll('[id]').forEach((el: Element) => {
      ids.push(el.id)
    })

    // Asserts no duplicate IDs across all instances
    const uniqueIds = new Set(ids)
    expect(uniqueIds.size).toBe(ids.length)
  })

  it('records complete provenance metadata for each registered icon', () => {
    for (const { key } of iconComponents) {
      const metadata = iconProvenanceMap[key]
      expect(metadata).toBeDefined()
      expect(metadata?.nodeId).toBeTruthy()
      expect(metadata?.sourceFile).toBeTruthy()
      expect(metadata?.description).toBeTruthy()
    }
  })
})
