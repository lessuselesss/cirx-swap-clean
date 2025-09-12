// Token helper functions composable
export function iuseTokenHelpers() {
  const getTokenLogo = (token) => {
    const logoMap = {
      'ETH': 'https://assets.coingecko.com/coins/images/279/small/ethereum.png',
      'USDC': 'https://assets.coingecko.com/coins/images/6319/small/USD_Coin_icon.png',
      'USDT': 'https://assets.coingecko.com/coins/images/325/small/Tether.png',
      'SOL': 'https://assets.coingecko.com/coins/images/4128/small/solana.png',
      'USDC_SOL': 'https://assets.coingecko.com/coins/images/6319/small/USD_Coin_icon.png',
      'CIRX': '/buy/cirx-icon.svg'
    }
    
    return logoMap[token] || 'https://assets.coingecko.com/coins/images/279/small/ethereum.png'
  }
  
  const getTokenSymbol = (token) => {
    const symbolMap = {
      'ETH': 'ETH',
      'USDC': 'USDC',
      'USDT': 'USDT',
      'SOL': 'SOL',
      'USDC_SOL': 'USDC',
      'CIRX': 'CIRX'
    }
    
    return symbolMap[token] || token
  }
  
  return {
    getTokenLogo,
    getTokenSymbol
  }
}