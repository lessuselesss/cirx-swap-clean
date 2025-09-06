<template>
  <div v-if="modelValue" class="fixed inset-0 backdrop-blur-sm bg-black/60 flex items-center justify-center z-50">
    <div class="space-modal rounded-2xl p-3 sm:p-5 md:p-6 w-full max-w-64 sm:max-w-80 md:max-w-md lg:max-w-lg mx-4 shadow-2xl shadow-cyan-500/20 relative gradient-border-animated border border-white/10 overflow-hidden">
      <!-- Additional star layers for random blinking -->
      <div class="star-layer-1"></div>
      <div class="star-layer-2"></div>
      
      <h2 class="text-2xl font-bold text-white mb-8 text-center">{{ title }}</h2>
      
      <!-- Wallet Tiles -->
      <div class="flex flex-col gap-4 mb-8">
        <a
          href="https://www.saturnwallet.app"
          target="_blank"
          rel="noopener noreferrer"
          class="wallet-link flex flex-col items-center gap-3 p-6 rounded-xl group"
        >
          <div class="w-48 h-48 rounded-2xl overflow-hidden bg-transparent group-hover:bg-gray-800 flex items-center justify-center flex-shrink-0 transition-colors duration-300">
            <img 
              src="/saturnicon.svg" 
              alt="Saturn Wallet"
              class="w-36 h-36 object-contain"
            />
          </div>
          <span class="wallet-text">Saturn</span>
        </a>

        <a
          href="https://www.circularlabs.io/nero"
          target="_blank"
          rel="noopener noreferrer"
          class="wallet-link flex flex-col items-center gap-3 p-6 rounded-xl group"
        >
          <div class="w-48 h-48 rounded-2xl overflow-hidden bg-transparent group-hover:bg-gray-800 flex items-center justify-center flex-shrink-0 transition-colors duration-300">
            <img 
              src="/neroicon.svg" 
              alt="Nero Wallet"
              class="w-36 h-36 object-contain"
            />
          </div>
          <span class="wallet-text">Nero</span>
        </a>
      </div>
      
      <!-- Close Button - Positioned under Nero wallet -->
      <button 
        @click="closeModal"
        class="close-button-circular absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-10 h-10 sm:w-12 sm:h-12 bg-black border-2 border-white/20 rounded-full flex items-center justify-center text-gray-300 hover:text-white hover:border-white/40 transition-all duration-300 hover:shadow-lg hover:shadow-white/20"
      >
        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Wallets'
  }
})

const emit = defineEmits(['update:modelValue'])

const closeModal = () => {
  emit('update:modelValue', false)
}
</script>

<style scoped>
/* Gradient border that's always animated (for modal) */
.gradient-border-animated {
  position: relative;
  border: 1px solid #00ff88;
  border-radius: 1rem;
  transition: all 0.3s ease;
  animation: border-color-cycle 75s ease infinite;
}

