<template>
  <div class="min-h-screen bg-circular-bg-primary p-8">
    <div class="max-w-4xl mx-auto">
      <h1 class="text-3xl font-bold text-white mb-8">Reown AppKit Wallet Test</h1>
      
      <!-- Wallet Connection Section -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">Wallet Connection</h2>
        <div class="flex items-center gap-4">
          <ReownWalletButton />
        </div>
      </div>

      <!-- Connection Status -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">Connection Status</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-400">Connected:</span>
            <span class="ml-2 font-mono" :class="isConnected ? 'text-green-400' : 'text-red-400'">
              {{ isConnected ? 'Yes' : 'No' }}
            </span>
          </div>
          <div>
            <span class="text-gray-400">Connecting:</span>
            <span class="ml-2 font-mono" :class="isConnecting ? 'text-yellow-400' : 'text-gray-400'">
              {{ isConnecting ? 'Yes' : 'No' }}
            </span>
          </div>
          <div>
            <span class="text-gray-400">Address:</span>
            <span class="ml-2 font-mono text-blue-400">
              {{ address || 'Not connected' }}
            </span>
          </div>
          <div>
            <span class="text-gray-400">Chain ID:</span>
            <span class="ml-2 font-mono text-purple-400">
              {{ chainId || 'Unknown' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Network Information -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6" v-if="caipNetwork">
        <h2 class="text-xl font-semibold text-white mb-4">Network Information</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-400">Network Name:</span>
            <span class="ml-2 text-green-400">{{ caipNetwork.name }}</span>
          </div>
          <div>
            <span class="text-gray-400">Network ID:</span>
            <span class="ml-2 font-mono text-blue-400">{{ caipNetwork.id }}</span>
          </div>
        </div>
      </div>

      <!-- Balance Information -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6" v-if="balance && isConnected">
        <h2 class="text-xl font-semibold text-white mb-4">Balance Information</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-400">Balance:</span>
            <span class="ml-2 text-green-400 font-mono">{{ formattedBalance }}</span>
          </div>
          <div>
            <span class="text-gray-400">Symbol:</span>
            <span class="ml-2 text-blue-400">{{ balance.symbol }}</span>
          </div>
        </div>
      </div>

      <!-- Store Integration Test -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">Reown Store Integration</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-400">Store Connected:</span>
            <span class="ml-2" :class="storeIsConnected ? 'text-green-400' : 'text-red-400'">
              {{ storeIsConnected ? 'Yes' : 'No' }}
            </span>
          </div>
          <div>
            <span class="text-gray-400">Store Address:</span>
            <span class="ml-2 font-mono text-blue-400">
              {{ storeAddress || 'Not connected' }}
            </span>
          </div>
          <div>
            <span class="text-gray-400">Active Wallet Type:</span>
            <span class="ml-2 text-purple-400">
              {{ activeWallet?.type || 'None' }}
            </span>
          </div>
          <div>
            <span class="text-gray-400">Network Name:</span>
            <span class="ml-2 text-green-400">
              {{ networkName || 'Unknown' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-semibold text-white mb-4">Test Actions</h2>
        <div class="flex flex-wrap gap-4">
          <button
            @click="openConnectModal"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
          >
            Open Connect Modal
          </button>
          
          <button
            @click="openAccountModal"
            :disabled="!isConnected"
            class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors"
          >
            Open Account Modal
          </button>
          
          <button
            @click="openNetworksModal"
            :disabled="!isConnected"
            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors"
          >
            Open Networks Modal
          </button>
          
          <button
            @click="refreshData"
            :disabled="!isConnected"
            class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors"
          >
            Refresh Balance
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAppKit, useAppKitAccount, useAppKitNetwork } from '@reown/appkit/vue'
import { useAccount, useBalance } from '@wagmi/vue'
import { formatUnits } from 'viem'
import { useReownWalletStore } from '~/stores/reownWallet'

// Page metadata
definePageMeta({
  title: 'Reown AppKit Test',
  description: 'Test page for Reown AppKit wallet integration',
  ssr: false
})

// Reown AppKit hooks
const { open } = useAppKit()
const { address, isConnected, isConnecting } = useAppKitAccount()
const { caipNetwork, chainId } = useAppKitNetwork()

// Wagmi hooks
const { address: wagmiAddress } = useAccount()
const { data: balance, refetch: refetchBalance } = useBalance({
  address: wagmiAddress,
})

// Store
const reownStore = useReownWalletStore()

// Computed properties
const formattedBalance = computed(() => {
  if (!balance.value) return '0.0'
  
  try {
    const formatted = formatUnits(balance.value.value, balance.value.decimals)
    const amount = parseFloat(formatted)
    return `${amount.toFixed(6)} ${balance.value.symbol}`
  } catch {
    return '0.0'
  }
})

const storeIsConnected = computed(() => reownStore.isConnected)
const storeAddress = computed(() => reownStore.address)
const activeWallet = computed(() => reownStore.activeWallet)
const networkName = computed(() => reownStore.networkName)

// Action functions
const openConnectModal = () => {
  open({ view: 'Connect' })
}

const openAccountModal = () => {
  open({ view: 'Account' })
}

const openNetworksModal = () => {
  open({ view: 'Networks' })
}

const refreshData = async () => {
  try {
    if (refetchBalance) {
      await refetchBalance()
    }
    await reownStore.refreshBalance()
  } catch (error) {
    console.error('Failed to refresh data:', error)
  }
}

// Initialize store
onMounted(() => {
  reownStore.initialize()
})
</script>

<style scoped>
/* Add any custom styles if needed */
</style>