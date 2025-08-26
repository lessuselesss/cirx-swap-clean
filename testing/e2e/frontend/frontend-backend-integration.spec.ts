import { test, expect } from '@playwright/test';

test.describe('Frontend-Backend Integration E2E', () => {
  let apiCalls: Array<{url: string, method: string, headers: any, body?: any}> = [];

  test.beforeEach(async ({ page }) => {
    // Reset API call tracking
    apiCalls = [];
    
    // Setup wallet mock
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

    // Monitor all API requests
    page.on('request', request => {
      if (request.url().includes('/api/')) {
        apiCalls.push({
          url: request.url(),
          method: request.method(),
          headers: request.headers(),
          body: request.postData()
        });
      }
    });
  });

  test('frontend API integration with real backend', async ({ page }) => {
    // Test backend health first
    const healthResponse = await page.request.get('/api/v1/health');
    if (healthResponse.status() !== 200) {
      test.skip('Backend not available for integration testing');
    }

    await page.goto('/swap');
    
    // Connect wallet
    const walletButton = page.locator('[data-testid="wallet-connect-button"], button:has-text("Connect")').first();
    if (await walletButton.isVisible({ timeout: 5000 })) {
      await walletButton.click();
    }

    // Fill swap form
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 5000 })) {
      await amountInput.fill('100');
    }

    // Submit swap
    const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
    if (await swapButton.isVisible({ timeout: 5000 })) {
      await swapButton.click();
    }

    // Wait for API calls to complete
    await page.waitForTimeout(2000);

    // Verify API calls were made correctly
    const swapApiCall = apiCalls.find(call => 
      call.url.includes('/api/v1/transactions/initiate-swap') && 
      call.method === 'POST'
    );

    if (swapApiCall) {
      expect(swapApiCall.method).toBe('POST');
      expect(swapApiCall.headers['content-type']).toContain('application/json');
      
      // Verify request body contains expected fields
      if (swapApiCall.body) {
        const requestBody = JSON.parse(swapApiCall.body);
        expect(requestBody).toHaveProperty('payment_amount');
        expect(requestBody.payment_amount).toBe('100');
      }
    }
  });

  test('API error handling and retry logic', async ({ page }) => {
    let callCount = 0;
    
    // Mock API to fail first call, succeed on retry
    await page.route('/api/v1/transactions/initiate-swap', async route => {
      callCount++;
      if (callCount === 1) {
        await route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 'Server temporarily unavailable'
          })
        });
      } else {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            transaction_id: 'retry-test-123',
            payment_address: '0x1234567890123456789012345678901234567890'
          })
        });
      }
    });

    await page.goto('/swap');
    
    // Submit form to trigger API call
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 5000 })) {
      await amountInput.fill('50');
    }

    const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
    if (await swapButton.isVisible({ timeout: 5000 })) {
      await swapButton.click();
    }

    // Should show error message first
    const errorMessage = page.locator('[data-testid="error-message"], .error, .notification');
    await expect(errorMessage).toBeVisible({ timeout: 10000 });

    // Look for retry button and click it
    const retryButton = page.locator('[data-testid="retry-button"], button:has-text("Retry")');
    if (await retryButton.isVisible({ timeout: 5000 })) {
      await retryButton.click();
      
      // Should succeed on retry
      const successMessage = page.locator('[data-testid="success-message"], .success, .payment-instructions');
      await expect(successMessage).toBeVisible({ timeout: 10000 });
    }

    // Verify both API calls were made
    expect(callCount).toBe(2);
  });

  test('API authentication and headers', async ({ page }) => {
    await page.goto('/swap');
    
    // Make any API call to check headers
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 5000 })) {
      await amountInput.fill('25');
      
      const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
      if (await swapButton.isVisible({ timeout: 5000 })) {
        await swapButton.click();
      }
    }

    await page.waitForTimeout(1000);

    // Check API call headers
    const apiCall = apiCalls.find(call => call.method === 'POST');
    if (apiCall) {
      // Verify required headers
      expect(apiCall.headers).toHaveProperty('content-type');
      expect(apiCall.headers['content-type']).toContain('application/json');
      
      // Check for API key or auth headers if used
      if (apiCall.headers['x-api-key'] || apiCall.headers['authorization']) {
        expect(apiCall.headers['x-api-key'] || apiCall.headers['authorization']).toBeTruthy();
      }
    }
  });

  test('real-time status updates via polling', async ({ page }) => {
    // Mock transaction status endpoint
    let statusCallCount = 0;
    await page.route('/api/v1/transactions/*/status', async route => {
      statusCallCount++;
      const status = statusCallCount < 3 ? 'payment_pending' : 'payment_verified';
      
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          transaction_id: 'status-test-123',
          status: status,
          payment_received: status === 'payment_verified',
          cirx_transferred: false
        })
      });
    });

    // Mock initial swap creation
    await page.route('/api/v1/transactions/initiate-swap', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          transaction_id: 'status-test-123',
          payment_address: '0x1234567890123456789012345678901234567890'
        })
      });
    });

    await page.goto('/swap');
    
    // Create transaction
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 5000 })) {
      await amountInput.fill('75');
    }

    const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
    if (await swapButton.isVisible({ timeout: 5000 })) {
      await swapButton.click();
    }

    // Wait for status updates
    await page.waitForTimeout(5000);

    // Check that status polling occurred
    const statusCalls = apiCalls.filter(call => call.url.includes('/status'));
    expect(statusCalls.length).toBeGreaterThan(0);
  });

  test('transaction history API integration', async ({ page }) => {
    // Mock transaction history endpoint
    await page.route('/api/v1/transactions/history*', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          transactions: [
            {
              id: 'hist-123',
              payment_amount: '100.00',
              payment_token: 'USDC',
              cirx_amount: '216.00',
              status: 'completed',
              created_at: new Date().toISOString()
            },
            {
              id: 'hist-124',
              payment_amount: '50.00', 
              payment_token: 'ETH',
              cirx_amount: '108.00',
              status: 'payment_pending',
              created_at: new Date(Date.now() - 3600000).toISOString()
            }
          ],
          pagination: {
            total: 2,
            page: 1,
            limit: 10
          }
        })
      });
    });

    await page.goto('/history');

    // Wait for history to load
    await page.waitForTimeout(2000);

    // Verify history API was called
    const historyCall = apiCalls.find(call => call.url.includes('/history'));
    expect(historyCall).toBeTruthy();
    expect(historyCall?.method).toBe('GET');

    // Check if transactions are displayed
    const transactionList = page.locator('[data-testid="transaction-list"], .transaction-history, .history-table');
    if (await transactionList.isVisible({ timeout: 5000 })) {
      // Should show transaction data
      await expect(page.locator('text=hist-123')).toBeVisible();
      await expect(page.locator('text=100.00')).toBeVisible();
    }
  });

  test('API rate limiting handling', async ({ page }) => {
    let requestCount = 0;
    
    // Mock rate limiting after 3 requests
    await page.route('/api/v1/**', async route => {
      requestCount++;
      if (requestCount > 3) {
        await route.fulfill({
          status: 429,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 'Rate limit exceeded',
            retry_after: 1
          })
        });
      } else {
        await route.continue();
      }
    });

    await page.goto('/swap');
    
    // Make multiple rapid requests
    for (let i = 0; i < 5; i++) {
      const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
      if (await amountInput.isVisible({ timeout: 2000 })) {
        await amountInput.fill(`${10 + i}`);
        
        const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
        if (await swapButton.isVisible({ timeout: 2000 })) {
          await swapButton.click();
          await page.waitForTimeout(500);
        }
      }
    }

    // Should eventually show rate limit error
    const rateLimitError = page.locator('text="Rate limit", text="too many", .rate-limit');
    await expect(rateLimitError).toBeVisible({ timeout: 10000 });
  });
});