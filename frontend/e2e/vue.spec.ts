import { test, expect } from '@playwright/test'

// See here how to get started:
// https://playwright.dev/docs/intro
test('renders the Aura Court homepage', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveTitle('Aura Court · Đặt sân cầu lông')
  await expect(page.getByRole('heading', { name: 'Where Precision Meets Performance.' })).toBeVisible()
  await expect(page.getByRole('link', { name: /Book a Court/ })).toHaveAttribute('href', '/courts')
})

test('loads the court list from the booking API', async ({ page }) => {
  await page.goto('/courts')
  await expect(page.getByRole('heading', { name: 'The Court List' })).toBeVisible()
  await expect(page.getByRole('link', { name: /Sân 1/ })).toBeVisible()
})
