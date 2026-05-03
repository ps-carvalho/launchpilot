import { test, expect } from '@playwright/test';

test.describe('Dashboard UI — shadow-depth design', () => {
    test.beforeEach(async ({ page }) => {
        // Register a test user
        await page.goto('/register');
        await page.fill('input#name', 'E2E Test User');
        await page.fill('input#email', `e2e-test-${Date.now()}@example.com`);
        await page.fill('input#password', 'password123');
        await page.click('button[type="submit"]');

        // Wait for redirect (either onboarding or dashboard)
        await page.waitForURL(/\/(dashboard|onboarding)/, { timeout: 10000 });
    });

    test('onboarding page has styled form card', async ({ page }) => {
        // We should be on onboarding after registration
        await expect(page).toHaveURL(/\/onboarding/);

        // Form card (the white card containing the form)
        const card = page.locator('.rounded-xl').filter({ hasText: 'Add your website' }).first();
        await expect(card).toBeVisible();
        await expect(card).toHaveClass(/shadow-elevation-1/);

        // Step indicator
        const step1 = page.locator('.rounded-full').filter({ hasText: '1' }).first();
        await expect(step1).toBeVisible();
    });

    test('campaigns page has tab pills and card grid', async ({ page }) => {
        await page.goto('/campaigns');
        await page.waitForLoadState('networkidle');

        // AppShell header
        const header = page.locator('header');
        await expect(header).toBeVisible();
        await expect(header).toHaveClass(/sticky/);
        await expect(header).toHaveClass(/backdrop-blur/);

        // Tab pills
        const tabs = page.locator('button').filter({ hasText: 'Active' });
        await expect(tabs).toBeVisible();

        // Empty state or cards
        const emptyState = page.locator('text=No campaigns yet');
        const cards = page.locator('.grid >> .rounded-xl').first();
        await expect(emptyState.or(cards)).toBeVisible();
    });

    test('campaign create page has styled form', async ({ page }) => {
        await page.goto('/campaigns/create');
        await page.waitForLoadState('networkidle');

        // Type selector cards
        const typeCards = page.locator('button[type="button"]').filter({ hasText: 'One-off Launch' });
        await expect(typeCards).toBeVisible();
        await expect(typeCards).toHaveClass(/shadow-elevation-1/);

        // Channel pills
        const channelPill = page.locator('button[type="button"]').filter({ hasText: 'LinkedIn' });
        await expect(channelPill).toBeVisible();
    });

    test('knowledge base page has upload area with depth', async ({ page }) => {
        await page.goto('/knowledge-base');
        await page.waitForLoadState('networkidle');

        // AppShell header
        const header = page.locator('header');
        await expect(header).toBeVisible();

        const uploadArea = page.locator('.rounded-xl').filter({ hasText: 'Drop a file here' });
        await expect(uploadArea).toBeVisible();
        await expect(uploadArea).toHaveClass(/shadow-elevation-1/);
    });

    test('settings page has styled cards', async ({ page }) => {
        await page.goto('/settings');
        await page.waitForLoadState('networkidle');

        // AppShell header
        const header = page.locator('header');
        await expect(header).toBeVisible();

        const settingsCards = page.locator('.rounded-xl').filter({ hasText: 'Plan' });
        await expect(settingsCards).toBeVisible();
        await expect(settingsCards).toHaveClass(/shadow-elevation-1/);
    });
});
