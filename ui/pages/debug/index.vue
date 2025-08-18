<template>
  <div class="min-h-screen bg-circular-bg-primary p-8">
    <div class="max-w-6xl mx-auto">
      <h1 class="text-4xl font-bold text-white mb-8">🛠️ Debug Dashboard</h1>
      
      <!-- Status Overview -->
      <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold text-white mb-4">System Status</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <span class="text-gray-300">Frontend</span>
              <span class="text-green-400 text-xl">✅</span>
            </div>
            <p class="text-sm text-gray-400 mt-1">Nuxt dev server running</p>
          </div>
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <span class="text-gray-300">Backend API</span>
              <span class="text-yellow-400 text-xl">⚠️</span>
            </div>
            <p class="text-sm text-gray-400 mt-1">Ready to test with Sepolia ETH</p>
          </div>
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <span class="text-gray-300">Wallet Integration</span>
              <span class="text-green-400 text-xl">✅</span>
            </div>
            <p class="text-sm text-gray-400 mt-1">MetaMask connection working</p>
          </div>
        </div>
      </div>

      <!-- Debug Pages Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- Frontend Debug Pages -->
        <div class="bg-gray-800 rounded-lg p-6">
          <h3 class="text-xl font-semibold text-white mb-4">🖥️ Frontend Debug</h3>
          <div class="space-y-3">
            <NuxtLink 
              to="/debug/debug-button" 
              class="block p-3 bg-blue-600 hover:bg-blue-700 rounded-lg text-white transition-colors"
            >
              <div class="font-medium">Button Debug</div>
              <div class="text-sm text-blue-200">Test Buy Liquid CIRX button states</div>
            </NuxtLink>
            
            <NuxtLink 
              to="/debug/swap-broken" 
              class="block p-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white transition-colors"
            >
              <div class="font-medium">Swap Components</div>
              <div class="text-sm text-purple-200">Legacy swap component testing</div>
            </NuxtLink>
            
            <NuxtLink 
              to="/debug/test-transaction-status" 
              class="block p-3 bg-green-600 hover:bg-green-700 rounded-lg text-white transition-colors"
            >
              <div class="font-medium">Transaction Status</div>
              <div class="text-sm text-green-200">Test transaction tracking flow</div>
            </NuxtLink>
          </div>
        </div>

        <!-- Wallet Debug Pages -->
        <div class="bg-gray-800 rounded-lg p-6">
          <h3 class="text-xl font-semibold text-white mb-4">👛 Wallet Debug</h3>
          <div class="space-y-3">
            <NuxtLink 
              to="/debug/circular-wallet" 
              class="block p-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white transition-colors"
            >
              <div class="font-medium">🪐 Circular Chain Wallet</div>
              <div class="text-sm text-purple-200">Test Saturn wallet & CIRX balance functions</div>
            </NuxtLink>
            
            <NuxtLink 
              to="/debug/wallet-test" 
              class="block p-3 bg-orange-600 hover:bg-orange-700 rounded-lg text-white transition-colors"
            >
              <div class="font-medium">Reown AppKit</div>
              <div class="text-sm text-orange-200">Test Ethereum wallet connection</div>
            </NuxtLink>
            
            <div class="p-3 bg-gray-700 rounded-lg">
              <div class="font-medium text-gray-300">Wallet Scripts</div>
              <div class="text-sm text-gray-400 mt-1">
                <a href="/debug/scripts/debug-wallet.js" target="_blank" class="text-blue-400 hover:text-blue-300">
                  debug-wallet.js
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Chart Debug Pages -->
        <div class="bg-gray-800 rounded-lg p-6">
          <h3 class="text-xl font-semibold text-white mb-4">📈 Chart Debug</h3>
          <div class="space-y-3">
            <NuxtLink 
              to="/debug/chart-test" 
              class="block p-3 bg-yellow-600 hover:bg-yellow-700 rounded-lg text-white transition-colors"
            >
              <div class="font-medium">TradingView Charts</div>
              <div class="text-sm text-yellow-200">Test TradingView integration</div>
            </NuxtLink>
            
            <NuxtLink 
              to="/debug/extensions-test" 
              class="block p-3 bg-indigo-600 hover:bg-indigo-700 rounded-lg text-white transition-colors"
            >
              <div class="font-medium">Extensions Test</div>
              <div class="text-sm text-indigo-200">Test browser extensions compatibility</div>
            </NuxtLink>
          </div>
        </div>

        <!-- Backend Debug -->
        <div class="bg-gray-800 rounded-lg p-6">
          <h3 class="text-xl font-semibold text-white mb-4">⚙️ Backend Debug</h3>
          <div class="space-y-3">
            <NuxtLink 
              to="/debug/backend-wallet" 
              class="block p-3 bg-red-600 hover:bg-red-700 rounded-lg text-white transition-colors"
            >
              <div class="font-medium">Hot Wallet Debug</div>
              <div class="text-sm text-red-200">Test Circular chain wallet & transactions</div>
            </NuxtLink>
            
            <div class="p-3 bg-gray-700 rounded-lg">
              <div class="font-medium text-gray-300">API Status</div>
              <div class="text-sm text-gray-400 mt-1">
                Backend endpoints ready for Sepolia testing
              </div>
            </div>
          </div>
        </div>

        <!-- Console Scripts -->
        <div class="bg-gray-800 rounded-lg p-6">
          <h3 class="text-xl font-semibold text-white mb-4">🔍 Console Scripts</h3>
          <div class="space-y-3">
            <div class="p-3 bg-gray-700 rounded-lg">
              <div class="font-medium text-gray-300">OTC Dropdown Debug</div>
              <div class="text-sm text-gray-400 mt-1">
                <a href="/debug/scripts/debug-console-commands.js" target="_blank" class="text-blue-400 hover:text-blue-300">
                  console-commands.js
                </a>
              </div>
            </div>
            
            <div class="p-3 bg-gray-700 rounded-lg">
              <div class="font-medium text-gray-300">OTC Integration</div>
              <div class="text-sm text-gray-400 mt-1">
                <a href="/debug/scripts/debug-otc-dropdown.js" target="_blank" class="text-blue-400 hover:text-blue-300">
                  otc-dropdown.js
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Manual Tests -->
        <div class="bg-gray-800 rounded-lg p-6">
          <h3 class="text-xl font-semibold text-white mb-4">🧪 Manual Tests</h3>
          <div class="space-y-3">
            <div class="p-3 bg-gray-700 rounded-lg">
              <div class="font-medium text-gray-300">Console Test Page</div>
              <div class="text-sm text-gray-400 mt-1">
                <a href="/debug/manual-console-test.html" target="_blank" class="text-blue-400 hover:text-blue-300">
                  manual-console-test.html
                </a>
              </div>
            </div>
            
            <div class="p-3 bg-gray-700 rounded-lg">
              <div class="font-medium text-gray-300">OTC Visibility Test</div>
              <div class="text-sm text-gray-400 mt-1">
                <a href="/debug/test-otc-visibility.html" target="_blank" class="text-blue-400 hover:text-blue-300">
                  otc-visibility.html
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Work Summary -->
      <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold text-white mb-4">📋 Recent Work Summary</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-lg font-medium text-green-400 mb-3">✅ Completed</h3>
            <ul class="space-y-2 text-sm text-gray-300">
              <li>• Fixed Buy Liquid CIRX button click issues</li>
              <li>• Replaced button element with div for proper event handling</li>
              <li>• Fixed address input focus targeting</li>
              <li>• Added comprehensive transaction flow debugging</li>
              <li>• Organized all debug pages into centralized infrastructure</li>
            </ul>
          </div>
          <div>
            <h3 class="text-lg font-medium text-yellow-400 mb-3">⏳ Pending</h3>
            <ul class="space-y-2 text-sm text-gray-300">
              <li>• Fix 'failed to fetch' error after MetaMask transactions (needs Sepolia ETH)</li>
              <li>• Create backend debug page for hot wallet logic</li>
              <li>• Clean up duplicate swap components</li>
              <li>• Test complete transaction flow end-to-end</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-2xl font-semibold text-white mb-4">⚡ Quick Actions</h2>
        <div class="flex flex-wrap gap-4">
          <NuxtLink 
            to="/swap" 
            class="px-6 py-3 bg-circular-primary text-gray-900 rounded-lg font-medium hover:bg-circular-primary-hover transition-colors"
          >
            Go to Main Swap Page
          </NuxtLink>
          
          <button 
            @click="openConsole"
            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors"
          >
            Open Browser Console
          </button>
          
          <button 
            @click="clearStorage"
            class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors"
          >
            Clear LocalStorage
          </button>
          
          <button 
            @click="refreshPage"
            class="px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors"
          >
            Hard Refresh
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Page metadata
definePageMeta({
  title: 'Debug Dashboard',
  description: 'Centralized debug tools and utilities for CIRX swap development',
  ssr: false
})

// Quick action functions
const openConsole = () => {
  alert('Press F12 or Ctrl+Shift+I (Cmd+Option+I on Mac) to open the browser console')
}

const clearStorage = () => {
  if (confirm('Clear all localStorage data? This will reset wallet connections and preferences.')) {
    localStorage.clear()
    sessionStorage.clear()
    location.reload()
  }
}

const refreshPage = () => {
  location.reload(true)
}

// Head configuration
useHead({
  title: 'Debug Dashboard - CIRX Swap',
  meta: [
    { 
      name: 'description', 
      content: 'Centralized debug tools and utilities for CIRX swap application development' 
    }
  ]
})
</script>