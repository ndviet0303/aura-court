import { expect, test } from '@playwright/test'

test('Trang chủ hiển thị điểm vào đặt sân thể thao @smoke', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('heading', { name: /Nơi chính xác/ })).toBeVisible()
  await expect(page.getByRole('link', { name: /Đặt sân/ }).first()).toBeVisible()
})
