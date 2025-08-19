<template>
  <div class="min-h-screen bg-circular-bg-primary p-8">
    <div class="max-w-4xl mx-auto">
      <h1 class="text-3xl font-bold text-white mb-8">Simple Chart Test (Lightweight Charts Only)</h1>
      
      <!-- Status -->
      <div class="bg-gray-800 rounded-lg p-4 mb-6">
        <div class="text-white">
          <p><strong>Status:</strong> {{ status }}</p>
          <p><strong>Error:</strong> {{ error || 'None' }}</p>
        </div>
      </div>

      <!-- CirxPriceChart Test -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">CIRX Price Chart (Lightweight Charts)</h2>
        <div class="h-96">
          <CirxPriceChart @close="() => {}" />
        </div>
      </div>

      <!-- Manual Chart Test -->
      <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-semibold text-white mb-4">Manual Lightweight Chart Test</h2>
        <div id="manual-chart" class="h-96 bg-gray-900 border border-gray-600 rounded-lg"></div>
        <button
          @click="createManualChart"
          class="mt-4 px-4 py-2 bg-circular-primary text-gray-900 rounded-lg hover:bg-circular-primary-hover transition-colors"
        >
          Create Manual Chart
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import CirxPriceChart from '~/components/CirxPriceChart.vue'

// Page metadata
definePageMeta({
  title: 'Simple Chart Test',
  description: 'Simple test for Lightweight Charts only',
  ssr: false
})

// State
const status = ref('Loading...')
const error = ref(null)

// Manual chart creation
const createManualChart = async () => {
  try {
    status.value = 'Creating manual chart...'
    
    // Import lightweight charts
    const { createChart, LineSeries } = await import('lightweight-charts')
    
    // Clear existing chart
    const container = document.getElementById('manual-chart')
    container.innerHTML = ''
    
    // Create chart
    const chart = createChart(container, {
      width: container.clientWidth,
      height: 384,
      layout: {
        background: { type: 'solid', color: '#1f2937' },
        textColor: '#ffffff'
      },
      grid: {
        vertLines: { color: '#374151' },
        horzLines: { color: '#374151' }
      },
      timeScale: {
        borderColor: '#374151'
      },
      rightPriceScale: {
        borderColor: '#374151'
      }
    })
    
    // Add series using v5 API
    const series = chart.addSeries(LineSeries, {
      color: '#10b981',
      lineWidth: 2
    })
    
    // Generate sample data
    const data = []
    let price = 1.25
    for (let i = 0; i < 100; i++) {
      const time = Math.floor(Date.now() / 1000) - (100 - i) * 3600
      price += (Math.random() - 0.5) * 0.02
      data.push({
        time,
        value: Number(price.toFixed(4))
      })
    }
    
    series.setData(data)
    
    status.value = 'Manual chart created successfully!'
    
  } catch (err) {
    console.error('Manual chart error:', err)
    error.value = err.message
    status.value = 'Manual chart failed'
  }
}

onMounted(async () => {
  try {
    status.value = 'Testing Lightweight Charts import...'
    
    // Test if we can import the library
    const { createChart, LineSeries } = await import('lightweight-charts')
    
    if (typeof createChart === 'function') {
      status.value = 'Lightweight Charts v5 API available ✅'
    } else {
      throw new Error('createChart is not a function')
    }
    
  } catch (err) {
    console.error('Import test error:', err)
    error.value = err.message
    status.value = 'Import test failed ❌'
  }
})
</script>

<style scoped>
#manual-chart {
  background: #1f2937;
}
</style>