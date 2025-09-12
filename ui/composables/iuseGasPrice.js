// Gas price fetching and management composable
import { ref } from 'vue'

export function iuseGasPrice() {
  const gasPriceWeiHex = ref('0x0')
  const isGasRefreshing = ref(false)
  
  const fetchGasPrice = async () => {
    try {
      isGasRefreshing.value = true
      // Prefer wallet provider if available
      if (typeof window !== 'undefined' && window.ethereum?.request) {
        const gp = await window.ethereum.request({ method: 'eth_gasPrice' })
        if (gp) gasPriceWeiHex.value = gp
      } else {
        // Fallback to public RPC
        const res = await fetch('https://ethereum.publicnode.com', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'eth_gasPrice', params: [] })
        })
        const json = await res.json()
        if (json?.result) gasPriceWeiHex.value = json.result
      }
    } catch (e) {
      console.warn('Gas price fetch failed', e)
    } finally {
      isGasRefreshing.value = false
    }
  }
  
  return {
    gasPriceWeiHex,
    isGasRefreshing,
    fetchGasPrice
  }
}