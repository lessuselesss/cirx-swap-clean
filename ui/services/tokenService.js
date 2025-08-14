/**
 * Real ERC-20 token balance and interaction service
 * Provides live token balances and contract interactions
 */

// ERC-20 token contract addresses (mainnet)
export const TOKEN_ADDRESSES = {
  USDC: '0xA0b86a33E6411d5F68EC6CECa5E4Ef10Cb8a92Bb', // USDC on Ethereum mainnet
  USDT: '0xdAC17F958D2ee523a2206206994597C13D831ec7', // USDT on Ethereum mainnet
  // CIRX: '0x...' // Will be added when CIRX contract is deployed
}

// ERC-20 ABI (minimal for balance queries)
const ERC20_ABI = [
  {
    "constant": true,
    "inputs": [{"name": "_owner", "type": "address"}],
    "name": "balanceOf",
    "outputs": [{"name": "balance", "type": "uint256"}],
    "type": "function"
  },
  {
    "constant": true,
    "inputs": [],
    "name": "decimals",
    "outputs": [{"name": "", "type": "uint8"}],
    "type": "function"
  },
  {
    "constant": true,
    "inputs": [],
    "name": "symbol",
    "outputs": [{"name": "", "type": "string"}],
    "type": "function"
  }
]

/**
 * Get ETH balance for an address
 */
export const getETHBalance = async (address, provider) => {
  try {
    if (!provider || !address) return '0'
    
    const balanceWei = await provider.request({
      method: 'eth_getBalance',
      params: [address, 'latest']
    })
    
    // Convert Wei to ETH
    const balanceEth = parseInt(balanceWei, 16) / Math.pow(10, 18)
    return balanceEth.toFixed(6)
  } catch (error) {
    console.warn('Failed to fetch ETH balance:', error)
    return '0'
  }
}

/**
 * Get ERC-20 token balance for an address
 */
export const getTokenBalance = async (tokenSymbol, address, provider) => {
  try {
    if (!provider || !address || !TOKEN_ADDRESSES[tokenSymbol]) {
      return '0'
    }
    
    const contractAddress = TOKEN_ADDRESSES[tokenSymbol]
    
    // Create contract call data for balanceOf(address)
    const data = '0x70a08231' + address.slice(2).padStart(64, '0')
    
    const result = await provider.request({
      method: 'eth_call',
      params: [{
        to: contractAddress,
        data: data
      }, 'latest']
    })
    
    if (!result || result === '0x') {
      return '0'
    }
    
    // Convert hex result to decimal and adjust for token decimals
    const balance = parseInt(result, 16)
    const decimals = getTokenDecimals(tokenSymbol)
    const formattedBalance = balance / Math.pow(10, decimals)
    
    return formattedBalance.toFixed(6)
  } catch (error) {
    console.warn(`Failed to fetch ${tokenSymbol} balance:`, error)
    return '0'
  }
}

/**
 * Get token decimals (standard values)
 */
const getTokenDecimals = (tokenSymbol) => {
  const decimals = {
    USDC: 6,
    USDT: 6,
    CIRX: 18, // Assumed standard
    ETH: 18
  }
  return decimals[tokenSymbol] || 18
}

/**
 * Get all token balances for an address
 */
export const getAllTokenBalances = async (address, provider) => {
  if (!provider || !address) {
    return {
      ETH: '0',
      USDC: '0', 
      USDT: '0',
      CIRX: '0'
    }
  }
  
  try {
    // Fetch all balances in parallel
    const [ethBalance, usdcBalance, usdtBalance] = await Promise.all([
      getETHBalance(address, provider),
      getTokenBalance('USDC', address, provider),
      getTokenBalance('USDT', address, provider),
      // getTokenBalance('CIRX', address, provider), // Enable when CIRX contract is ready
    ])
    
    return {
      ETH: ethBalance,
      USDC: usdcBalance,
      USDT: usdtBalance,
      CIRX: '0' // Placeholder until CIRX contract is deployed
    }
  } catch (error) {
    console.error('Failed to fetch token balances:', error)
    
    // Return fallback values
    return {
      ETH: '0',
      USDC: '0',
      USDT: '0', 
      CIRX: '0'
    }
  }
}

/**
 * Check if token contract exists and is valid
 */
export const validateTokenContract = async (tokenSymbol, provider) => {
  try {
    if (tokenSymbol === 'ETH') return true
    
    const contractAddress = TOKEN_ADDRESSES[tokenSymbol]
    if (!contractAddress || !provider) return false
    
    // Try to get token symbol from contract
    const data = '0x95d89b41' // symbol() function selector
    
    const result = await provider.request({
      method: 'eth_call',
      params: [{
        to: contractAddress,
        data: data
      }, 'latest']
    })
    
    return result && result !== '0x'
  } catch (error) {
    console.warn(`Token contract validation failed for ${tokenSymbol}:`, error)
    return false
  }
}

/**
 * Format balance for display
 */
export const formatBalance = (balance, decimals = 6) => {
  const num = parseFloat(balance)
  if (isNaN(num) || num === 0) return '0'
  
  if (num < 0.000001) return '< 0.000001'
  if (num < 1) return num.toFixed(decimals)
  if (num < 1000) return num.toFixed(4)
  if (num < 1000000) return (num / 1000).toFixed(2) + 'K'
  
  return (num / 1000000).toFixed(2) + 'M'
}

/**
 * Check if address has sufficient balance for swap
 */
export const hasSufficientBalance = async (address, tokenSymbol, amount, provider) => {
  try {
    const balance = tokenSymbol === 'ETH' 
      ? await getETHBalance(address, provider)
      : await getTokenBalance(tokenSymbol, address, provider)
    
    const available = parseFloat(balance)
    const required = parseFloat(amount)
    
    // Reserve gas fees for ETH transactions
    const gasReserve = tokenSymbol === 'ETH' ? 0.001 : 0
    
    return available >= (required + gasReserve)
  } catch (error) {
    console.warn('Balance check failed:', error)
    return false
  }
}