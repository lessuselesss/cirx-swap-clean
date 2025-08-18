#!/usr/bin/env node

// ~~~ File Marked for Refactor/Migration 2025-08-16 ~~~~

// Simple test script to verify OTC dropdown integration
// Run with: node debug-otc-dropdown.js

console.log('🔍 Testing OTC Dropdown Integration...')

// Test 1: Check if all files exist
import { existsSync } from 'fs'
import { readFileSync } from 'fs'

const files = [
  'components/OtcDiscountDropdown.vue',
  'components/SwapForm.vue', 
  'components/SwapOutput.vue',
  'composables/useOtcConfig.js',
  'public/swap/discount.json'
]

console.log('\n📁 File Existence Check:')
files.forEach(file => {
  const exists = existsSync(file)
  console.log(`${exists ? '✅' : '❌'} ${file}`)
})

// Test 2: Check discount.json data
console.log('\n📊 Discount Tiers Data:')
try {
  const discountData = JSON.parse(readFileSync('public/swap/discount.json', 'utf8'))
  console.log('✅ Discount tiers loaded:', discountData.discountTiers.length, 'tiers')
  discountData.discountTiers.forEach((tier, i) => {
    console.log(`   Tier ${i+1}: ${tier.discount}% ($${tier.minAmount.toLocaleString()}+)`)
  })
} catch (err) {
  console.log('❌ Failed to load discount data:', err.message)
}

// Test 3: Analyze component integration
console.log('\n🔗 Component Integration Analysis:')

// Check SwapOutput for dropdown integration
try {
  const swapOutput = readFileSync('components/SwapOutput.vue', 'utf8')
  
  const hasDropdownImport = swapOutput.includes('import OtcDiscountDropdown')
  const hasConditionalRender = swapOutput.includes('v-if="activeTab === \'otc\' && discountTiers')
  const hasPropsBinding = swapOutput.includes(':discount-tiers="discountTiers"')
  
  console.log(`${hasDropdownImport ? '✅' : '❌'} OtcDiscountDropdown import`)
  console.log(`${hasConditionalRender ? '✅' : '❌'} Conditional rendering logic`)
  console.log(`${hasPropsBinding ? '✅' : '❌'} Props binding`)
  
} catch (err) {
  console.log('❌ Failed to analyze SwapOutput:', err.message)
}

// Test 4: Check SwapForm integration
try {
  const swapForm = readFileSync('components/SwapForm.vue', 'utf8')
  
  const hasOtcConfigImport = swapForm.includes('useOtcConfig')
  const hasDiscountTiersDestructure = swapForm.includes('discountTiers } = useOtcConfig')
  const hasPropsPass = swapForm.includes(':discount-tiers="discountTiers"')
  
  console.log(`${hasOtcConfigImport ? '✅' : '❌'} useOtcConfig usage`)
  console.log(`${hasDiscountTiersDestructure ? '✅' : '❌'} discountTiers destructuring`)
  console.log(`${hasPropsPass ? '✅' : '❌'} Props passing to SwapOutput`)
  
} catch (err) {
  console.log('❌ Failed to analyze SwapForm:', err.message)
}

console.log('\n🎯 Recommendations:')
console.log('1. Switch to OTC tab and check browser console for debug logs')
console.log('2. Ensure useOtcConfig composable is properly imported')
console.log('3. Add debug logging to trace discountTiers reactivity')
console.log('4. Check that async data loading doesn\'t cause race conditions')

console.log('\n✅ Integration analysis complete!')