@keyframes border-color-cycle {
  0% { border-color: #00ff88; }
  25% { border-color: #00d9ff; }
  50% { border-color: #8b5cf6; }
  75% { border-color: #a855f7; }
  100% { border-color: #00ff88; }
}

/* Wallet text - white by default, animated gradient on hover */
.wallet-text {
  color: white;
  font-weight: 600;
  font-size: 1.125rem;
  line-height: 1.75rem;
  transition: all 0.3s ease;
  display: inline-block;
}

a.group:hover .wallet-text {
  background: linear-gradient(45deg, #00ff88, #00d9ff, #8b5cf6, #a855f7, #00ff88) !important;
  background-size: 400% 400% !important;
  background-clip: text !important;
  -webkit-background-clip: text !important;
  -webkit-text-fill-color: transparent !important;
  color: transparent !important;
  animation: text-gradient-cycle 75s ease infinite !important;
}

@keyframes text-gradient-cycle {
  0% {
    background-position: 0% 50%;
  }
  25% {
    background-position: 25% 50%;
  }
  50% {
    background-position: 50% 50%;
  }
  75% {
    background-position: 75% 50%;
  }
  100% {
    background-position: 100% 50%;
  }
}

/* Space Theme Modal with Particles */
.space-modal {
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.95), rgba(15, 15, 35, 0.9));
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 
    0 8px 32px 0 rgba(0, 0, 0, 0.6),
    inset 0 1px 0 rgba(255, 255, 255, 0.05);
  position: relative;
  overflow: hidden;
}

.space-modal::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(2px 2px at 20px 30px, rgba(255, 255, 255, 0.8), transparent),
    radial-gradient(2px 2px at 40px 70px, rgba(200, 200, 255, 0.6), transparent),
    radial-gradient(1px 1px at 90px 40px, rgba(255, 255, 255, 0.9), transparent),
    radial-gradient(1px 1px at 130px 80px, rgba(255, 200, 200, 0.5), transparent),
    radial-gradient(2px 2px at 160px 30px, rgba(255, 255, 255, 0.7), transparent),
    radial-gradient(1px 1px at 200px 90px, rgba(200, 255, 255, 0.6), transparent),
    radial-gradient(1px 1px at 240px 50px, rgba(255, 255, 255, 0.8), transparent),
    radial-gradient(2px 2px at 280px 10px, rgba(255, 255, 255, 0.9), transparent),
    radial-gradient(1px 1px at 320px 70px, rgba(255, 255, 200, 0.4), transparent),
    radial-gradient(1px 1px at 360px 20px, rgba(255, 255, 255, 0.7), transparent),
    radial-gradient(3px 3px at 80px 120px, rgba(255, 255, 255, 0.8), transparent),
    radial-gradient(1px 1px at 220px 140px, rgba(200, 200, 255, 0.5), transparent),
    radial-gradient(2px 2px at 300px 100px, rgba(255, 255, 255, 0.6), transparent),
    radial-gradient(1px 1px at 180px 60px, rgba(255, 200, 255, 0.3), transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite;
  pointer-events: none;
  z-index: 0;
}

.space-modal::after {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(2px 2px at 45px 85px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.9) 50%, transparent),
    radial-gradient(1px 1px at 125px 45px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.7) 50%, transparent),
    radial-gradient(2px 2px at 245px 125px, rgba(200, 200, 255, 0), rgba(200, 200, 255, 0.8) 50%, transparent),
    radial-gradient(1px 1px at 315px 65px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6) 50%, transparent),
    radial-gradient(3px 3px at 185px 25px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.9) 50%, transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite, randomBlink1 8s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

/* Additional star layers for random blinking */
.space-modal .star-layer-1 {
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(1px 1px at 60px 110px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.8) 50%, transparent),
    radial-gradient(2px 2px at 340px 45px, rgba(200, 255, 200, 0), rgba(200, 255, 200, 0.7) 50%, transparent),
    radial-gradient(1px 1px at 150px 180px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6) 50%, transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite, randomBlink2 12s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

.space-modal .star-layer-2 {
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(1px 1px at 275px 155px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.9) 50%, transparent),
    radial-gradient(2px 2px at 95px 75px, rgba(255, 200, 255, 0), rgba(255, 200, 255, 0.6) 50%, transparent),
    radial-gradient(1px 1px at 385px 135px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.7) 50%, transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite, randomBlink3 16s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

.space-modal > * {
  position: relative;
  z-index: 1;
}

/* Circular Close Button */
.close-button-circular {
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.9), rgba(30, 30, 50, 0.8));
  backdrop-filter: blur(10px);
  box-shadow: 
    0 4px 16px rgba(0, 0, 0, 0.4),
    inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.close-button-circular:hover {
  background: linear-gradient(135deg, rgba(20, 20, 20, 0.95), rgba(40, 40, 60, 0.9));
  transform: translateX(-50%) scale(1.05);
}

/* Animation keyframes */
@keyframes starsMove {
  0% {
    transform: translateX(0px) translateY(0px);
  }
  100% {
    transform: translateX(-400px) translateY(-200px);
  }
}

@keyframes randomBlink1 {
  0% { opacity: 0.3; }
  15% { opacity: 0.8; }
  20% { opacity: 0.3; }
  45% { opacity: 0.3; }
  50% { opacity: 1; }
  55% { opacity: 0.3; }
  80% { opacity: 0.3; }
  85% { opacity: 0.9; }
  90% { opacity: 0.3; }
  100% { opacity: 0.3; }
}

@keyframes randomBlink2 {
  0% { opacity: 0.4; }
  25% { opacity: 0.4; }
  30% { opacity: 0.9; }
  35% { opacity: 0.4; }
  65% { opacity: 0.4; }
  70% { opacity: 1; }
  75% { opacity: 0.4; }
  100% { opacity: 0.4; }
}

@keyframes randomBlink3 {
  0% { opacity: 0.2; }
  10% { opacity: 0.7; }
  15% { opacity: 0.2; }
  40% { opacity: 0.2; }
  60% { opacity: 0.2; }
  65% { opacity: 0.8; }
  70% { opacity: 0.2; }
  90% { opacity: 0.2; }
  95% { opacity: 0.6; }
  100% { opacity: 0.2; }
}
</style>