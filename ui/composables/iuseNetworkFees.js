// Network fee calculation composable
import { computed } from 'vue'
import { useSwapFormatting } from './features/useSwapFormatting.js'

export function iuseNetworkFees() {
  const { hexToBigInt } = useSwapFormatting()
  
  const GAS_ESTIMATES = {
    approval: 50000,       // conservative ERC-20 approve
    liquid: 180000,        // liquid swap placeholder
    otc: 220000            // otc (mint + vesting) placeholder
  }
  
  const getEstimatedGasUnits = (activeTab, inputToken) => {
    const base = activeTab === 'otc' ? GAS_ESTIMATES.otc : GAS_ESTIMATES.liquid
    // If paying with ERC-20 (non-ETH), add approval buffer
    const needsApproval = ['USDC', 'USDT'].includes(inputToken)
    return base + (needsApproval ? GAS_ESTIMATES.approval : 0)
  }
  
  const calculateNetworkFee = (gasPriceWeiHex, estimatedGasUnits, livePrices) => {
    const gasPriceWei = hexToBigInt(gasPriceWeiHex)
    if (gasPriceWei === 0n || !estimatedGasUnits) return { eth: '0.0000', usd: '0.00' }
    const feeWei = gasPriceWei * BigInt(estimatedGasUnits)
    // Convert wei to ETH: divide by 1e18 using number math safely for display
    const feeEth = Number(feeWei) / 1e18
    const feeEthSafe = isFinite(feeEth) ? feeEth : 0
    const ethUsd = livePrices?.ETH || 0
    const feeUsd = feeEthSafe * ethUsd
    return {
      eth: feeEthSafe.toFixed(5),
      usd: feeUsd.toFixed(2)
    }
  }
  
  return {
    GAS_ESTIMATES,
    getEstimatedGasUnits,
    calculateNetworkFee
  }
}