import { vi } from 'vitest'

// Mock window object for testing
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(), // deprecated
    removeListener: vi.fn(), // deprecated
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
})

// Mock global $toast for tests
global.$toast = {
  success: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
  info: vi.fn(),
  add: vi.fn(),
  remove: vi.fn(),
  clear: vi.fn(),
}

// Mock window.$toast
Object.defineProperty(window, '$toast', {
  writable: true,
  value: global.$toast,
})