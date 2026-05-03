import { test, expect } from '@playwright/test';

test.describe('Public pages — shadow-depth design', () => {
    test('home page renders with depth cards and correct styling', async ({ page }) => {
        await page.goto('/');

        // Header
        const header = page.locator('header');
        await expect(header).toBeVisible();
        await expect(header).toHaveClass(/backdrop-blur/);

        // Hero
        await expect(page.locator('h1')).toContainText('AI Marketing');

        // Pricing cards with depth
        const pricingCards = page.locator('section').filter({ hasText: 'Simple, transparent pricing' }).locator('.rounded-card');
        await expect(pricingCards).toHaveCount(2);

        // Pro card has accent border
        const proCard = pricingCards.filter({ hasText: 'Pro' });
        await expect(proCard).toHaveClass(/border-accent/);
        await expect(proCard).toHaveClass(/shadow-elevation-2/);

        // Feature cards with hover lift
        const featureCards = page.locator('article.rounded-card');
        await expect(featureCards).toHaveCount(3);
        await expect(featureCards.first()).toHaveClass(/shadow-elevation-1/);
        await expect(featureCards.first()).toHaveClass(/hover:shadow-elevation-2/);

        // CTA buttons with shadow
        const cta = page.locator('a[href="/register"]').filter({ hasText: 'Start for free' });
        await expect(cta).toHaveClass(/shadow-elevation-2/);
    });

    test('login page has styled form card', async ({ page }) => {
        await page.goto('/login');

        const card = page.locator('.rounded-card').first();
        await expect(card).toBeVisible();
        await expect(card).toHaveClass(/shadow-elevation-2/);
        await expect(card).toHaveClass(/border-line\/60/);

        // Inputs with focus ring
        const emailInput = page.locator('input#email');
        await expect(emailInput).toHaveClass(/focus:ring-accent\/20/);

        // Submit button with shadow
        const submit = page.locator('button[type="submit"]');
        await expect(submit).toHaveClass(/shadow-elevation-1/);
    });

    test('register page has styled form card', async ({ page }) => {
        await page.goto('/register');

        const card = page.locator('.rounded-card').first();
        await expect(card).toBeVisible();
        await expect(card).toHaveClass(/shadow-elevation-2/);
    });
});
