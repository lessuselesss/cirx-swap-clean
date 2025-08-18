/**
 * Comprehensive test suite for ToastNotifications component
 * Tests all functionality including rendering, auto-dismiss, actions, and accessibility
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ToastNotifications from '../ToastNotifications.vue'

describe('ToastNotifications', () => {
  let wrapper
  let mockToast

  beforeEach(() => {
    vi.useFakeTimers()
    wrapper = mount(ToastNotifications, {
      global: {
        stubs: {
          Teleport: true,
          TransitionGroup: true
        }
      }
    })
    mockToast = wrapper.vm
  })

  afterEach(() => {
    wrapper?.unmount()
    vi.useRealTimers()
    vi.clearAllMocks()
  })

  describe('Component Initialization', () => {
    it('should render empty notification list initially', () => {
      expect(wrapper.vm.notifications).toEqual([])
      expect(wrapper.find('[role="region"]').exists()).toBe(false)
    })

    it('should expose toast API methods', () => {
      expect(typeof mockToast.success).toBe('function')
      expect(typeof mockToast.error).toBe('function')
      expect(typeof mockToast.warning).toBe('function')
      expect(typeof mockToast.info).toBe('function')
      expect(typeof mockToast.add).toBe('function')
      expect(typeof mockToast.remove).toBe('function')
      expect(typeof mockToast.clear).toBe('function')
    })

    it('should make toast API available globally on window', async () => {
      await nextTick()
      expect(window.$toast).toBeDefined()
      expect(typeof window.$toast.success).toBe('function')
    })
  })

  describe('Toast Creation and Types', () => {
    it('should create success toast with correct styling', async () => {
      const toastId = mockToast.success('Success message')
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(1)
      expect(wrapper.vm.notifications[0].type).toBe('success')
      expect(wrapper.vm.notifications[0].message).toBe('Success message')
      expect(wrapper.vm.notifications[0].id).toBe(toastId)

      const toastElement = wrapper.find('[role="status"]')
      expect(toastElement.exists()).toBe(true)
      expect(toastElement.classes()).toContain('bg-green-900/90')
    })

    it('should create error toast with correct styling and longer timeout', async () => {
      const toastId = mockToast.error('Error message')
      await nextTick()

      const notification = wrapper.vm.notifications[0]
      expect(notification.type).toBe('error')
      expect(notification.message).toBe('Error message')
      expect(notification.autoTimeoutMs).toBe(8000) // Error toasts have longer timeout

      const toastElement = wrapper.find('[role="alert"]')
      expect(toastElement.exists()).toBe(true)
      expect(toastElement.classes()).toContain('bg-red-900/90')
    })

    it('should create warning toast with correct styling', async () => {
      mockToast.warning('Warning message')
      await nextTick()

      const notification = wrapper.vm.notifications[0]
      expect(notification.type).toBe('warning')
      expect(notification.message).toBe('Warning message')

      const toastElement = wrapper.find('[role="status"]')
      expect(toastElement.classes()).toContain('bg-yellow-900/90')
    })

    it('should create info toast with correct styling', async () => {
      mockToast.info('Info message')
      await nextTick()

      const notification = wrapper.vm.notifications[0]
      expect(notification.type).toBe('info')
      expect(notification.message).toBe('Info message')

      const toastElement = wrapper.find('[role="status"]')
      expect(toastElement.classes()).toContain('bg-blue-900/90')
    })

    it('should handle custom options for toasts', async () => {
      mockToast.success('Custom message', {
        title: 'Custom Title',
        autoTimeoutMs: 10000,
        showProgress: false
      })
      await nextTick()

      const notification = wrapper.vm.notifications[0]
      expect(notification.title).toBe('Custom Title')
      expect(notification.autoTimeoutMs).toBe(10000)
      expect(notification.showProgress).toBe(false)
    })
  })

  describe('Toast Content and Structure', () => {
    it('should display title when provided', async () => {
      mockToast.success('Message', { title: 'Success Title' })
      await nextTick()

      const titleElement = wrapper.find('h4')
      expect(titleElement.exists()).toBe(true)
      expect(titleElement.text()).toBe('Success Title')
    })

    it('should not render title element when not provided', async () => {
      mockToast.success('Message without title')
      await nextTick()

      const titleElement = wrapper.find('h4')
      expect(titleElement.exists()).toBe(false)
    })

    it('should display custom icons when provided', async () => {
      mockToast.success('Message', { customIcon: '/test-icon.png' })
      await nextTick()

      const iconImg = wrapper.find('img')
      expect(iconImg.exists()).toBe(true)
      expect(iconImg.attributes('src')).toBe('/test-icon.png')
    })

    it('should use default SVG icons when no custom icon', async () => {
      mockToast.success('Message')
      await nextTick()

      const svgIcon = wrapper.find('svg')
      expect(svgIcon.exists()).toBe(true)
      expect(svgIcon.classes()).toContain('w-5')
    })

    it('should handle image error by hiding broken custom icons', async () => {
      mockToast.success('Message', { customIcon: '/broken-icon.png' })
      await nextTick()

      const iconImg = wrapper.find('img')
      expect(iconImg.exists()).toBe(true)

      // Simulate image error
      await iconImg.trigger('error')
      expect(iconImg.element.style.display).toBe('none')
    })
  })

  describe('Action Buttons', () => {
    it('should render action buttons when provided', async () => {
      const mockAction1 = vi.fn()
      const mockAction2 = vi.fn()

      mockToast.error('Error with actions', {
        actions: [
          { label: 'Retry', handler: mockAction1, primary: true },
          { label: 'Cancel', handler: mockAction2, primary: false }
        ]
      })
      await nextTick()

      const actionButtons = wrapper.findAll('button').filter(btn => 
        btn.text() === 'Retry' || btn.text() === 'Cancel'
      )
      expect(actionButtons).toHaveLength(2)

      const retryButton = actionButtons.find(btn => btn.text() === 'Retry')
      const cancelButton = actionButtons.find(btn => btn.text() === 'Cancel')

      expect(retryButton.exists()).toBe(true)
      expect(cancelButton.exists()).toBe(true)

      // Primary button should have different styling
      expect(retryButton.classes()).toContain('bg-red-600')
      expect(cancelButton.classes()).toContain('bg-red-600/20')
    })

    it('should execute action handlers when clicked', async () => {
      const mockHandler = vi.fn()
      
      mockToast.success('Message with action', {
        actions: [{ label: 'Test Action', handler: mockHandler }]
      })
      await nextTick()

      const actionButton = wrapper.findAll('button').find(btn => btn.text() === 'Test Action')
      await actionButton.trigger('click')

      expect(mockHandler).toHaveBeenCalledOnce()
    })

    it('should dismiss toast after action by default', async () => {
      const mockHandler = vi.fn()
      
      mockToast.success('Message with action', {
        actions: [{ label: 'Test Action', handler: mockHandler }]
      })
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(1)

      const actionButton = wrapper.findAll('button').find(btn => btn.text() === 'Test Action')
      await actionButton.trigger('click')

      expect(wrapper.vm.notifications).toHaveLength(0)
    })

    it('should not dismiss toast when action.dismiss is false', async () => {
      const mockHandler = vi.fn()
      
      mockToast.success('Message with non-dismissing action', {
        actions: [{ label: 'Keep Open', handler: mockHandler, dismiss: false }]
      })
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(1)

      const actionButton = wrapper.findAll('button').find(btn => btn.text() === 'Keep Open')
      await actionButton.trigger('click')

      expect(wrapper.vm.notifications).toHaveLength(1)
      expect(mockHandler).toHaveBeenCalledOnce()
    })
  })

  describe('Manual Dismissal', () => {
    it('should dismiss toast when close button is clicked', async () => {
      mockToast.success('Test message')
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(1)

      const closeButton = wrapper.find('button[aria-label="Dismiss notification"]')
      expect(closeButton.exists()).toBe(true)

      await closeButton.trigger('click')
      expect(wrapper.vm.notifications).toHaveLength(0)
    })

    it('should dismiss specific toast by ID', async () => {
      const id1 = mockToast.success('Message 1')
      const id2 = mockToast.success('Message 2')
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(2)

      mockToast.remove(id1)
      expect(wrapper.vm.notifications).toHaveLength(1)
      expect(wrapper.vm.notifications[0].id).toBe(id2)
    })

    it('should clear all notifications', async () => {
      mockToast.success('Message 1')
      mockToast.error('Message 2')
      mockToast.warning('Message 3')
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(3)

      mockToast.clear()
      expect(wrapper.vm.notifications).toHaveLength(0)
    })
  })

  describe('Auto-Dismiss Functionality', () => {
    it('should auto-dismiss toast after timeout', async () => {
      mockToast.success('Auto dismiss test', { autoTimeoutMs: 1000 })
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(1)

      // Fast-forward time
      vi.advanceTimersByTime(1000)
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(0)
    })

    it('should not auto-dismiss when autoTimeoutMs is 0 or false', async () => {
      mockToast.success('No auto dismiss', { autoTimeoutMs: 0 })
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(1)

      vi.advanceTimersByTime(10000)
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(1)
    })

    it('should update progress bar during countdown', async () => {
      mockToast.success('Progress test', { autoTimeoutMs: 1000, showProgress: true })
      await nextTick()

      const notification = wrapper.vm.notifications[0]
      expect(notification.progress).toBe(100)

      // Advance time partially
      vi.advanceTimersByTime(500)
      await nextTick()

      expect(notification.progress).toBeCloseTo(50, 0)
    })

    it('should hide progress bar when showProgress is false', async () => {
      mockToast.success('No progress', { showProgress: false })
      await nextTick()

      const progressBar = wrapper.find('.absolute.bottom-0')
      expect(progressBar.exists()).toBe(false)
    })
  })

  describe('Multiple Toasts', () => {
    it('should display multiple toasts simultaneously', async () => {
      mockToast.success('Toast 1')
      mockToast.error('Toast 2')
      mockToast.warning('Toast 3')
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(3)
      expect(wrapper.findAll('[role="status"], [role="alert"]')).toHaveLength(3)
    })

    it('should maintain toast order (newest first)', async () => {
      const id1 = mockToast.success('First toast')
      const id2 = mockToast.error('Second toast')
      await nextTick()

      expect(wrapper.vm.notifications[0].id).toBe(id1)
      expect(wrapper.vm.notifications[1].id).toBe(id2)
    })

    it('should handle auto-dismiss of multiple toasts independently', async () => {
      mockToast.success('Fast dismiss', { autoTimeoutMs: 500 })
      mockToast.error('Slow dismiss', { autoTimeoutMs: 1500 })
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(2)

      // Fast toast should dismiss first
      vi.advanceTimersByTime(500)
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(1)
      expect(wrapper.vm.notifications[0].message).toBe('Slow dismiss')

      // Slow toast should dismiss after full time
      vi.advanceTimersByTime(1000)
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(0)
    })
  })

  describe('Accessibility', () => {
    it('should have proper ARIA attributes for error toasts', async () => {
      mockToast.error('Error message')
      await nextTick()

      const toastElement = wrapper.find('[role="alert"]')
      expect(toastElement.exists()).toBe(true)
      expect(toastElement.attributes('aria-live')).toBe('assertive')
    })

    it('should have proper ARIA attributes for non-error toasts', async () => {
      mockToast.success('Success message')
      await nextTick()

      const toastElement = wrapper.find('[role="status"]')
      expect(toastElement.exists()).toBe(true)
      expect(toastElement.attributes('aria-live')).toBe('polite')
    })

    it('should have notifications region with proper label', async () => {
      mockToast.info('Test message')
      await nextTick()

      const region = wrapper.find('[role="region"]')
      expect(region.exists()).toBe(true)
      expect(region.attributes('aria-label')).toBe('Notifications')
    })

    it('should have accessible close button', async () => {
      mockToast.success('Test message')
      await nextTick()

      const closeButton = wrapper.find('button[aria-label="Dismiss notification"]')
      expect(closeButton.exists()).toBe(true)
      expect(closeButton.attributes('aria-label')).toBe('Dismiss notification')
    })

    it('should provide alt text for custom icons', async () => {
      mockToast.success('Message', { 
        customIcon: '/test-icon.png',
        title: 'Custom Title'
      })
      await nextTick()

      const iconImg = wrapper.find('img')
      expect(iconImg.attributes('alt')).toBe('Custom Title')
    })

    it('should fallback to "Notification" alt text when no title', async () => {
      mockToast.success('Message', { customIcon: '/test-icon.png' })
      await nextTick()

      const iconImg = wrapper.find('img')
      expect(iconImg.attributes('alt')).toBe('Notification')
    })
  })

  describe('Styling and CSS Classes', () => {
    it('should apply correct container styles', async () => {
      mockToast.success('Test')
      await nextTick()

      const container = wrapper.find('[role="region"]')
      expect(container.classes()).toContain('fixed')
      expect(container.classes()).toContain('top-4')
      expect(container.classes()).toContain('right-4')
      expect(container.classes()).toContain('z-50')
    })

    it('should apply correct icon classes for each type', async () => {
      const types = ['success', 'error', 'warning', 'info']
      const expectedClasses = [
        'text-green-400',
        'text-red-400', 
        'text-yellow-400',
        'text-blue-400'
      ]

      for (let i = 0; i < types.length; i++) {
        const newWrapper = mount(ToastNotifications)
        newWrapper.vm[types[i]]('Test message')
        await nextTick()

        const icon = newWrapper.find('svg')
        expect(icon.classes()).toContain(expectedClasses[i])
        newWrapper.unmount()
      }
    })

    it('should apply correct border classes for custom icons', async () => {
      mockToast.error('Error with icon', { customIcon: '/error-icon.png' })
      await nextTick()

      const iconImg = wrapper.find('img')
      expect(iconImg.classes()).toContain('border-red-500')
    })
  })

  describe('Edge Cases and Error Handling', () => {
    it('should handle removing non-existent notification gracefully', () => {
      expect(() => {
        mockToast.remove('non-existent-id')
      }).not.toThrow()
      expect(wrapper.vm.notifications).toHaveLength(0)
    })

    it('should generate unique IDs for simultaneous toasts', async () => {
      const id1 = mockToast.success('Message 1')
      const id2 = mockToast.success('Message 2')
      
      expect(id1).not.toBe(id2)
      expect(typeof id1).toBe('number')
      expect(typeof id2).toBe('number')
    })

    it('should handle empty or undefined messages', async () => {
      mockToast.success('')
      mockToast.error(undefined)
      await nextTick()

      expect(wrapper.vm.notifications).toHaveLength(2)
      expect(wrapper.vm.notifications[0].message).toBe('')
      expect(wrapper.vm.notifications[1].message).toBe(undefined)
    })

    it('should handle actions without handlers', async () => {
      mockToast.success('Message', {
        actions: [{ label: 'No Handler' }]
      })
      await nextTick()

      const actionButton = wrapper.findAll('button').find(btn => btn.text() === 'No Handler')
      
      expect(() => actionButton.trigger('click')).not.toThrow()
    })
  })
})