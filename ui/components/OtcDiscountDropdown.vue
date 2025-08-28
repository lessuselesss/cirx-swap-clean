<template>
  <div class="token-display-otc relative z-50">
    <!-- Dropdown Button -->
    <button
      type="button"
      @click="toggleDropdown"
      class="flex items-center gap-2 rounded-full bg-gray-700/50 hover:bg-gray-700/70 transition-colors"
      style="width: 110px; min-width: 110px; max-width: 110px; padding: 8px 12px; gap: 6px;"
      :class="{ 'ring-2 ring-circular-primary/50': isOpen }"
    >
      <img 
        src="/buy/cirx-icon.svg" 
        alt="CIRX"
        class="rounded-full"
        style="width: 16px; height: 16px;"
        @error="handleImageError"
      />
      <span v-if="selectedTier" class="font-semibold text-green-400" style="font-size: 0.8rem; letter-spacing: -0.01em;">
        -{{ selectedTier.discount }}%
      </span>
      <span v-else class="font-semibold text-white" style="font-size: 0.8rem; letter-spacing: -0.01em;">
        CIRX
      </span>
      <svg 
        :class="['text-gray-400 transition-transform ml-2', isOpen && 'rotate-180']" 
        style="width: 12px; height: 12px;"
        fill="none" 
        stroke="currentColor" 
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>


    <!-- Dropdown Menu -->
    <div
      v-if="isOpen"
      class="absolute top-full right-0 mt-2 w-64 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-50"
    >
      <!-- Header -->
      <div class="px-4 py-3 border-b border-gray-700">
        <h3 class="font-semibold text-white text-sm">OTC Discount Tiers</h3>
        <p class="text-xs text-gray-400 mt-1">Select your vesting tier</p>
      </div>

      <!-- Tier Options -->
      <div class="py-2">
        <button
          v-for="(tier, index) in sortedTiers"
          :key="index"
          @click="selectTier(tier)"
          class="w-full px-4 py-3 text-left hover:bg-gray-700/50 transition-colors"
          :class="{ 
            'bg-circular-primary/10 border-l-2 border-circular-primary': selectedTier === tier 
          }"
        >
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium text-white">{{ tier.discount }}% Discount</span>
                <div v-if="selectedTier === tier" class="w-2 h-2 bg-circular-primary rounded-full"></div>
              </div>
              <div class="text-xs text-gray-400">
                Min: ${{ formatAmount(tier.minAmount) }} • {{ tier.vestingMonths }} month vesting
              </div>
              <div class="text-xs text-green-400 font-medium mt-1">
                {{ formatBonusDescription(tier.discount) }}
              </div>
            </div>
          </div>
        </button>
      </div>

      <!-- Auto-Selection Notice -->
      <div class="px-4 py-3 border-t border-gray-700 bg-gray-800/50">
        <p class="text-xs text-gray-400">
          💡 Tier auto-selected based on your swap amount
        </p>
      </div>
    </div>

    <!-- Backdrop -->
    <div
      v-if="isOpen"
      @click="closeDropdown"
      class="fixed inset-0 bg-black/5 z-40"
    ></div>
  </div>
</template>

<script setup>
import { ref, computed, watchEffect, onUnmounted } from 'vue'

const props = defineProps({
  discountTiers: {
    type: Array,
    required: true
  },
  selectedTier: {
    type: Object,
    default: null
  },
  currentAmount: {
    type: [String, Number],
    default: 0
  }
})

const emit = defineEmits(['update:selectedTier', 'tier-changed'])

const isOpen = ref(false)

// Sort tiers by minimum amount (highest first for better UX)
const sortedTiers = computed(() => {
  return [...props.discountTiers].sort((a, b) => b.minAmount - a.minAmount)
})

const toggleDropdown = () => {
  isOpen.value = !isOpen.value
}

const closeDropdown = () => {
  isOpen.value = false
}

const selectTier = (tier) => {
  emit('update:selectedTier', tier)
  emit('tier-changed', tier)
  closeDropdown()
}

const formatAmount = (amount) => {
  if (amount >= 1000000) {
    return `${(amount / 1000000).toFixed(1)}M`
  } else if (amount >= 1000) {
    return `${(amount / 1000).toFixed(0)}K`
  }
  return amount.toString()
}

const formatBonusDescription = (discount) => {
  return `${discount}% more CIRX tokens`
}

const handleImageError = (event) => {
  // Fallback to a simple SVG circle
  event.target.style.display = 'none'
  // Add a simple colored circle as fallback
  const fallback = document.createElement('div')
  fallback.className = 'w-5 h-5 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center text-white text-xs font-bold'
  fallback.textContent = 'C'
  event.target.parentNode.replaceChild(fallback, event.target)
}

// Close dropdown when clicking outside
const closeOnOutsideClick = (event) => {
  if (!event.target.closest('.relative')) {
    closeDropdown()
  }
}

// Add global event listener when dropdown is open
watchEffect(() => {
  if (isOpen.value) {
    document.addEventListener('click', closeOnOutsideClick)
  } else {
    document.removeEventListener('click', closeOnOutsideClick)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', closeOnOutsideClick)
})
</script>