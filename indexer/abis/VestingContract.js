// VestingContract ABI (extracted from the contract)
export const VestingContractABI = [
  // Events
  {
    type: 'event',
    name: 'VestingPositionCreated',
    inputs: [
      { name: 'user', type: 'address', indexed: true },
      { name: 'amount', type: 'uint256', indexed: false },
      { name: 'startTime', type: 'uint256', indexed: false }
    ]
  },
  {
    type: 'event',
    name: 'TokensClaimed',
    inputs: [
      { name: 'user', type: 'address', indexed: true },
      { name: 'amount', type: 'uint256', indexed: false }
    ]
  },
  {
    type: 'event',
    name: 'ContractAuthorized',
    inputs: [
      { name: 'contractAddress', type: 'address', indexed: true }
    ]
  },
  {
    type: 'event',
    name: 'ContractDeauthorized',
    inputs: [
      { name: 'contractAddress', type: 'address', indexed: true }
    ]
  },

  // Read-only functions
  {
    type: 'function',
    name: 'getVestingInfo',
    stateMutability: 'view',
    inputs: [{ name: 'user', type: 'address' }],
    outputs: [
      { name: 'totalAmount', type: 'uint256' },
      { name: 'startTime', type: 'uint256' },
      { name: 'claimedAmount', type: 'uint256' },
      { name: 'claimableAmount', type: 'uint256' },
      { name: 'isActive', type: 'bool' }
    ]
  },
  {
    type: 'function',
    name: 'getClaimableAmount',
    stateMutability: 'view',
    inputs: [{ name: 'user', type: 'address' }],
    outputs: [{ name: 'claimableAmount', type: 'uint256' }]
  },
  {
    type: 'function',
    name: 'VESTING_DURATION',
    stateMutability: 'view',
    inputs: [],
    outputs: [{ name: '', type: 'uint256' }]
  }
];