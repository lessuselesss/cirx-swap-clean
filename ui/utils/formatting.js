/**
 * Formatting utilities
 * Consolidates duplicate formatting logic across the codebase
 */

/**
 * Format numbers with proper locale and options
 * @param {number|string} value - Value to format
 * @param {object} options - Formatting options
 * @returns {string} Formatted number
 */
export function formatNumber(value, options = {}) {
  const {
    decimals = 2,
    locale = 'en-US',
    style = 'decimal',
    currency = 'USD',
    compact = false,
    showSign = false
  } = options

  if (value === null || value === undefined || value === '') {
    return '0'
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value
  
  if (isNaN(numValue)) {
    return '0'
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
 * Format token amounts with appropriate decimal places
 * @param {number|string} amount - Token amount
 * @param {object} options - Formatting options
 * @returns {string} Formatted token amount
 */
export function formatTokenAmount(amount, options = {}) {
  const {
    decimals = 6,
    symbol = '',
    compact = false,
    showFullPrecision = false
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
    formattedAmount = formatNumber(numAmount, { decimals: 2, compact: true })
  } else if (numAmount < 0.001) {
    // Use scientific notation for very small amounts
    formattedAmount = numAmount.toExponential(2)
  } else if (numAmount < 1) {
    // More decimals for amounts less than 1
    formattedAmount = formatNumber(numAmount, { decimals: Math.min(decimals + 2, 8) })
  } else {
    // Standard formatting
    formattedAmount = formatNumber(numAmount, { decimals })
  }

  return `${formattedAmount}${symbol ? ' ' + symbol : ''}`
}

/**
 * Format currency amounts
 * @param {number|string} amount - Currency amount
 * @param {object} options - Formatting options
 * @returns {string} Formatted currency
 */
export function formatCurrency(amount, options = {}) {
  const {
    currency = 'USD',
    locale = 'en-US',
    compact = false
  } = options

  return formatNumber(amount, {
    style: 'currency',
    currency,
    locale,
    compact,
    decimals: 2
  })
}

/**
 * Format percentage values
 * @param {number|string} value - Percentage value (0.15 for 15%)
 * @param {object} options - Formatting options
 * @returns {string} Formatted percentage
 */
export function formatPercentage(value, options = {}) {
  const {
    decimals = 2,
    locale = 'en-US',
    multiply100 = true
  } = options

  if (value === null || value === undefined || value === '') {
    return '0%'
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value
  
  if (isNaN(numValue)) {
    return '0%'
  }

  const displayValue = multiply100 ? numValue * 100 : numValue

  try {
    return new Intl.NumberFormat(locale, {
      style: 'percent',
      minimumFractionDigits: 0,
      maximumFractionDigits: decimals
    }).format(multiply100 ? numValue : numValue / 100)
  } catch (error) {
    return `${displayValue.toFixed(decimals)}%`
  }
}

/**
 * Format time durations
 * @param {number} seconds - Duration in seconds
 * @param {object} options - Formatting options
 * @returns {string} Formatted duration
 */
export function formatDuration(seconds, options = {}) {
  const {
    format = 'auto', // 'auto', 'short', 'long'
    showSeconds = true
  } = options

  if (!seconds || seconds <= 0) {
    return format === 'long' ? '0 seconds' : '0s'
  }

  const units = [
    { label: format === 'long' ? 'year' : 'y', seconds: 31536000 },
    { label: format === 'long' ? 'month' : 'mo', seconds: 2592000 },
    { label: format === 'long' ? 'day' : 'd', seconds: 86400 },
    { label: format === 'long' ? 'hour' : 'h', seconds: 3600 },
    { label: format === 'long' ? 'minute' : 'm', seconds: 60 },
    { label: format === 'long' ? 'second' : 's', seconds: 1 }
  ]

  const parts = []
  let remaining = Math.floor(seconds)

  for (const unit of units) {
    if (remaining >= unit.seconds) {
      const count = Math.floor(remaining / unit.seconds)
      remaining -= count * unit.seconds
      
      if (format === 'long') {
        parts.push(`${count} ${unit.label}${count !== 1 ? 's' : ''}`)
      } else {
        parts.push(`${count}${unit.label}`)
      }

      if (format === 'auto' && parts.length >= 2) break
      if (!showSeconds && unit.label.includes('second')) break
    }
  }

  if (parts.length === 0) {
    return format === 'long' ? '0 seconds' : '0s'
  }

  return format === 'long' ? parts.join(', ') : parts.join(' ')
}

/**
 * Format file sizes
 * @param {number} bytes - Size in bytes
 * @param {object} options - Formatting options
 * @returns {string} Formatted file size
 */
export function formatFileSize(bytes, options = {}) {
  const {
    decimals = 1,
    binary = true
  } = options

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
 * Format transaction hashes
 * @param {string} hash - Transaction hash
 * @param {object} options - Formatting options
 * @returns {string} Formatted hash
 */
export function formatTransactionHash(hash, options = {}) {
  const {
    startChars = 8,
    endChars = 6,
    separator = '...'
  } = options

  if (!hash) return ''

  if (hash.length <= startChars + endChars + separator.length) {
    return hash
  }

  return `${hash.slice(0, startChars)}${separator}${hash.slice(-endChars)}`
}

/**
 * Format relative time (time ago)
 * @param {Date|string|number} date - Date to format
 * @param {object} options - Formatting options
 * @returns {string} Formatted relative time
 */
export function formatTimeAgo(date, options = {}) {
  const {
    locale = 'en-US',
    numeric = 'auto' // 'auto', 'always'
  } = options

  if (!date) return ''

  const now = new Date()
  const targetDate = new Date(date)
  
  if (isNaN(targetDate.getTime())) {
    return 'Invalid date'
  }

  try {
    const rtf = new Intl.RelativeTimeFormat(locale, { numeric })
    const diffInSeconds = Math.floor((targetDate.getTime() - now.getTime()) / 1000)

    const units = [
      { unit: 'year', seconds: 31536000 },
      { unit: 'month', seconds: 2592000 },
      { unit: 'day', seconds: 86400 },
      { unit: 'hour', seconds: 3600 },
      { unit: 'minute', seconds: 60 },
      { unit: 'second', seconds: 1 }
    ]

    for (const { unit, seconds } of units) {
      const interval = Math.floor(Math.abs(diffInSeconds) / seconds)
      if (interval >= 1) {
        return rtf.format(diffInSeconds < 0 ? -interval : interval, unit)
      }
    }

    return rtf.format(0, 'second')
  } catch (error) {
    console.warn('Relative time formatting error:', error)
    return targetDate.toLocaleDateString()
  }
}