/**
 * Wallet configuration constants
 * Consolidates duplicate wallet metadata and configuration across components
 */

export const WALLET_ICONS = {
  metamask: 'https://raw.githubusercontent.com/MetaMask/brand-resources/master/SVG/metamask-fox.svg',
  phantom: '/icons/wallets/phantom-icon.svg',
  walletconnect: '/icons/wallets/walletconnect.svg',
  coinbase: 'https://avatars.githubusercontent.com/u/18060234?s=280&v=4',
  rabby: '/icons/wallets/rabby.svg',
  trust: '/icons/wallets/trust.svg'
}

export const WALLET_METADATA = {
  metamask: {
    name: 'MetaMask',
    installUrl: 'https://metamask.io/download/',
    blockchain: 'ethereum',
    icon: WALLET_ICONS.metamask,
    description: 'Popular Ethereum wallet extension'
  },
  phantom: {
    name: 'Phantom', 
    installUrl: 'https://phantom.app/download',
    blockchain: 'solana',
    icon: WALLET_ICONS.phantom,
    description: 'Leading Solana wallet'
  },
  walletconnect: {
    name: 'WalletConnect',
    installUrl: 'https://walletconnect.org/',
    blockchain: 'ethereum',
    icon: WALLET_ICONS.walletconnect,
    description: 'Connect any wallet'
  },
  coinbase: {
    name: 'Coinbase Wallet',
    installUrl: 'https://www.coinbase.com/wallet',
    blockchain: 'ethereum',
    icon: WALLET_ICONS.coinbase,
    description: 'Coinbase\'s self-custody wallet'
  },
  rabby: {
    name: 'Rabby',
    installUrl: 'https://rabby.io/',
    blockchain: 'ethereum', 
    icon: WALLET_ICONS.rabby,
    description: 'DeFi-focused wallet'
  },
  trust: {
    name: 'Trust Wallet',
    installUrl: 'https://trustwallet.com/',
    blockchain: 'ethereum',
    icon: WALLET_ICONS.trust,
    description: 'Mobile-first wallet'
  }
}

export const SUPPORTED_WALLETS = Object.keys(WALLET_METADATA)

export const ETHEREUM_WALLETS = SUPPORTED_WALLETS.filter(
  wallet => WALLET_METADATA[wallet].blockchain === 'ethereum'
)

export const SOLANA_WALLETS = SUPPORTED_WALLETS.filter(
  wallet => WALLET_METADATA[wallet].blockchain === 'solana'
)

/**
 * Get wallet metadata by wallet ID
 * @param {string} walletId - Wallet identifier
 * @returns {object|null} Wallet metadata or null if not found
 */
export function getWalletMetadata(walletId) {
  return WALLET_METADATA[walletId] || null
}

/**
 * Check if wallet is supported
 * @param {string} walletId - Wallet identifier
 * @returns {boolean} Is wallet supported
 */
export function isWalletSupported(walletId) {
  return SUPPORTED_WALLETS.includes(walletId)
}

/**
 * Get wallets by blockchain
 * @param {string} blockchain - 'ethereum' or 'solana'
 * @returns {string[]} Array of wallet IDs for that blockchain
 */
export function getWalletsByBlockchain(blockchain) {
  return SUPPORTED_WALLETS.filter(
    wallet => WALLET_METADATA[wallet].blockchain === blockchain
  )
}