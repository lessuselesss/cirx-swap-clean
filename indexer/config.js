// Event Indexing Service Configuration
export const config = {
  // Blockchain connection
  rpc: {
    url: process.env.RPC_URL || 'http://localhost:8545', // Anvil default
    chain: 'localhost', // or 'mainnet', 'sepolia', etc.
    pollingInterval: 2000, // 2 seconds
  },

  // Contract addresses (update when deployed)
  contracts: {
    SimpleOTCSwap: process.env.OTC_SWAP_ADDRESS || '0x0000000000000000000000000000000000000000',
    VestingContract: process.env.VESTING_CONTRACT_ADDRESS || '0x0000000000000000000000000000000000000000',
    CIRXToken: process.env.CIRX_TOKEN_ADDRESS || '0x0000000000000000000000000000000000000000',
  },

  // Database configuration (using SQLite for simplicity)
  database: {
    path: './indexer/data/transactions.db',
    tables: {
      swaps: 'swaps',
      vestingPositions: 'vesting_positions',
      claims: 'claims',
    }
  },

  // API server configuration  
  api: {
    port: process.env.INDEXER_PORT || 3001,
    host: 'localhost',
  },

  // Events to index
  events: {
    swap: ['LiquidSwap', 'OTCSwap'],
    vesting: ['VestingPositionCreated', 'TokensClaimed'],
    token: ['TokenSupported', 'PriceUpdated'],
  },

  // Starting block (0 for genesis, or specific block number)
  startBlock: process.env.START_BLOCK || 0,
};