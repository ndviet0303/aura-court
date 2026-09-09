import { afterEach, expect, it } from 'vitest'

afterEach(() => {
  localStorage.clear()
  sessionStorage.clear()
})

it('provides browser storage through both global and window access', () => {
  localStorage.setItem('test-key', 'local')
  sessionStorage.setItem('test-key', 'session')
  expect(window.localStorage.getItem('test-key')).toBe('local')
  expect(window.sessionStorage.getItem('test-key')).toBe('session')
  localStorage.removeItem('test-key')
  expect(localStorage.getItem('test-key')).toBeNull()
})
