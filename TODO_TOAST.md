# Toast System Unification - TODO

## Current Problem
We have **multiple overlapping toast systems** creating complexity and potential conflicts:

1. **ToastNotifications.vue** - Full-featured toast system (top-right positioning)
2. **ConnectionToast.vue** - Specialized wallet connection toasts (top-center positioning)
3. **Complex initialization chain** - Plugin → safeToast → window.$toast with polling
4. **Architectural redundancy** - Duplicate positioning, styling, and lifecycle management

## Unified Solution: Zone-Based Toast System

### Architecture Overview
- **Single Component** managing all toasts with multiple zones
- **Zone-based positioning** for different toast contexts
- **Unified API** with backward compatibility
- **Simplified initialization** without polling mechanisms

### Implementation Plan

#### Phase 1: Extend ToastNotifications.vue (✅ COMPLETED)
- [x] Add zone support to existing toast system
- [x] Support multiple positioning zones (general, connection)
- [x] Maintain backward compatibility with existing safeToast calls
- [x] Add connection-specific styling and positioning

#### Phase 2: Unified API Implementation (✅ COMPLETED)
- [x] Extend window.$toast with connection zone support
- [x] Add window.$toast.connection.success/loading/error methods
- [x] Ensure proper zone-based positioning (top-right vs top-center)

#### Phase 3: Migration (🔄 IN PROGRESS)
- [ ] Replace ConnectionToast usage in pages/index.vue
- [ ] Convert connectionToast reactive object to unified API calls
- [ ] Test all connection flows (wallet connect, disconnect, chain switching)

#### Phase 4: Cleanup (⏳ PENDING)
- [ ] Remove ConnectionToast.vue component
- [ ] Simplify initialization in plugins/0.toast-init.client.js
- [ ] Remove redundant polling mechanisms from useToast.js
- [ ] Update any remaining direct ConnectionToast imports

### API Design

```javascript
// Unified API
window.$toast = {
  // General notifications (backward compatible)
  success: (message, options) => add({type: 'success', message, zone: 'general', ...options}),
  error: (message, options) => add({type: 'error', message, zone: 'general', ...options}),
  warning: (message, options) => add({type: 'warning', message, zone: 'general', ...options}),
  info: (message, options) => add({type: 'info', message, zone: 'general', ...options}),
  
  // Connection zone
  connection: {
    success: (message, options) => add({type: 'success', message, zone: 'connection', ...options}),
    loading: (message, options) => add({type: 'loading', message, zone: 'connection', autoTimeoutMs: 0, ...options}),
    error: (message, options) => add({type: 'error', message, zone: 'connection', ...options})
  }
}
```

### Zone Specifications

#### General Zone (top-right)
- Position: `fixed top-4 right-4 z-50`
- Types: success, error, warning, info
- Auto-dismiss: 5s (error: 8s)
- Features: Progress bar, custom icons, action buttons

#### Connection Zone (top-center)
- Position: `fixed top-20 left-1/2 transform -translate-x-1/2 z-50`
- Types: success, error, loading
- Auto-dismiss: 4s (loading: persistent)
- Features: Wallet-specific styling, connection state indicators

### Migration Examples

```javascript
// OLD: ConnectionToast reactive object
connectionToast.value = { 
  show: true, 
  type: 'success', 
  title: 'Wallet Connected',
  message: 'MetaMask connected successfully' 
}

// NEW: Unified API
window.$toast.connection.success('MetaMask connected successfully', {
  title: 'Wallet Connected'
})
```

### Benefits
- ✅ Single source of truth for all toasts
- ✅ Maintains distinct UX for connection vs general notifications
- ✅ Simplified architecture and reduced complexity
- ✅ Better performance (single component, unified lifecycle)
- ✅ Easier testing and debugging
- ✅ Future extensible for new toast types/zones

### Files Modified
- `ui/components/ToastNotifications.vue` - Extended with zone support
- `ui/pages/index.vue` - Migration from ConnectionToast to unified API
- `ui/components/ConnectionToast.vue` - To be removed in Phase 4
- `ui/plugins/0.toast-init.client.js` - Simplified initialization
- `ui/composables/useToast.js` - Cleanup redundant polling

### Testing Checklist
- [ ] General toasts appear top-right with correct styling
- [ ] Connection toasts appear top-center with connection styling
- [ ] Wallet connect/disconnect shows appropriate toasts
- [ ] Chain switching displays correct feedback
- [ ] Loading states work properly (persistent for connection zone)
- [ ] Auto-dismiss timing works correctly for each zone
- [ ] No conflicts between zones (toasts don't overlap)
- [ ] Backward compatibility with existing safeToast calls

### Notes
- Connection zone uses different styling (gray theme vs colored themes)
- Connection loading toasts are persistent (no auto-dismiss)
- General zone supports all features (progress, actions, custom icons)
- Connection zone focused on simple status updates