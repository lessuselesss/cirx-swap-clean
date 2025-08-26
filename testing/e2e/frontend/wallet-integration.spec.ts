import { test, expect } from '@playwright/test';

test.describe('Wallet Integration E2E', () => {
  test.beforeEach(async ({ page }) => {
    // Setup mock wallet environment
    await page.addInitScript(() => {
      // Mock Ethereum provider (MetaMask)
      window.ethereum = {
        isMetaMask: true,
        request: async ({ method, params }) => {
          if (method === 'eth_requestAccounts') {
            return ['0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3'];
          }
          if (method === 'eth_chainId') {
            return '0xaa36a7'; // Sepolia chain ID
          }
          if (method === 'eth_accounts') {
            return ['0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3'];
          }
          if (method === 'eth_getBalance') {
            return '0x16345785d8a0000'; // 0.1 ETH
          }
          return null;
        },
        on: () => {},
        removeListener: () => {}
      };

      // Mock Solana provider (Phantom)
      window.solana = {
        isPhantom: true,
        connect: async () => ({
          publicKey: {
            toString: () => 'BB9dBE8B94AE940016E89837574E84E2651F7F10DA7809FFF0728CC419514370'
          }
        }),
        disconnect: async () => {},
        on: () => {},
        off: () => {}
      };
    });
  });

  test('complete MetaMask connection flow', async ({ page }) => {
    await page.goto('/swap');
    
    // Wait for page to load
    await expect(page.locator('body')).toBeVisible();
    
    // Look for wallet connection button (using multiple possible selectors)
    const walletButton = page.locator('[data-testid="wallet-connect-button"], .wallet-connect, button:has-text("Connect"), button:has-text("Wallet")').first();
    await expect(walletButton).toBeVisible({ timeout: 10000 });
    
    // Click wallet connect button
    await walletButton.click();
    
    // Look for MetaMask option in modal/dropdown
    const metamaskOption = page.locator('[data-testid="metamask-option"], button:has-text("MetaMask"), .metamask-option').first();
    if (await metamaskOption.isVisible({ timeout: 5000 })) {
      await metamaskOption.click();
    }
    
    // Verify wallet connected - look for address display
    const addressDisplay = page.locator('[data-testid="wallet-address"], .wallet-address, .connected-address');
    await expect(addressDisplay).toContainText('0x742d35', { timeout: 10000 });
  });

  test('Phantom wallet connection for Solana', async ({ page }) => {
    await page.goto('/swap');
    
    // Wait for page to load
    await expect(page.locator('body')).toBeVisible();
    
    // Look for wallet connection button
    const walletButton = page.locator('[data-testid="wallet-connect-button"], .wallet-connect, button:has-text("Connect"), button:has-text("Wallet")').first();
    await expect(walletButton).toBeVisible({ timeout: 10000 });
    
    // Click wallet connect button
    await walletButton.click();
    
    // Look for Phantom option
    const phantomOption = page.locator('[data-testid="phantom-option"], button:has-text("Phantom"), .phantom-option').first();
    if (await phantomOption.isVisible({ timeout: 5000 })) {
      await phantomOption.click();
    }
    
    // Verify Solana wallet connected
    const addressDisplay = page.locator('[data-testid="wallet-address"], .wallet-address, .connected-address');
    await expect(addressDisplay).toContainText('BB9dBE8B', { timeout: 10000 });
  });

  test('wallet disconnection flow', async ({ page }) => {
    await page.goto('/swap');
    
    // Connect wallet first
    const walletButton = page.locator('[data-testid="wallet-connect-button"], .wallet-connect, button:has-text("Connect"), button:has-text("Wallet")').first();
    await walletButton.click();
    
    // Wait for connection
    await expect(page.locator('[data-testid="wallet-address"], .wallet-address, .connected-address')).toBeVisible({ timeout: 10000 });
    
    // Look for disconnect button
    const disconnectButton = page.locator('[data-testid="disconnect-button"], button:has-text("Disconnect"), .disconnect-wallet').first();
    if (await disconnectButton.isVisible({ timeout: 5000 })) {
      await disconnectButton.click();
      
      // Verify disconnected
      await expect(page.locator('[data-testid="wallet-connect-button"], .wallet-connect, button:has-text("Connect")')).toBeVisible({ timeout: 5000 });
    }
  });

  test('handles wallet not installed scenario', async ({ page }) => {
    // Remove mock wallets
    await page.addInitScript(() => {
      delete window.ethereum;
      delete window.solana;
    });
    
    await page.goto('/swap');
    
    // Try to connect wallet
    const walletButton = page.locator('[data-testid="wallet-connect-button"], .wallet-connect, button:has-text("Connect"), button:has-text("Wallet")').first();
    await walletButton.click();
    
    // Should show install prompt or error message
    const installPrompt = page.locator('[data-testid="install-wallet"], .install-prompt, text="install"');
    const errorMessage = page.locator('[data-testid="error-message"], .error, .notification');
    
    // Either install prompt or error should be visible
    await expect(installPrompt.or(errorMessage)).toBeVisible({ timeout: 5000 });
  });

  test('network switching for Ethereum', async ({ page }) => {
    // Mock network switching
    await page.addInitScript(() => {
      window.ethereum.request = async ({ method, params }) => {
        if (method === 'eth_requestAccounts') {
          return ['0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3'];
        }
        if (method === 'eth_chainId') {
          return '0x1'; // Mainnet initially
        }
        if (method === 'wallet_switchEthereumChain') {
          // Simulate successful network switch
          return null;
        }
        return null;
      };
    });
    
    await page.goto('/swap');
    
    // Connect wallet
    const walletButton = page.locator('[data-testid="wallet-connect-button"], .wallet-connect, button:has-text("Connect"), button:has-text("Wallet")').first();
    await walletButton.click();
    
    // Look for network warning or switch prompt
    const networkWarning = page.locator('[data-testid="network-warning"], .network-error, text="network"');
    const switchButton = page.locator('[data-testid="switch-network"], button:has-text("Switch"), .network-switch');
    
    if (await networkWarning.isVisible({ timeout: 5000 })) {
      // Should show switch network option
      await expect(switchButton).toBeVisible();
    }
  });
});