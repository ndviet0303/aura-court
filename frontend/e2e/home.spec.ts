import { expect, test } from '@playwright/test'

test('home page exposes the booking entry point', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { name: /Nơi chính xác/ })).toBeVisible()
  await expect(page.getByRole('link', { name: /Đặt sân/ }).first()).toBeVisible()
})
