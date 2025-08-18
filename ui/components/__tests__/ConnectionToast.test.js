/**
 * Comprehensive test suite for ConnectionToast component
 * Tests wallet-specific toast notifications with icons and loading states
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ConnectionToast from '../ConnectionToast.vue'

describe('ConnectionToast', () => {
  let wrapper

  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    wrapper?.unmount()
    vi.useRealTimers()
    vi.clearAllMocks()
  })

  describe('Component Initialization', () => {
    it('should not render when show is false', () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: false,
          title: 'Test Title'
        },
        global: {
          stubs: {
            Teleport: true,
            Transition: true
          }
        }
      })

      expect(wrapper.find('.fixed').exists()).toBe(false)
    })

    it('should render when show is true', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Connection Test',
          message: 'Testing connection toast'
        },
        global: {
          stubs: {
            Teleport: true,
            Transition: true
          }
        }
      })

      await nextTick()
      expect(wrapper.find('.fixed').exists()).toBe(true)
    })
  })

  describe('Toast Types and Icons', () => {
    it('should display success icon for success type', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'success',
          title: 'Connection Successful'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const successIcon = wrapper.find('.bg-green-500\\/20')
      expect(successIcon.exists()).toBe(true)
      
      const checkIcon = wrapper.find('svg path[fill-rule="evenodd"]')
      expect(checkIcon.exists()).toBe(true)
    })

    it('should display loading spinner for loading type', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'loading',
          title: 'Connecting...'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const loadingContainer = wrapper.find('.bg-blue-500\\/20')
      expect(loadingContainer.exists()).toBe(true)
      
      const spinner = wrapper.find('.animate-spin')
      expect(spinner.exists()).toBe(true)
      expect(spinner.classes()).toContain('border-blue-400')
    })

    it('should display error icon for error type', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'error',
          title: 'Connection Failed'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const errorContainer = wrapper.find('.bg-red-500\\/20')
      expect(errorContainer.exists()).toBe(true)
      
      const errorIcon = wrapper.find('svg')
      expect(errorIcon.classes()).toContain('text-red-400')
    })

    it('should default to error icon for unknown type', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'unknown',
          title: 'Unknown Type'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const errorContainer = wrapper.find('.bg-red-500\\/20')
      expect(errorContainer.exists()).toBe(true)
    })
  })

  describe('Wallet Icon Support', () => {
    it('should display wallet icon when provided', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'success',
          title: 'MetaMask Connected',
          walletIcon: '/metamask-icon.png'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const walletIcon = wrapper.find('img')
      expect(walletIcon.exists()).toBe(true)
      expect(walletIcon.attributes('src')).toBe('/metamask-icon.png')
      expect(walletIcon.attributes('alt')).toBe('MetaMask Connected')
    })

    it('should apply correct border color based on type for wallet icons', async () => {
      const testCases = [
        { type: 'success', expectedClass: 'border-green-500' },
        { type: 'error', expectedClass: 'border-red-500' },
        { type: 'loading', expectedClass: 'border-gray-600/50' }
      ]

      for (const testCase of testCases) {
        const testWrapper = mount(ConnectionToast, {
          props: {
            show: true,
            type: testCase.type,
            title: `${testCase.type} test`,
            walletIcon: '/test-icon.png'
          },
          global: {
            stubs: { Teleport: true, Transition: true }
          }
        })

        await nextTick()
        const iconContainer = testWrapper.find('img')
        expect(iconContainer.classes()).toContain(testCase.expectedClass)
        testWrapper.unmount()
      }
    })

    it('should handle wallet icon load error gracefully', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Wallet Connection',
          walletIcon: '/broken-icon.png'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const walletIcon = wrapper.find('img')
      expect(walletIcon.exists()).toBe(true)

      // Simulate image error
      await walletIcon.trigger('error')
      expect(walletIcon.element.style.display).toBe('none')
    })

    it('should prioritize wallet icon over default type icons', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'success',
          title: 'Wallet Connected',
          walletIcon: '/wallet-icon.png'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      // Should show wallet icon
      const walletIcon = wrapper.find('img')
      expect(walletIcon.exists()).toBe(true)
      
      // Should not show default success icon container
      const defaultIcon = wrapper.find('.bg-green-500\\/20')
      expect(defaultIcon.exists()).toBe(false)
    })
  })

  describe('Content Display', () => {
    it('should display title text', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Connection Status'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const titleElement = wrapper.find('.font-medium')
      expect(titleElement.text()).toBe('Connection Status')
    })

    it('should display message when provided', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Status',
          message: 'Detailed status message'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const messageElement = wrapper.find('.text-gray-300')
      expect(messageElement.exists()).toBe(true)
      expect(messageElement.text()).toBe('Detailed status message')
    })

    it('should not render message element when message is not provided', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Status Only'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const messageElement = wrapper.find('.text-gray-300')
      expect(messageElement.exists()).toBe(false)
    })
  })

  describe('Manual Dismissal', () => {
    it('should have close button', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Closeable Toast'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const closeButton = wrapper.find('.bg-gray-700\\/50')
      expect(closeButton.exists()).toBe(true)
      
      const closeIcon = closeButton.find('svg')
      expect(closeIcon.exists()).toBe(true)
    })

    it('should emit close event when close button is clicked', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Test Toast'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const closeButton = wrapper.find('.bg-gray-700\\/50')
      await closeButton.trigger('click')

      expect(wrapper.emitted('close')).toBeTruthy()
      expect(wrapper.emitted('close')).toHaveLength(1)
    })
  })

  describe('Auto-Dismiss Functionality', () => {
    it('should auto-dismiss after default duration (4000ms)', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'success',
          title: 'Auto Dismiss Test'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      expect(wrapper.emitted('close')).toBeFalsy()

      // Fast-forward 4 seconds
      vi.advanceTimersByTime(4000)
      await nextTick()

      expect(wrapper.emitted('close')).toBeTruthy()
      expect(wrapper.emitted('close')).toHaveLength(1)
    })

    it('should auto-dismiss after custom duration', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'success',
          title: 'Custom Duration Test',
          duration: 2000
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      expect(wrapper.emitted('close')).toBeFalsy()

      // Should not dismiss before custom duration
      vi.advanceTimersByTime(1999)
      await nextTick()
      expect(wrapper.emitted('close')).toBeFalsy()

      // Should dismiss after custom duration
      vi.advanceTimersByTime(1)
      await nextTick()
      expect(wrapper.emitted('close')).toBeTruthy()
    })

    it('should not auto-dismiss loading toasts', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'loading',
          title: 'Loading Test',
          duration: 1000
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      
      // Even after duration passes, loading toasts should not auto-dismiss
      vi.advanceTimersByTime(2000)
      await nextTick()

      expect(wrapper.emitted('close')).toBeFalsy()
    })

    it('should not auto-dismiss when duration is 0', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'success',
          title: 'No Auto Dismiss',
          duration: 0
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      
      vi.advanceTimersByTime(10000)
      await nextTick()

      expect(wrapper.emitted('close')).toBeFalsy()
    })

    it('should clear timeout when component is unmounted', async () => {
      const clearTimeoutSpy = vi.spyOn(global, 'clearTimeout')
      
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'success',
          title: 'Unmount Test',
          duration: 5000
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      wrapper.unmount()

      expect(clearTimeoutSpy).toHaveBeenCalled()
    })
  })

  describe('Prop Validation', () => {
    it('should validate type prop correctly', () => {
      const validator = ConnectionToast.props.type.validator
      
      expect(validator('success')).toBe(true)
      expect(validator('error')).toBe(true)
      expect(validator('loading')).toBe(true)
      expect(validator('invalid')).toBe(false)
    })

    it('should have correct default props', () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Default Props Test'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      // Type should default to 'success'
      expect(wrapper.vm.type).toBe('success')
      
      // Duration should default to 4000
      expect(wrapper.vm.duration).toBe(4000)
    })
  })

  describe('Styling and Layout', () => {
    it('should have correct positioning classes', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Positioning Test'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const container = wrapper.find('.fixed')
      expect(container.classes()).toContain('top-20')
      expect(container.classes()).toContain('right-4')
      expect(container.classes()).toContain('z-50')
      expect(container.classes()).toContain('max-w-sm')
    })

    it('should have backdrop blur and border styling', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Styling Test'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const container = wrapper.find('.fixed')
      expect(container.classes()).toContain('backdrop-blur-xl')
      expect(container.classes()).toContain('border-gray-700/50')
      expect(container.classes()).toContain('bg-gray-800/95')
    })

    it('should have proper text styling', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          title: 'Text Styling Test',
          message: 'Message text'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      const titleElement = wrapper.find('.font-medium.text-white')
      const messageElement = wrapper.find('.text-gray-300')
      
      expect(titleElement.exists()).toBe(true)
      expect(messageElement.exists()).toBe(true)
    })
  })

  describe('Reactive Behavior', () => {
    it('should react to show prop changes', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: false,
          title: 'Reactive Test'
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      expect(wrapper.find('.fixed').exists()).toBe(false)

      await wrapper.setProps({ show: true })
      expect(wrapper.find('.fixed').exists()).toBe(true)

      await wrapper.setProps({ show: false })
      expect(wrapper.find('.fixed').exists()).toBe(false)
    })

    it('should restart timer when show becomes true again', async () => {
      wrapper = mount(ConnectionToast, {
        props: {
          show: true,
          type: 'success',
          title: 'Timer Restart Test',
          duration: 1000
        },
        global: {
          stubs: { Teleport: true, Transition: true }
        }
      })

      await nextTick()
      
      // Hide toast before auto-dismiss
      await wrapper.setProps({ show: false })
      vi.advanceTimersByTime(500)
      
      // Show again - should restart timer
      await wrapper.setProps({ show: true })
      await nextTick()
      
      // Should not dismiss after original time would have expired
      vi.advanceTimersByTime(600)
      await nextTick()
      expect(wrapper.emitted('close')).toBeFalsy()
      
      // Should dismiss after new full duration
      vi.advanceTimersByTime(500)
      await nextTick()
      expect(wrapper.emitted('close')).toBeTruthy()
    })
  })
})