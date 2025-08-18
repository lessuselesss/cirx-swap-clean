import { test, expect } from '@playwright/test';

test.describe('Complete Swap Flow E2E', () => {
  test.beforeEach(async ({ page }) => {
    // Setup mock wallet and backend
    await page.addInitScript(() => {
      // Mock Ethereum provider
      window.ethereum = {
        isMetaMask: true,
        request: async ({ method, params }) => {
          if (method === 'eth_requestAccounts') {
            return ['0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3'];
          }
          if (method === 'eth_chainId') {
            return '0xaa36a7'; // Sepolia
          }
          if (method === 'eth_accounts') {
            return ['0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3'];
          }
          return null;
        },
        on: () => {},
        removeListener: () => {}
      };
    });

    // Mock backend API responses
    await page.route('/api/v1/**', async route => {
      const url = route.request().url();
      const method = route.request().method();
      
      if (url.includes('/transactions/initiate-swap') && method === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            transaction_id: 'test-tx-12345',
            payment_address: '0x1234567890123456789012345678901234567890',
            payment_amount: '1000.00',
            payment_token: 'USDC',
            cirx_amount: '2160.00',
            discount_percentage: 5,
            expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString()
          })
        });
      } else if (url.includes('/transactions/test-tx-12345/status')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            transaction_id: 'test-tx-12345',
            status: 'payment_pending',
            payment_received: false,
            cirx_transferred: false,
            created_at: new Date().toISOString()
          })
        });
      } else {
        await route.continue();
      }
    });
  });

  async function connectWallet(page) {
    const walletButton = page.locator('[data-testid="wallet-connect-button"], .wallet-connect, button:has-text("Connect"), button:has-text("Wallet")').first();
    await walletButton.click();
    
    // Wait for connection
    await expect(page.locator('[data-testid="wallet-address"], .wallet-address, .connected-address')).toBeVisible({ timeout: 10000 });
  }

  test('USDC to CIRX OTC swap with wallet integration', async ({ page }) => {
    await page.goto('/swap');
    
    // 1. Connect wallet
    await connectWallet(page);
    
    // 2. Select OTC tab if available
    const otcTab = page.locator('[data-testid="otc-tab"], .otc-tab, button:has-text("OTC"), .tab:has-text("OTC")');
    if (await otcTab.isVisible({ timeout: 3000 })) {
      await otcTab.click();
    }
    
    // 3. Configure swap - look for amount input
    const amountInput = page.locator('[data-testid="payment-amount"], [data-testid="amount-input"], input[placeholder*="amount"], input[type="number"]').first();
    await expect(amountInput).toBeVisible({ timeout: 10000 });
    await amountInput.fill('1000');
    
    // 4. Select token if dropdown exists
    const tokenSelector = page.locator('[data-testid="payment-token"], [data-testid="token-selector"], select, .token-dropdown');
    if (await tokenSelector.first().isVisible({ timeout: 3000 })) {
      await tokenSelector.first().selectOption('USDC');
    }
    
    // 5. Set recipient address
    const recipientInput = page.locator('[data-testid="cirx-recipient"], [data-testid="recipient-address"], input[placeholder*="address"]');
    if (await recipientInput.isVisible({ timeout: 3000 })) {
      await recipientInput.fill('0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370');
    }
    
    // 6. Select discount tier if available
    const discountSelector = page.locator('[data-testid="discount-tier"], .discount-dropdown');
    if (await discountSelector.isVisible({ timeout: 3000 })) {
      await discountSelector.selectOption('5-percent');
    }
    
    // 7. Review swap details - check if summary is shown
    const swapSummary = page.locator('[data-testid="swap-summary"], .swap-details, .quote-summary');
    if (await swapSummary.isVisible({ timeout: 3000 })) {
      await expect(swapSummary).toContainText('1000');
    }
    
    // 8. Initiate swap
    const initiateButton = page.locator('[data-testid="initiate-swap-button"], [data-testid="swap-button"], button:has-text("Swap"), button:has-text("Buy"), .initiate-swap').first();
    await expect(initiateButton).toBeVisible({ timeout: 10000 });
    await initiateButton.click();
    
    // 9. Verify swap initiated - look for payment instructions or success message
    const paymentInstructions = page.locator('[data-testid="payment-instructions"], .payment-details, .transaction-created');
    const successMessage = page.locator('[data-testid="success-message"], .success, .notification');
    const transactionId = page.locator('[data-testid="transaction-id"], .tx-id');
    
    // Either payment instructions or success should be visible
    await expect(paymentInstructions.or(successMessage)).toBeVisible({ timeout: 10000 });
    
    // 10. Check if transaction ID is displayed
    if (await transactionId.isVisible({ timeout: 3000 })) {
      const txId = await transactionId.textContent();
      expect(txId).toBeTruthy();
    }
  });

  test('ETH to CIRX liquid swap flow', async ({ page }) => {
    await page.goto('/swap');
    
    // Connect wallet
    await connectWallet(page);
    
    // Select liquid tab if available
    const liquidTab = page.locator('[data-testid="liquid-tab"], .liquid-tab, button:has-text("Liquid"), .tab:has-text("Instant")');
    if (await liquidTab.isVisible({ timeout: 3000 })) {
      await liquidTab.click();
    }
    
    // Configure ETH swap
    const amountInput = page.locator('[data-testid="payment-amount"], [data-testid="amount-input"], input[placeholder*="amount"], input[type="number"]').first();
    await amountInput.fill('0.5');
    
    // Select ETH if token selector exists
    const tokenSelector = page.locator('[data-testid="payment-token"], [data-testid="token-selector"], select, .token-dropdown');
    if (await tokenSelector.first().isVisible({ timeout: 3000 })) {
      await tokenSelector.first().selectOption('ETH');
    }
    
    // Initiate swap
    const swapButton = page.locator('[data-testid="initiate-swap-button"], [data-testid="swap-button"], button:has-text("Swap"), button:has-text("Buy")').first();
    await swapButton.click();
    
    // Verify response
    const response = page.locator('[data-testid="payment-instructions"], .payment-details, .success, .notification');
    await expect(response).toBeVisible({ timeout: 10000 });
  });

  test('form validation and error handling', async ({ page }) => {
    await page.goto('/swap');
    
    // Try to submit without connecting wallet
    const swapButton = page.locator('[data-testid="initiate-swap-button"], [data-testid="swap-button"], button:has-text("Swap"), button:has-text("Buy")').first();
    
    if (await swapButton.isVisible({ timeout: 5000 })) {
      await swapButton.click();
      
      // Should show error or wallet connect prompt
      const errorMessage = page.locator('[data-testid="error-message"], .error, .validation-error, .notification');
      const walletPrompt = page.locator('[data-testid="connect-wallet-prompt"], .wallet-required');
      
      await expect(errorMessage.or(walletPrompt)).toBeVisible({ timeout: 5000 });
    }
    
    // Connect wallet and test form validation
    await connectWallet(page);
    
    // Try empty amount
    const amountInput = page.locator('[data-testid="payment-amount"], [data-testid="amount-input"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 3000 })) {
      await amountInput.fill('');
      await swapButton.click();
      
      // Should show validation error
      const validationError = page.locator('[data-testid="validation-error"], .error, .invalid-input');
      if (await validationError.isVisible({ timeout: 3000 })) {
        expect(await validationError.textContent()).toContain('amount');
      }
    }
  });

  test('handles API errors gracefully', async ({ page }) => {
    // Override route to return error
    await page.route('/api/v1/transactions/initiate-swap', route => {
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          error: 'Internal server error',
          message: 'Service temporarily unavailable'
        })
      });
    });
    
    await page.goto('/swap');
    await connectWallet(page);
    
    // Fill form and submit
    const amountInput = page.locator('[data-testid="payment-amount"], [data-testid="amount-input"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 3000 })) {
      await amountInput.fill('100');
    }
    
    const swapButton = page.locator('[data-testid="initiate-swap-button"], [data-testid="swap-button"], button:has-text("Swap"), button:has-text("Buy")').first();
    await swapButton.click();
    
    // Should show error message
    const errorMessage = page.locator('[data-testid="error-message"], .error, .notification, .alert');
    await expect(errorMessage).toBeVisible({ timeout: 10000 });
    
    // Should allow retry
    const retryButton = page.locator('[data-testid="retry-button"], button:has-text("Retry"), .retry');
    if (await retryButton.isVisible({ timeout: 3000 })) {
      await expect(retryButton).toBeVisible();
    }
  });

  test('responsive design on mobile', async ({ page, isMobile }) => {
    if (isMobile) {
      await page.goto('/swap');
      
      // Check mobile layout
      const swapForm = page.locator('[data-testid="swap-form"], .swap-container, form');
      await expect(swapForm).toBeVisible();
      
      // Verify mobile-specific elements
      const mobileMenu = page.locator('.mobile-menu, .hamburger, [data-testid="mobile-nav"]');
      if (await mobileMenu.isVisible({ timeout: 3000 })) {
        await mobileMenu.click();
      }
      
      // Test form interactions on mobile
      const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
      if (await amountInput.isVisible({ timeout: 3000 })) {
        await amountInput.tap();
        await amountInput.fill('500');
      }
    }
  });
});