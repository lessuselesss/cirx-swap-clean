<template>
  <div class="py-8">
    <div class="max-w-4xl mx-auto">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">
          Liquidity Pools
        </h1>
        <p class="text-gray-600">
          Provide liquidity to earn fees from trades
        </p>
      </div>

      <!-- Pool Actions -->
      <div class="flex justify-center gap-4 mb-8">
        <UButton 
          @click="showAddLiquidity = true"
          size="lg"
          class="px-6"
        >
          Add Liquidity
        </UButton>
        <UButton 
          variant="outline" 
          size="lg" 
          class="px-6"
          @click="showCreatePool = true"
        >
          Create Pool
        </UButton>
      </div>

      <!-- My Positions -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">My Positions</h2>
        
        <div v-if="userPositions.length === 0" class="text-center py-8">
          <Icon name="heroicons:banknotes" class="w-16 h-16 text-gray-300 mx-auto mb-4" />
          <p class="text-gray-500 mb-2">No liquidity positions found</p>
          <p class="text-sm text-gray-400">
            Add liquidity to a pool to start earning fees
          </p>
        </div>

        <div v-else class="space-y-4">
          <div 
            v-for="position in userPositions" 
            :key="position.id"
            class="border border-gray-200 rounded-lg p-4"
          >
            <div class="flex justify-between items-start mb-2">
              <div class="flex items-center gap-2">
                <div class="flex -space-x-2">
                  <img 
                    v-if="position.token0.logo"
                    :src="position.token0.logo" 
                    :alt="position.token0.symbol"
                    class="w-8 h-8 rounded-full border-2 border-white"
                  />
                  <img 
                    v-if="position.token1.logo"
                    :src="position.token1.logo" 
                    :alt="position.token1.symbol"
                    class="w-8 h-8 rounded-full border-2 border-white"
                  />
                </div>
                <span class="font-medium">
                  {{ position.token0.symbol }}/{{ position.token1.symbol }}
                </span>
                <UBadge :color="position.inRange ? 'green' : 'red'" size="xs">
                  {{ position.inRange ? 'In Range' : 'Out of Range' }}
                </UBadge>
              </div>
              <UDropdown :items="positionActions">
                <UButton variant="ghost" size="sm" icon="heroicons:ellipsis-vertical" />
              </UDropdown>
            </div>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-gray-500">Liquidity:</span>
                <span class="ml-2 font-medium">${{ position.liquidity.toLocaleString() }}</span>
              </div>
              <div>
                <span class="text-gray-500">Fees Earned:</span>
                <span class="ml-2 font-medium text-green-600">${{ position.feesEarned.toLocaleString() }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Available Pools -->
      <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-semibold mb-4">All Pools</h2>
        
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="text-left py-3 px-2 font-medium text-gray-600">Pool</th>
                <th class="text-right py-3 px-2 font-medium text-gray-600">TVL</th>
                <th class="text-right py-3 px-2 font-medium text-gray-600">24h Volume</th>
                <th class="text-right py-3 px-2 font-medium text-gray-600">24h Fees</th>
                <th class="text-right py-3 px-2 font-medium text-gray-600">APR</th>
                <th class="text-right py-3 px-2 font-medium text-gray-600"></th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="pool in pools" 
                :key="pool.id"
                class="border-b border-gray-100 hover:bg-gray-50"
              >
                <td class="py-4 px-2">
                  <div class="flex items-center gap-2">
                    <div class="flex -space-x-2">
                      <img 
                        v-if="pool.token0.logo"
                        :src="pool.token0.logo" 
                        :alt="pool.token0.symbol"
                        class="w-6 h-6 rounded-full border border-white"
                      />
                      <img 
                        v-if="pool.token1.logo"
                        :src="pool.token1.logo" 
                        :alt="pool.token1.symbol"
                        class="w-6 h-6 rounded-full border border-white"
                      />
                    </div>
                    <span class="font-medium">
                      {{ pool.token0.symbol }}/{{ pool.token1.symbol }}
                    </span>
                    <UBadge size="xs" color="gray">
                      {{ pool.fee }}%
                    </UBadge>
                  </div>
                </td>
                <td class="py-4 px-2 text-right font-medium">
                  ${{ pool.tvl.toLocaleString() }}
                </td>
                <td class="py-4 px-2 text-right">
                  ${{ pool.volume24h.toLocaleString() }}
                </td>
                <td class="py-4 px-2 text-right">
                  ${{ pool.fees24h.toLocaleString() }}
                </td>
                <td class="py-4 px-2 text-right font-medium text-green-600">
                  {{ pool.apr.toFixed(2) }}%
                </td>
                <td class="py-4 px-2 text-right">
                  <UButton 
                    size="sm" 
                    variant="outline"
                    @click="addLiquidityToPool(pool)"
                  >
                    Add
                  </UButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Page metadata
definePageMeta({
  title: 'Liquidity Pools',
  layout: 'default'
})

// Reactive state
const showAddLiquidity = ref(false)
const showCreatePool = ref(false)

// Mock data - replace with actual Web3 data
const userPositions = ref([
  // Example positions would go here
])

const pools = ref([
  {
    id: 1,
    token0: { symbol: 'ETH', name: 'Ethereum', logo: null },
    token1: { symbol: 'USDC', name: 'USD Coin', logo: null },
    fee: 0.3,
    tvl: 125000000,
    volume24h: 45000000,
    fees24h: 135000,
    apr: 12.5
  },
  {
    id: 2,
    token0: { symbol: 'WBTC', name: 'Wrapped Bitcoin', logo: null },
    token1: { symbol: 'ETH', name: 'Ethereum', logo: null },
    fee: 0.3,
    tvl: 87000000,
    volume24h: 28000000,
    fees24h: 84000,
    apr: 15.2
  },
  {
    id: 3,
    token0: { symbol: 'DAI', name: 'Dai Stablecoin', logo: null },
    token1: { symbol: 'USDC', name: 'USD Coin', logo: null },
    fee: 0.05,
    tvl: 156000000,
    volume24h: 12000000,
    fees24h: 6000,
    apr: 2.8
  }
])

const positionActions = [
  [{
    label: 'Add Liquidity',
    icon: 'heroicons:plus',
    click: () => console.log('Add liquidity')
  }],
  [{
    label: 'Remove Liquidity',
    icon: 'heroicons:minus',
    click: () => console.log('Remove liquidity')
  }],
  [{
    label: 'Collect Fees',
    icon: 'heroicons:banknotes',
    click: () => console.log('Collect fees')
  }]
]

// Methods
const addLiquidityToPool = (pool) => {
  // TODO: Implement add liquidity modal with selected pool
  console.log('Adding liquidity to pool:', pool)
  showAddLiquidity.value = true
}

// Head configuration
useHead({
  title: 'Liquidity Pools - Circular CIRX',
  meta: [
    { name: 'description', content: 'Provide liquidity to earn fees from trades on our decentralized exchange protocol.' }
  ]
})
</script>