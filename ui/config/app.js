/**
 * Application configuration
 * Centralized settings for features, APIs, and environment-specific behavior
 */

// Environment detection
const isDevelopment = process.env.NODE_ENV === 'development'
const isProduction = process.env.NODE_ENV === 'production'

export const config = {
  // Feature flags
  features: {
    // Live price feeds (CoinGecko API)
    livePrices: true,
    
    // Real token balance queries (requires Ethereum RPC)
    realBalances: true,
    
    // Contract interactions (disabled until CIRX contracts are deployed)
    contractInteractions: false,
    
    // Phantom wallet support (currently disabled per user request)
    phantomWallet: false,
    
    // WalletConnect support (currently disabled)
    walletConnect: false,
    
    // Vesting claims (mock until contracts are ready)
    vestingClaims: false
  },

  // API endpoints
  apis: {
    // Price feed service
    priceApi: 'https://api.coingecko.com/api/v3/simple/price',
    
    // Ethereum RPC (using public endpoint, can be replaced with Infura/Alchemy)
    ethereumRpc: 'https://ethereum.publicnode.com',
    
    // Backup RPC endpoints
    backupRpcs: [
      'https://rpc.ankr.com/eth',
      'https://eth.llamarpc.com'
    ]
  },

  // Smart contract addresses (will be updated when contracts are deployed)
  contracts: {
    cirx: null, // CIRX token contract
    swap: null, // Main swap contract  
    vesting: null, // Vesting contract
    oracle: null // Price oracle contract
  },

  // Network settings
  networks: {
    ethereum: {
      chainId: 1,
      name: 'Ethereum Mainnet',
      rpcUrl: 'https://ethereum.publicnode.com',
      blockExplorer: 'https://etherscan.io'
    },
    sepolia: {
      chainId: 11155111,
      name: 'Sepolia Testnet', 
      rpcUrl: 'https://sepolia.infura.io/v3/',
      blockExplorer: 'https://sepolia.etherscan.io'
    },
    local: {
      chainId: 31337,
      name: 'Local Network',
      rpcUrl: 'http://localhost:8545',
      blockExplorer: null
    }
  },

  // UI settings
  ui: {
    // Theme
    defaultTheme: 'dark',
    
    // Animation speeds
    animationDuration: 300,
    
    // Toast notification duration
    toastDuration: 5000,
    
    // Price refresh interval (30 seconds)
    priceRefreshInterval: 30000,
    
    // Balance refresh interval (10 seconds)
    balanceRefreshInterval: 10000
  },

  // Business logic
  business: {
    // Minimum swap amount in USD
    minSwapAmount: 10,
    
    // OTC discount tiers
    otcTiers: [
      { minAmount: 50000, discount: 12 }, // $50K+: 12%
      { minAmount: 10000, discount: 8 },  // $10K+: 8%
      { minAmount: 1000, discount: 5 }    // $1K+: 5%
    ],
    
    // Fee structure
    fees: {
      liquid: 0.3,  // 0.3% for liquid swaps  
      otc: 0.15     // 0.15% for OTC swaps
    },
    
    // Vesting period for OTC purchases
    vestingPeriod: 6 // months
  },

  // Development settings
  dev: {
    // Enable console logging
    enableLogging: isDevelopment,
    
    // Show simulation warnings
    showSimulationWarnings: true,
    
    // Mock data fallbacks
    enableMockFallbacks: true
  }
}

/**
 * Get feature flag status
 */
export const isFeatureEnabled = (featureName) => {
  return config.features[featureName] || false
}

/**
 * Get API endpoint
 */
export const getApiEndpoint = (apiName) => {
  return config.apis[apiName] || null
}

/**
 * Get contract address
 */
export const getContractAddress = (contractName) => {
  return config.contracts[contractName] || null
}

/**
 * Get network configuration
 */
export const getNetworkConfig = (networkName = 'ethereum') => {
  return config.networks[networkName] || config.networks.ethereum
}

/**
 * Check if we're in development mode
 */
export const isDev = () => isDevelopment

/**
 * Check if we're in production mode
 */
export const isProd = () => isProduction