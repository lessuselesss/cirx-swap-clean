import { WagmiAdapter } from '@reown/appkit-adapter-wagmi'
import { mainnet, arbitrum, sepolia, base, optimism, polygon } from '@reown/appkit/networks'

// Project ID from Reown Cloud
export const projectId = '2585d3b6fd8a214ece0e26b344957169'

// Environment-driven network configuration
const isDevelopment = process.env.NODE_ENV === 'development' || process.env.NUXT_PUBLIC_TESTNET_MODE === 'true'

// EVM networks for Wagmi adapter - prioritize based on environment
export const evmNetworks = isDevelopment 
  ? [
      sepolia,  // Testnet first in development
      mainnet,  // Mainnet available but not default
      polygon,
      arbitrum,
      optimism, 
      base
    ]
  : [
      mainnet,  // Mainnet first in production
      polygon,
      arbitrum,
      optimism,
      base,
      sepolia   // Testnet available but not default
    ]

// All supported networks (for AppKit) - EVM only
export const networks = evmNetworks

// Application metadata - use dynamic URL to avoid mismatch warnings
const getAppUrl = () => {
  if (typeof window !== 'undefined') {
    return window.location.origin
  }
  // Fallback for SSR
  return process.env.NUXT_PUBLIC_SITE_URL || 'https://circular.io'
}

export const metadata = {
  name: 'Circular CIRX OTC Platform',
  description: 'Professional OTC trading platform for CIRX tokens with instant delivery and discounted vesting options.',
  url: getAppUrl(),
  icons: ['https://circular.io/circular-logo.svg']
}

// Initialize Wagmi Adapter for EVM chains only
export const wagmiAdapter = new WagmiAdapter({
  networks: evmNetworks,
  projectId,
  ssr: false
})

// Solana adapter removed - EVM only

// Export Wagmi config for use in plugins
export const wagmiConfig = wagmiAdapter.wagmiConfig