// Network configuration management composable
import { ref, computed } from 'vue'

export function iuseNetworkConfig() {
  const networkConfig = ref({
    network: 'testnet',
    chain_name: 'Circular SandBox',
    environment: 'development'
  })
  
  const dynamicPlaceholder = computed(() => {
    const network = networkConfig.value?.network || 'testnet'
    const chainName = networkConfig.value?.chain_name || 'Circular SandBox'
    
    // Capitalize network names: mainnet -> Mainnet, testnet -> Testnet, devnet -> Devnet
    const capitalizedNetwork = network.charAt(0).toUpperCase() + network.slice(1).toLowerCase()
    
    return `Enter a ${capitalizedNetwork} (${chainName}) Wallet Address`
  })
  
  const fetchNetworkConfig = async () => {
    try {
      const config = useRuntimeConfig()
      const apiBaseUrl = config.public.apiBaseUrl || 'http://localhost:18423/v1'
      
      const response = await fetch(`${apiBaseUrl}/config/circular-network`)
      if (response.ok) {
        const data = await response.json()
        networkConfig.value = data
        console.log('🔗 Network config loaded for placeholder:', {
          network: data.network,
          chain: data.chain_name
        })
      } else {
        console.warn('Failed to fetch network config, using fallback placeholder')
      }
    } catch (error) {
      console.warn('Network config fetch error:', error.message)
      // networkConfig.value remains null, so fallback placeholder will be used
    }
  }
  
  return {
    networkConfig,
    dynamicPlaceholder,
    fetchNetworkConfig
  }
}