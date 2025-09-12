// Price countdown timer management composable
import { ref, computed } from 'vue'

export function iusePriceCountdown() {
  const priceCountdown = ref(30)
  let countdownTimer = null
  
  // Timer progress for SVG animation
  const timerProgress = computed(() => {
    const circumference = 2 * Math.PI * 20 // radius = 20
    const progress = (30 - priceCountdown.value) / 30
    const offset = circumference * (1 - progress)
    return offset
  })
  
  const startPriceCountdown = (onExpire) => {
    if (countdownTimer) clearInterval(countdownTimer)
    priceCountdown.value = 30
    console.log('🕐 Starting price countdown from:', priceCountdown.value)
    countdownTimer = setInterval(async () => {
      if (priceCountdown.value > 0) {
        priceCountdown.value -= 1
      } else {
        console.log('🔄 Countdown expired, triggering refresh')
        if (onExpire) await onExpire()
      }
    }, 1000)
  }
  
  const stopPriceCountdown = () => {
    if (countdownTimer) {
      clearInterval(countdownTimer)
      countdownTimer = null
    }
  }
  
  return {
    priceCountdown,
    timerProgress,
    startPriceCountdown,
    stopPriceCountdown
  }
}