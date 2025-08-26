import { test, expect } from '@playwright/test';

test.describe('Performance Testing', () => {
  test.beforeEach(async ({ page }) => {
    // Setup basic mocks
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

  test('page load performance', async ({ page }) => {
    const startTime = Date.now();
    
    await page.goto('/swap');
    
    // Wait for critical elements to be visible
    await expect(page.locator('body')).toBeVisible();
    
    const loadTime = Date.now() - startTime;
    
    // Performance thresholds
    expect(loadTime).toBeLessThan(5000); // Should load within 5 seconds
    
    // Check for critical elements
    const swapForm = page.locator('[data-testid="swap-form"], .swap-container, form');
    const loadEventTime = await page.evaluate(() => {
      return performance.timing.loadEventEnd - performance.timing.navigationStart;
    });
    
    // Web Vitals checks
    const lcp = await page.evaluate(() => {
      return new Promise((resolve) => {
        new PerformanceObserver((entryList) => {
          const entries = entryList.getEntries();
          if (entries.length > 0) {
            resolve(entries[entries.length - 1].startTime);
          }
        }).observe({ entryTypes: ['largest-contentful-paint'] });
        
        // Fallback timeout
        setTimeout(() => resolve(0), 3000);
      });
    });
    
    console.log('Performance metrics:', {
      totalLoadTime: loadTime,
      loadEventTime,
      largestContentfulPaint: lcp
    });
    
    // Performance assertions
    expect(loadEventTime).toBeLessThan(3000); // Load event within 3 seconds
    if (lcp > 0) {
      expect(lcp).toBeLessThan(2500); // LCP within 2.5 seconds (Core Web Vital)
    }
  });

  test('form interaction performance', async ({ page }) => {
    await page.goto('/swap');
    
    // Wait for form to be ready
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    await expect(amountInput).toBeVisible({ timeout: 10000 });
    
    // Measure form interaction response times
    const interactions = [
      { action: 'fill amount', element: amountInput, value: '100' },
      { action: 'clear amount', element: amountInput, value: '' },
      { action: 'fill large amount', element: amountInput, value: '999999' }
    ];
    
    for (const interaction of interactions) {
      const startTime = Date.now();
      
      if (interaction.action.includes('clear')) {
        await interaction.element.clear();
      } else {
        await interaction.element.fill(interaction.value);
      }
      
      // Wait for any debounced updates
      await page.waitForTimeout(500);
      
      const responseTime = Date.now() - startTime;
      
      console.log(`${interaction.action} response time:`, responseTime + 'ms');
      
      // Form interactions should be responsive
      expect(responseTime).toBeLessThan(1000); // Under 1 second
    }
  });

  test('API response time performance', async ({ page }) => {
    const apiTimes: {[key: string]: number} = {};
    
    // Track API request timing
    page.on('response', response => {
      if (response.url().includes('/api/')) {
        const request = response.request();
        const timing = response.timing();
        apiTimes[response.url()] = timing.responseEnd;
      }
    });
    
    // Mock API with realistic response times
    await page.route('/api/v1/**', async route => {
      // Simulate network latency
      await new Promise(resolve => setTimeout(resolve, Math.random() * 200 + 50));
      
      if (route.request().url().includes('/transactions/initiate-swap')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            transaction_id: 'perf-test-123',
            payment_address: '0x1234567890123456789012345678901234567890'
          })
        });
      } else {
        await route.continue();
      }
    });
    
    await page.goto('/swap');
    
    // Trigger API calls
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 5000 })) {
      await amountInput.fill('150');
    }
    
    const swapButton = page.locator('[data-testid="initiate-swap-button"], button:has-text("Swap")').first();
    if (await swapButton.isVisible({ timeout: 5000 })) {
      const apiStartTime = Date.now();
      await swapButton.click();
      
      // Wait for API response
      await page.waitForResponse('/api/v1/transactions/initiate-swap', { timeout: 10000 });
      const apiResponseTime = Date.now() - apiStartTime;
      
      console.log('API response time:', apiResponseTime + 'ms');
      
      // API should respond quickly
      expect(apiResponseTime).toBeLessThan(5000); // Under 5 seconds
      expect(apiResponseTime).toBeLessThan(2000); // Ideally under 2 seconds
    }
  });

  test('memory usage and resource cleanup', async ({ page }) => {
    await page.goto('/swap');
    
    // Get initial memory usage
    const initialMemory = await page.evaluate(() => {
      return (performance as any).memory ? {
        usedJSHeapSize: (performance as any).memory.usedJSHeapSize,
        totalJSHeapSize: (performance as any).memory.totalJSHeapSize,
        jsHeapSizeLimit: (performance as any).memory.jsHeapSizeLimit
      } : null;
    });
    
    // Perform multiple form interactions to test for memory leaks
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    
    if (await amountInput.isVisible({ timeout: 5000 })) {
      for (let i = 0; i < 50; i++) {
        await amountInput.fill(`${i * 10}`);
        await page.waitForTimeout(10); // Small delay
      }
    }
    
    // Force garbage collection if available
    await page.evaluate(() => {
      if (window.gc) {
        window.gc();
      }
    });
    
    // Get final memory usage
    const finalMemory = await page.evaluate(() => {
      return (performance as any).memory ? {
        usedJSHeapSize: (performance as any).memory.usedJSHeapSize,
        totalJSHeapSize: (performance as any).memory.totalJSHeapSize,
        jsHeapSizeLimit: (performance as any).memory.jsHeapSizeLimit
      } : null;
    });
    
    if (initialMemory && finalMemory) {
      const memoryIncrease = finalMemory.usedJSHeapSize - initialMemory.usedJSHeapSize;
      const memoryIncreasePercent = (memoryIncrease / initialMemory.usedJSHeapSize) * 100;
      
      console.log('Memory usage:', {
        initial: Math.round(initialMemory.usedJSHeapSize / 1024 / 1024) + ' MB',
        final: Math.round(finalMemory.usedJSHeapSize / 1024 / 1024) + ' MB',
        increase: Math.round(memoryIncrease / 1024 / 1024) + ' MB',
        increasePercent: Math.round(memoryIncreasePercent) + '%'
      });
      
      // Memory usage shouldn't grow excessively
      expect(memoryIncreasePercent).toBeLessThan(200); // Less than 200% increase
      expect(finalMemory.usedJSHeapSize).toBeLessThan(100 * 1024 * 1024); // Under 100MB total
    }
  });

  test('network performance with slow connections', async ({ page, context }) => {
    // Simulate slow 3G connection
    await context.route('**/*', async route => {
      await new Promise(resolve => setTimeout(resolve, 500)); // 500ms delay
      await route.continue();
    });
    
    const startTime = Date.now();
    await page.goto('/swap');
    
    // Wait for essential content
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    
    const loadTime = Date.now() - startTime;
    console.log('Slow connection load time:', loadTime + 'ms');
    
    // Should still be usable on slow connections
    expect(loadTime).toBeLessThan(15000); // 15 seconds max
    
    // Verify core functionality works
    const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
    if (await amountInput.isVisible({ timeout: 5000 })) {
      await amountInput.fill('50');
      
      // Should respond even on slow connections
      const inputValue = await amountInput.inputValue();
      expect(inputValue).toBe('50');
    }
  });

  test('concurrent user simulation', async ({ browser }) => {
    const contexts = [];
    const pages = [];
    
    // Create multiple browser contexts to simulate concurrent users
    for (let i = 0; i < 3; i++) {
      const context = await browser.newContext();
      const page = await context.newPage();
      
      // Setup wallet mock for each context
      await page.addInitScript(() => {
        window.ethereum = {
          isMetaMask: true,
          request: async ({ method }) => {
            if (method === 'eth_requestAccounts') {
              return [`0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a${Math.floor(Math.random() * 10)}`];
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
      
      contexts.push(context);
      pages.push(page);
    }
    
    try {
      // Navigate all pages simultaneously
      const startTime = Date.now();
      
      await Promise.all(pages.map(page => page.goto('/swap')));
      
      const navigationTime = Date.now() - startTime;
      console.log('Concurrent navigation time:', navigationTime + 'ms');
      
      // Wait for all pages to load
      await Promise.all(pages.map(page => 
        expect(page.locator('body')).toBeVisible({ timeout: 10000 })
      ));
      
      // Perform actions on all pages simultaneously
      const actionPromises = pages.map(async (page, index) => {
        const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
        if (await amountInput.isVisible({ timeout: 5000 })) {
          await amountInput.fill(`${(index + 1) * 100}`);
        }
      });
      
      const actionStartTime = Date.now();
      await Promise.all(actionPromises);
      const actionTime = Date.now() - actionStartTime;
      
      console.log('Concurrent actions time:', actionTime + 'ms');
      
      // Verify all pages function correctly under load
      for (const page of pages) {
        const amountInput = page.locator('[data-testid="payment-amount"], input[type="number"]').first();
        if (await amountInput.isVisible({ timeout: 2000 })) {
          const value = await amountInput.inputValue();
          expect(value).toBeTruthy();
        }
      }
      
      // Performance should be reasonable even with concurrent users
      expect(navigationTime).toBeLessThan(10000); // 10 seconds
      expect(actionTime).toBeLessThan(5000); // 5 seconds
      
    } finally {
      // Cleanup
      await Promise.all(contexts.map(context => context.close()));
    }
  });

  test('large data handling performance', async ({ page }) => {
    // Mock API with large response
    await page.route('/api/v1/transactions/history*', async route => {
      const largeTransactionList = Array.from({ length: 1000 }, (_, i) => ({
        id: `tx-${i}`,
        payment_amount: `${(i * 10) % 1000}.00`,
        payment_token: ['ETH', 'USDC', 'USDT'][i % 3],
        cirx_amount: `${(i * 21.6) % 10000}.00`,
        status: ['completed', 'pending', 'failed'][i % 3],
        created_at: new Date(Date.now() - i * 3600000).toISOString()
      }));
      
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          transactions: largeTransactionList,
          pagination: { total: 1000, page: 1, limit: 1000 }
        })
      });
    });
    
    const startTime = Date.now();
    await page.goto('/history');
    
    // Wait for data to load
    await page.waitForResponse('/api/v1/transactions/history*');
    
    const loadTime = Date.now() - startTime;
    console.log('Large data load time:', loadTime + 'ms');
    
    // Should handle large datasets reasonably
    expect(loadTime).toBeLessThan(10000); // 10 seconds
    
    // Check if virtualization or pagination is working
    const visibleTransactions = page.locator('[data-testid*="transaction"], .transaction-row, tr');
    const transactionCount = await visibleTransactions.count();
    
    // Should not render all 1000 items at once (performance optimization)
    expect(transactionCount).toBeLessThan(100);
    expect(transactionCount).toBeGreaterThan(0);
    
    console.log('Visible transactions:', transactionCount);
  });
});