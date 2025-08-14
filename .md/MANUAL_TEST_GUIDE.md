# CIRX Logo Visibility Fix - Manual Testing Guide

## Issue Summary
**FIXED**: Users reported that the CIRX logo was not visible or clickable in the receive input box. This was caused by conditional rendering logic that would hide the CIRX display when switching to OTC mode while discount tiers were loading.

## Quick Fix Verification

1. **Start the dev server** (if not running):
   ```bash
   npm run dev
   ```

2. **Open your browser** to `http://localhost:3000` (or 3001)

3. **Test the fix**:
   - Switch to "Buy Liquid" tab → CIRX logo should be visible
   - Switch to "Buy OTC" tab → CIRX logo should STILL be visible (this was broken before)
   - If discount tiers load, dropdown should appear
   - If tiers don't load, regular CIRX logo should remain visible

4. **Check debug indicators** (dev mode only):
   - Look for debug text showing "Tab: liquid/otc, Tiers: X"
   - Green dot indicator on CIRX logo when visible
   - "OTC tiers loading..." message if dropdown not ready

## Advanced Debugging (if needed)

3. **Open browser DevTools** (F12 or right-click → Inspect)

4. **Load debug commands** by copying and pasting this into the Console tab:
   ```javascript
   fetch('/debug-console-commands.js').then(r => r.text()).then(code => eval(code))
   ```

5. **Run the diagnostic**:
   ```javascript
   otcDebug.runFullDiagnostic()
   ```

## What to Look For

### ✅ SUCCESS Indicators:
- Debug text shows: `Debug: activeTab=otc, tiers=3, showDropdown=true`
- OTC dropdown button appears with "CIRX" and a percentage (e.g., "5%")
- Clicking the dropdown shows 3 tiers: 5%, 8%, 12%

### ❌ FAILURE Indicators:
- Debug text shows: `Debug: activeTab=otc, tiers=0, showDropdown=false`
- Only "CIRX" text appears (no dropdown arrow or percentage)
- Console errors about useOtcConfig or component loading

## Manual Step-by-Step Test

### Step 1: Check Initial State
1. Look at the CIRX output field
2. Should show: `Debug: activeTab=liquid, tiers=3, showDropdown=false`
3. Should see standard CIRX token icon (no dropdown)

### Step 2: Switch to OTC Tab
1. Click "Buy OTC" tab
2. Look for debug text change to: `Debug: activeTab=otc, tiers=3, showDropdown=true`
3. CIRX field should now show dropdown button

### Step 3: Test Dropdown Functionality
1. Click the CIRX dropdown button
2. Should see dropdown menu with:
   - "OTC Discount Tiers" header
   - Three tiers: 12% ($50K+), 8% ($10K+), 5% ($1K+)
   - Auto-selection notice at bottom

### Step 4: Test Tier Selection
1. Click different tiers
2. Dropdown should close
3. Selected tier percentage should show next to CIRX

## Troubleshooting

### If `tiers=0`:
- useOtcConfig composable not loading data
- Check network tab for failed `/swap/discount.json` request
- Check console for "Using default OTC configuration" message

### If `showDropdown=false` but `activeTab=otc`:
- Problem with conditional rendering logic
- Check console for Vue component errors

### If dropdown appears but clicking doesn't work:
- JavaScript event handlers not bound properly
- Check console for click event errors

## Console Commands Reference

```javascript
// Quick diagnostic
otcDebug.runFullDiagnostic()

// Check current state
otcDebug.checkDebugOutput()

// Force switch to OTC
otcDebug.switchToOTC()

// Look for dropdown in DOM
otcDebug.checkDropdown()

// Check for recent errors
otcDebug.checkConsoleErrors()
```

## Expected Console Output (Success)

```
🔍 Running full OTC dropdown diagnostic...

✅ Nuxt app found
✅ Debug output found: Debug: activeTab=liquid, tiers=3, showDropdown=false  
✅ Found OTC tab, clicking...
✅ OTC dropdown found: <button>...</button>
   Text content: CIRX 5%

📊 Diagnostic Results: {
  vueApp: true,
  debugOutput: { activeTab: 'otc', tiers: 3, showDropdown: true },
  otcTabFound: true,
  dropdownFound: true
}

🎉 SUCCESS: OTC dropdown is working!
```

## Next Steps

If the dropdown is working:
1. Remove the debug text from SwapOutput.vue
2. Test different input amounts to see tier auto-selection
3. Test the full swap flow with OTC mode

If still not working, share:
1. The console diagnostic output
2. Any console error messages
3. Screenshot of the debug text values