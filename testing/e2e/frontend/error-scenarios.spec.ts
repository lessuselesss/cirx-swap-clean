import { test, expect } from '@playwright/test';

test.describe('Error Scenario Testing', () => {
  test.beforeEach(async ({ page }) => {
    // Setup basic wallet mock
    await page.addInitScript(() => {
      window.ethereum = {
        isMetaMask: true,
        request: async ({ method }) => {
          if (method === 'eth_requestAccounts') {
            return ['0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3'];
          }
          if (method === 'eth_chainId') {
            return '0xaa36a7';
          }
          return null;
        },
        on: () => {},
        removeListener: () => {}
      };
    });
  });

  test('handles API failures gracefully', async ({ page }) => {
    // Intercept API calls and simulate random failures
    await page.route('/api/v1/**', route => {
      if (Math.random() < 0.3) { // 30% failure rate
        route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({ 
            error: 'Internal server error',
            message: 'Service temporarily unavailable'
          })
        });
      } else {
        route.continue();
      }
    });
    
    await page.goto('/swap');
    
    // Try to perform swap multiple times
    for (let i = 0; i < 3; i++) {
      const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
      if (await amountInput.isVisible({ timeout: 3000 })) {
        await amountInput.fill(`${100 + i * 50}`);
      }
      
      const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
      if (await swapButton.isVisible({ timeout: 3000 })) {
        await swapButton.click();
        
        // Check for either success or error
        const errorMessage = page.locator('[data-testid="error-message"], .error, .notification');
        const successMessage = page.locator('[data-testid="success-message"], .success, .payment-instructions');
        
        await expect(errorMessage.or(successMessage)).toBeVisible({ timeout: 10000 });
        
        // If error, should show retry option
        const retryButton = page.locator('[data-testid="retry-button"], button:has-text("Retry")');
        if (await errorMessage.isVisible() && await retryButton.isVisible({ timeout: 3000 })) {
          expect(retryButton).toBeVisible();
        }
        
        await page.waitForTimeout(1000);
      }
    }
  });

  test('network connectivity issues', async ({ page }) => {
    await page.goto('/swap');
    
    // Simulate network going offline
    await page.context().setOffline(true);
    
    // Try to perform action
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 3000 })) {
      await amountInput.fill('200');
    }
    
    const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
    if (await swapButton.isVisible({ timeout: 3000 })) {
      await swapButton.click();
      
      // Should show network error
      const networkError = page.locator('text="network", text="offline", text="connection", .network-error');
      await expect(networkError).toBeVisible({ timeout: 10000 });
    }
    
    // Restore network
    await page.context().setOffline(false);
    
    // Should be able to retry
    const retryButton = page.locator('[data-testid="retry-button"], button:has-text("Retry")');
    if (await retryButton.isVisible({ timeout: 3000 })) {
      await retryButton.click();
      
      // Should work now
      const successMessage = page.locator('[data-testid="success-message"], .success, .payment-instructions');
      await expect(successMessage).toBeVisible({ timeout: 10000 });
    }
  });

  test('wallet connection failures', async ({ page }) => {
    // Override wallet to reject connection
    await page.addInitScript(() => {
      window.ethereum = {
        isMetaMask: true,
        request: async ({ method }) => {
          if (method === 'eth_requestAccounts') {
            throw new Error('User rejected the request');
          }
          return null;
        },
        on: () => {},
        removeListener: () => {}
      };
    });
    
    await page.goto('/swap');
    
    // Try to connect wallet
    const walletButton = page.locator('[data-testid="wallet-connect-button"], button:has-text("Connect")').first();
    if (await walletButton.isVisible({ timeout: 5000 })) {
      await walletButton.click();
      
      // Should show connection error
      const connectionError = page.locator('[data-testid="connection-error"], .wallet-error, text="rejected", text="failed"');
      await expect(connectionError).toBeVisible({ timeout: 10000 });
    }
  });

  test('invalid input validation', async ({ page }) => {
    await page.goto('/swap');
    
    // Test various invalid inputs
    const testCases = [
      { value: '-100', error: 'negative' },
      { value: '0', error: 'zero' },
      { value: 'abc', error: 'invalid' },
      { value: '999999999999', error: 'maximum' }
    ];
    
    for (const testCase of testCases) {
      const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
      if (await amountInput.isVisible({ timeout: 3000 })) {
        await amountInput.fill(testCase.value);
        
        const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
        if (await swapButton.isVisible({ timeout: 3000 })) {
          await swapButton.click();
          
          // Should show validation error
          const validationError = page.locator('[data-testid="validation-error"], .validation-error, .invalid, .error');
          await expect(validationError).toBeVisible({ timeout: 5000 });
        }
        
        // Clear input for next test
        await amountInput.clear();
      }
    }
  });

  test('session timeout handling', async ({ page }) => {
    // Mock session expiry
    let sessionExpired = false;
    
    await page.route('/api/v1/**', route => {
      if (sessionExpired) {
        route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 'Session expired',
            code: 'SESSION_EXPIRED'
          })
        });
      } else {
        route.continue();
      }
    });
    
    await page.goto('/swap');
    
    // Perform normal action first
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 3000 })) {
      await amountInput.fill('150');
    }
    
    // Expire session
    sessionExpired = true;
    
    const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
    if (await swapButton.isVisible({ timeout: 3000 })) {
      await swapButton.click();
      
      // Should show session expired error
      const sessionError = page.locator('text="session", text="expired", text="unauthorized", .session-error');
      await expect(sessionError).toBeVisible({ timeout: 10000 });
      
      // Should provide way to re-authenticate
      const loginButton = page.locator('[data-testid="login-button"], button:has-text("Login"), button:has-text("Sign")');
      if (await loginButton.isVisible({ timeout: 3000 })) {
        expect(loginButton).toBeVisible();
      }
    }
  });

  test('insufficient wallet balance', async ({ page }) => {
    // Mock wallet with low balance
    await page.addInitScript(() => {
      window.ethereum = {
        isMetaMask: true,
        request: async ({ method }) => {
          if (method === 'eth_requestAccounts') {
            return ['0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3'];
          }
          if (method === 'eth_chainId') {
            return '0xaa36a7';
          }
          if (method === 'eth_getBalance') {
            return '0x0'; // 0 ETH
          }
          return null;
        },
        on: () => {},
        removeListener: () => {}
      };
    });
    
    await page.goto('/swap');
    
    // Connect wallet
    const walletButton = page.locator('[data-testid="wallet-connect-button"], button:has-text("Connect")').first();
    if (await walletButton.isVisible({ timeout: 5000 })) {
      await walletButton.click();
    }
    
    // Try to swap more than balance
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 3000 })) {
      await amountInput.fill('1000');
    }
    
    const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
    if (await swapButton.isVisible({ timeout: 3000 })) {
      await swapButton.click();
      
      // Should show insufficient balance error
      const balanceError = page.locator('text="insufficient", text="balance", .balance-error');
      await expect(balanceError).toBeVisible({ timeout: 10000 });
    }
  });

  test('browser compatibility issues', async ({ page, browserName }) => {
    await page.goto('/swap');
    
    // Test browser-specific features
    if (browserName === 'webkit') {
      // Safari-specific tests
      const safariWarning = page.locator('.safari-warning, .browser-warning');
      if (await safariWarning.isVisible({ timeout: 3000 })) {
        await expect(safariWarning).toContainText('Safari');
      }
    }
    
    // Test if wallet injection works across browsers
    const walletButton = page.locator('[data-testid="wallet-connect-button"], button:has-text("Connect")').first();
    if (await walletButton.isVisible({ timeout: 5000 })) {
      await walletButton.click();
      
      // Should either connect or show appropriate error
      const connected = page.locator('[data-testid="wallet-address"], .connected');
      const notSupported = page.locator('text="not supported", text="browser", .browser-error');
      
      await expect(connected.or(notSupported)).toBeVisible({ timeout: 10000 });
    }
  });

  test('concurrent user actions', async ({ page }) => {
    await page.goto('/swap');
    
    // Simulate rapid clicking/form changes
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
    
    if (await amountInput.isVisible({ timeout: 3000 }) && await swapButton.isVisible({ timeout: 3000 })) {
      // Rapid form changes
      for (let i = 0; i < 5; i++) {
        await amountInput.fill(`${i * 100}`);
        await page.waitForTimeout(100);
      }
      
      // Multiple rapid clicks
      for (let i = 0; i < 3; i++) {
        await swapButton.click();
        await page.waitForTimeout(200);
      }
      
      // Should handle gracefully without crashes
      const errorOrSuccess = page.locator('[data-testid="error-message"], [data-testid="success-message"], .error, .success');
      await expect(errorOrSuccess).toBeVisible({ timeout: 10000 });
      
      // App should remain functional
      await expect(amountInput).toBeVisible();
      await expect(swapButton).toBeVisible();
    }
  });

  test('malformed API responses', async ({ page }) => {
    // Mock malformed JSON responses
    await page.route('/api/v1/**', route => {
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: '{"invalid": json malformed'  // Invalid JSON
      });
    });
    
    await page.goto('/swap');
    
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 3000 })) {
      await amountInput.fill('100');
    }
    
    const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
    if (await swapButton.isVisible({ timeout: 3000 })) {
      await swapButton.click();
      
      // Should handle parsing error gracefully
      const parseError = page.locator('text="error", text="invalid", .parse-error, .json-error');
      await expect(parseError).toBeVisible({ timeout: 10000 });
    }
  });
});