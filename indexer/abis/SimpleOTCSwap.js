// SimpleOTCSwap contract ABI (extracted from the contract)
export const SimpleOTCSwapABI = [
  // Events
  {
    type: 'event',
    name: 'LiquidSwap',
    inputs: [
      { name: 'user', type: 'address', indexed: true },
      { name: 'inputToken', type: 'address', indexed: true },
      { name: 'inputAmount', type: 'uint256', indexed: false },
      { name: 'cirxAmount', type: 'uint256', indexed: false }
    ]
  },
  {
    type: 'event',
    name: 'OTCSwap',
    inputs: [
      { name: 'user', type: 'address', indexed: true },
      { name: 'inputToken', type: 'address', indexed: true },
      { name: 'inputAmount', type: 'uint256', indexed: false },
      { name: 'cirxAmount', type: 'uint256', indexed: false },
      { name: 'discountBps', type: 'uint256', indexed: false }
    ]
  },
  {
    type: 'event',
    name: 'TokenSupported',
    inputs: [
      { name: 'token', type: 'address', indexed: true },
      { name: 'price', type: 'uint256', indexed: false }
    ]
  },
  {
    type: 'event',
    name: 'PriceUpdated',
    inputs: [
      { name: 'token', type: 'address', indexed: true },
      { name: 'newPrice', type: 'uint256', indexed: false }
    ]
  },
  {
    type: 'event',
    name: 'DiscountTierAdded',
    inputs: [
      { name: 'minAmount', type: 'uint256', indexed: false },
      { name: 'discountBps', type: 'uint256', indexed: false }
    ]
  },

  // Read-only functions for additional data
  {
    type: 'function',
    name: 'supportedTokens',
    stateMutability: 'view',
    inputs: [{ name: '', type: 'address' }],
    outputs: [{ name: '', type: 'bool' }]
  },
  {
    type: 'function',
    name: 'tokenPrices',
    stateMutability: 'view',
    inputs: [{ name: '', type: 'address' }],
    outputs: [{ name: '', type: 'uint256' }]
  }
];