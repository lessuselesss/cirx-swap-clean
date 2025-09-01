/**
 * Formatting Utility Functions
 * Consolidated from useFormattedNumbers.js to eliminate redundancy
 * Provides consistent number, currency, and percentage formatting
 */
import { ref, computed } from 'vue'

export function useFormattingUtils() {

  /**
   * Advanced number formatting with comprehensive options
   * Consolidated from useFormattedNumbers.js lines 758-803
   * @param {number|string} value - Value to format
   * @param {object} options - Formatting options
   * @returns {string} Formatted number
   */
  const formatNumber = (value, options = {}) => {
    const {
      decimals = 2,
      locale = 'en-US',
      style = 'decimal',
      currency = 'USD',
      compact = false,
      showSign = false,
      fallback = '0'
    } = options

    if (value === null || value === undefined || value === '') {
      return fallback
    }

    const numValue = typeof value === 'string' ? parseFloat(value) : value
    
    if (isNaN(numValue)) {
      return fallback
    }

    const formatOptions = {
      style,
      minimumFractionDigits: style === 'currency' ? 2 : 0,
      maximumFractionDigits: decimals
    }

    if (style === 'currency') {
      formatOptions.currency = currency
    }

    if (compact) {
      formatOptions.notation = 'compact'
      formatOptions.compactDisplay = 'short'
    }

    if (showSign) {
      formatOptions.signDisplay = 'always'
    }

    try {
      return new Intl.NumberFormat(locale, formatOptions).format(numValue)
    } catch (error) {
      console.warn('Number formatting error:', error)
      return numValue.toString()
    }
  }

  /**
   * Format currency amounts
   * Consolidated from useFormattedNumbers.js lines 857-871
   * @param {number|string} amount - Amount to format
   * @param {object} options - Currency formatting options
   * @returns {string} Formatted currency
   */
  const formatCurrency = (amount, options = {}) => {
    const {
      currency = 'USD',
      locale = 'en-US',
      compact = false,
      decimals = 2
    } = options

    return formatNumber(amount, {
      style: 'currency',
      currency,
      locale,
      compact,
      decimals
    })
  }

  /**
   * Format token amounts with appropriate decimal places
   * Consolidated from useFormattedNumbers.js lines 811-849
   * @param {number|string} amount - Token amount
   * @param {object} options - Token formatting options
   * @returns {string} Formatted token amount
   */
  const formatTokenAmount = (amount, options = {}) => {
    const {
      decimals = 6,
      symbol = '',
      compact = false,
      showFullPrecision = false,
      locale = 'en-US'
    } = options

    if (!amount || amount === '0') {
      return `0${symbol ? ' ' + symbol : ''}`
    }

    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount
    
    if (isNaN(numAmount)) {
      return `0${symbol ? ' ' + symbol : ''}`
    }

    let formattedAmount

    if (showFullPrecision) {
      // Show full precision for very small amounts
      formattedAmount = numAmount.toString()
    } else if (compact && numAmount >= 1000) {
      // Use compact notation for large amounts
      formattedAmount = formatNumber(numAmount, { decimals: 2, compact: true, locale })
    } else if (numAmount < 0.001) {
      // Use scientific notation for very small amounts
      formattedAmount = numAmount.toExponential(2)
    } else if (numAmount < 1) {
      // More decimals for amounts less than 1
      formattedAmount = formatNumber(numAmount, { decimals: Math.min(decimals + 2, 8), locale })
    } else {
      // Standard formatting
      formattedAmount = formatNumber(numAmount, { decimals, locale })
    }

    return `${formattedAmount}${symbol ? ' ' + symbol : ''}`
  }

  /**
   * Format percentage values
   * Consolidated from useFormattedNumbers.js lines 879-907
   * @param {number|string} value - Percentage value
   * @param {object} options - Percentage formatting options
   * @returns {string} Formatted percentage
   */
  const formatPercentage = (value, options = {}) => {
    const {
      decimals = 2,
      locale = 'en-US',
      multiply100 = true,
      showSign = false
    } = options

    if (value === null || value === undefined || value === '') {
      return '0%'
    }

    const numValue = typeof value === 'string' ? parseFloat(value) : value
    
    if (isNaN(numValue)) {
      return '0%'
    }

    try {
      const formatOptions = {
        style: 'percent',
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals
      }

      if (showSign) {
        formatOptions.signDisplay = 'always'
      }

      return new Intl.NumberFormat(locale, formatOptions).format(multiply100 ? numValue : numValue / 100)
    } catch (error) {
      const displayValue = multiply100 ? numValue * 100 : numValue
      const sign = showSign && displayValue > 0 ? '+' : ''
      return `${sign}${displayValue.toFixed(decimals)}%`
    }
  }

  /**
   * Format file sizes (bytes to human readable)
   * @param {number} bytes - Size in bytes
   * @param {object} options - Formatting options
   * @returns {string} Formatted file size
   */
  const formatFileSize = (bytes, options = {}) => {
    const { decimals = 1, binary = false } = options
    
    if (bytes === 0) return '0 B'
    
    const k = binary ? 1024 : 1000
    const sizes = binary 
      ? ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB']
      : ['B', 'KB', 'MB', 'GB', 'TB', 'PB']
    
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    const size = bytes / Math.pow(k, i)
    
    return `${size.toFixed(decimals)} ${sizes[i]}`
  }

  /**
   * Format duration from seconds to human readable
   * @param {number} seconds - Duration in seconds
   * @param {object} options - Formatting options
   * @returns {string} Formatted duration
   */
  const formatDuration = (seconds, options = {}) => {
    const { 
      format = 'long', // 'short', 'long', 'compact'
      units = ['days', 'hours', 'minutes', 'seconds']
    } = options

    if (seconds < 0) return '0s'
    
    const intervals = {
      days: 86400,
      hours: 3600,
      minutes: 60,
      seconds: 1
    }

    const parts = []
    let remaining = Math.floor(seconds)

    for (const unit of units) {
      const value = Math.floor(remaining / intervals[unit])
      if (value > 0) {
        remaining -= value * intervals[unit]
        
        if (format === 'short') {
          parts.push(`${value}${unit[0]}`)
        } else if (format === 'compact') {
          parts.push(`${value}${unit.slice(0, 1)}`)
        } else {
          parts.push(`${value} ${unit === 'seconds' && value === 1 ? 'second' : unit}`)
        }
      }
    }

    return parts.length > 0 ? parts.join(' ') : '0s'
  }

  /**
   * Format time ago (relative time)
   * @param {Date|string|number} date - Date to format
   * @param {object} options - Formatting options
   * @returns {string} Relative time string
   */
  const formatTimeAgo = (date, options = {}) => {
    const { locale = 'en-US' } = options
    
    const now = new Date()
    const target = new Date(date)
    const diffSeconds = Math.floor((now - target) / 1000)

    if (diffSeconds < 60) return 'just now'
    if (diffSeconds < 3600) return `${Math.floor(diffSeconds / 60)}m ago`
    if (diffSeconds < 86400) return `${Math.floor(diffSeconds / 3600)}h ago`
    if (diffSeconds < 2592000) return `${Math.floor(diffSeconds / 86400)}d ago`
    
    // For older dates, show actual date
    try {
      return new Intl.DateTimeFormat(locale, { 
        month: 'short', 
        day: 'numeric' 
      }).format(target)
    } catch (error) {
      return target.toLocaleDateString()
    }
  }

  /**
   * Format wallet addresses (truncate with ellipsis)
   * @param {string} address - Wallet address
   * @param {object} options - Formatting options
   * @returns {string} Formatted address
   */
  const formatAddress = (address, options = {}) => {
    const { prefixLength = 6, suffixLength = 4, separator = '...' } = options
    
    if (!address || typeof address !== 'string') {
      return ''
    }
    
    if (address.length <= prefixLength + suffixLength + separator.length) {
      return address
    }
    
    return `${address.slice(0, prefixLength)}${separator}${address.slice(-suffixLength)}`
  }

  /**
   * Format transaction hashes
   * @param {string} hash - Transaction hash
   * @param {object} options - Formatting options
   * @returns {string} Formatted hash
   */
  const formatTransactionHash = (hash, options = {}) => {
    return formatAddress(hash, { prefixLength: 8, suffixLength: 6, ...options })
  }

  // Return all utility functions
  return {
    // Core formatting
    formatNumber,
    formatCurrency,
    formatTokenAmount,
    formatPercentage,
    
    // Specialized formatting
    formatFileSize,
    formatDuration,
    formatTimeAgo,
    formatAddress,
    formatTransactionHash
  }
}

// Named exports for direct import
export const {
  formatNumber,
  formatCurrency,
  formatTokenAmount,
  formatPercentage,
  formatFileSize,
  formatDuration,
  formatTimeAgo,
  formatAddress,
  formatTransactionHash
} = useFormattingUtils()