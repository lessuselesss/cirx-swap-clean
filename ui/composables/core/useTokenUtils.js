/**
 * Token Utilities
 * Consolidated token configuration and utilities
 * Eliminates duplicate getTokenDecimals functions (100% similarity)
 */
import { ref, computed } from 'vue'

export function useTokenUtils() {
  /**
   * Get token decimals by symbol
   * Consolidated from useAppKitWallet.js and useSwapHandler.js
   * @param {string} tokenSymbol - Token symbol (ETH, USDC, etc.)
   * @returns {number} Number of decimals for the token
   */
  const getTokenDecimals = (tokenSymbol) => {
    const decimals = {
      ETH: 18,
      USDC: 6,  // USDC uses 6 decimals
      USDT: 6,  // USDT uses 6 decimals
      CIRX: 18
    }
    return decimals[tokenSymbol] || 18 // Default to 18 decimals
  }

  /**
   * Token metadata configuration
   */
  const TOKEN_CONFIG = {
    ETH: {
      symbol: 'ETH',
      name: 'Ethereum',
      decimals: 18,
      icon: 'ethereum'
    },
    USDC: {
      symbol: 'USDC', 
      name: 'USD Coin',
      decimals: 6,
      icon: 'usdc'
    },
    USDT: {
      symbol: 'USDT',
      name: 'Tether USD', 
      decimals: 6,
      icon: 'usdt'
    },
    CIRX: {
      symbol: 'CIRX',
      name: 'Circular Protocol',
      decimals: 18,
      icon: 'cirx'
    }
  }

  /**
   * Get token configuration by symbol
   * @param {string} tokenSymbol - Token symbol
   * @returns {object} Token configuration object
   */
  const getTokenConfig = (tokenSymbol) => {
    return TOKEN_CONFIG[tokenSymbol] || {
      symbol: tokenSymbol,
      name: tokenSymbol,
      decimals: 18,
      icon: 'default'
    }
  }

  /**
   * Get list of supported tokens
   * @returns {array} Array of supported token symbols
   */
  const getSupportedTokens = () => {
    return Object.keys(TOKEN_CONFIG)
  }

  /**
   * Check if token is supported
   * @param {string} tokenSymbol - Token symbol to check
   * @returns {boolean} True if token is supported
   */
  const isTokenSupported = (tokenSymbol) => {
    return !!TOKEN_CONFIG[tokenSymbol]
  }

  /**
   * Normalize token symbol for price lookup
   * Consolidated from useQuoteCalculator.js and useSwapHandler.js (100% similarity)
   * @param {string} tokenSymbol - Token symbol to normalize
   * @returns {string} Normalized token symbol
   */
  const normalizeTokenSymbol = (tokenSymbol) => {
    // Handle Solana-specific token naming
    if (tokenSymbol === 'USDC_SOL') return 'USDC'
    if (tokenSymbol === 'USDT_SOL') return 'USDT'
    return tokenSymbol?.toUpperCase() || 'UNKNOWN'
  }

  return {
    // Core functions
    getTokenDecimals,
    getTokenConfig,
    
    // Utilities
    getSupportedTokens,
    isTokenSupported,
    normalizeTokenSymbol,
    
    // Configuration
    TOKEN_CONFIG
  }
}

// Named exports for direct import
export const {
  getTokenDecimals,
  getTokenConfig,
  getSupportedTokens,
  isTokenSupported,
  normalizeTokenSymbol,
  TOKEN_CONFIG
} = useTokenUtils()