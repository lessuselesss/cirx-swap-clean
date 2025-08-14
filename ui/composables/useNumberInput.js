/**
 * useNumberInput Composable - Basic version with comma formatting
 */
import { ref, computed } from 'vue'

export function useNumberInput(initialValue = '', options = {}) {
  const rawValue = ref(initialValue || '')
  const displayValue = ref(initialValue || '')
  
  // Simple comma formatting function
  function addCommas(value) {
    if (!value || value === '') return ''
    
    // Remove existing commas and clean the value
    const cleaned = value.toString().replace(/[^0-9.]/g, '')
    if (!cleaned) return ''
    
    const parts = cleaned.split('.')
    const integerPart = parts[0]
    const decimalPart = parts[1]
    
    // Add commas to integer part
    const withCommas = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
    
    // Reconstruct with decimal if it exists
    return decimalPart !== undefined ? `${withCommas}.${decimalPart}` : withCommas
  }
  
  // Remove commas for calculations
  function removeCommas(value) {
    if (!value) return ''
    return value.toString().replace(/,/g, '')
  }
  
  function handleInput(inputValue) {
    const cleanValue = removeCommas(inputValue)
    rawValue.value = cleanValue
    displayValue.value = addCommas(cleanValue)
  }
  
  function handleFocus() {
    // On focus, show clean value for easy editing
    displayValue.value = rawValue.value
  }
  
  function handleBlur() {
    // On blur, show formatted value with commas
    displayValue.value = addCommas(rawValue.value)
  }
  
  function handleKeypress(event) {
    const char = String.fromCharCode(event.which)
    // Allow only numbers and decimal point
    if (!/[0-9.]/.test(char)) {
      event.preventDefault()
    }
  }
  
  function handlePaste(event) {
    event.preventDefault()
    const pastedText = (event.clipboardData || window.clipboardData).getData('text')
    handleInput(pastedText)
  }
  
  return {
    displayValue,
    rawValue: computed(() => rawValue.value),
    handleInput,
    handleFocus,
    handleBlur,
    handleKeypress,
    handlePaste
  }
}