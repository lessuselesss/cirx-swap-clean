// ~~~ File Marked for Refactor/Migration 2025-08-16 ~~~~
// Browser Console Debug Commands for OTC Dropdown
// Copy and paste these commands into your browser's developer console
// when you have http://localhost:3000 open

console.log('🔍 OTC Dropdown Debug Commands Loaded');

// Command 1: Check if Vue app is mounted
function checkVueApp() {
  const app = document.getElementById('__nuxt');
  if (app) {
    console.log('✅ Nuxt app found');
    return true;
  } else {
    console.log('❌ Nuxt app not found');
    return false;
  }
}

// Command 2: Find and click OTC tab
function switchToOTC() {
  // Look for tab buttons
  const tabs = document.querySelectorAll('button');
  let otcTab = null;
  
  tabs.forEach(tab => {
    if (tab.textContent.toLowerCase().includes('otc') || tab.textContent.toLowerCase().includes('buy otc')) {
      otcTab = tab;
    }
  });
  
  if (otcTab) {
    console.log('✅ Found OTC tab, clicking...');
    otcTab.click();
    return true;
  } else {
    console.log('❌ OTC tab not found. Available buttons:', Array.from(tabs).map(t => t.textContent));
    return false;
  }
}

// Command 3: Check for OTC dropdown in DOM
function checkDropdown() {
  // Look for OtcDiscountDropdown component
  const dropdownButtons = document.querySelectorAll('button[type="button"]');
  let otcDropdown = null;
  
  dropdownButtons.forEach(btn => {
    if (btn.textContent.includes('CIRX') && btn.textContent.includes('%')) {
      otcDropdown = btn;
    }
  });
  
  if (otcDropdown) {
    console.log('✅ OTC dropdown found:', otcDropdown);
    console.log('   Text content:', otcDropdown.textContent);
    return otcDropdown;
  } else {
    console.log('❌ OTC dropdown not found');
    console.log('   Available buttons:', Array.from(dropdownButtons).map(b => b.textContent).filter(t => t.trim()));
    return null;
  }
}

// Command 4: Check debug output in DOM
function checkDebugOutput() {
  const body = document.body.textContent;
  const debugMatch = body.match(/Debug: activeTab=(\w+), tiers=(\d+), showDropdown=(true|false)/);
  
  if (debugMatch) {
    console.log('✅ Debug output found:', debugMatch[0]);
    return {
      activeTab: debugMatch[1],
      tiers: parseInt(debugMatch[2]),
      showDropdown: debugMatch[3] === 'true'
    };
  } else {
    console.log('❌ Debug output not found in DOM');
    return null;
  }
}

// Command 5: Run full diagnostic
function runFullDiagnostic() {
  console.log('🔍 Running full OTC dropdown diagnostic...\n');
  
  const results = {
    vueApp: checkVueApp(),
    debugOutput: checkDebugOutput(),
    otcTabFound: false,
    dropdownFound: false
  };
  
  // Try to switch to OTC tab
  results.otcTabFound = switchToOTC();
  
  // Wait a moment for reactivity, then check dropdown
  setTimeout(() => {
    results.dropdownFound = !!checkDropdown();
    results.debugOutputAfter = checkDebugOutput();
    
    console.log('\n📊 Diagnostic Results:', results);
    
    if (results.dropdownFound) {
      console.log('🎉 SUCCESS: OTC dropdown is working!');
    } else {
      console.log('❌ ISSUE: OTC dropdown not found. Possible causes:');
      console.log('   1. discountTiers not loaded');
      console.log('   2. Component not rendering due to condition');
      console.log('   3. CSS hiding the dropdown');
      console.log('   4. JavaScript error preventing render');
    }
  }, 1000);
}

// Command 6: Check console for errors
function checkConsoleErrors() {
  // This will show recent console errors
  console.log('🔍 Recent console activity (check for errors above this message)');
  console.log('If you see Vue warnings, composable errors, or network failures, those might be the cause.');
}

// Command 7: Test the useOtcConfig composable directly (if available globally)
function testOtcConfig() {
  try {
    // This might work if the composable is globally available
    if (window.useOtcConfig) {
      const config = window.useOtcConfig();
      console.log('✅ useOtcConfig available:', config.discountTiers.value);
    } else {
      console.log('❌ useOtcConfig not globally available (this is normal)');
    }
  } catch (err) {
    console.log('❌ Error testing useOtcConfig:', err.message);
  }
}

// Export commands for easy use
window.otcDebug = {
  checkVueApp,
  switchToOTC,
  checkDropdown,
  checkDebugOutput,
  runFullDiagnostic,
  checkConsoleErrors,
  testOtcConfig
};

console.log(`
🎯 Available Debug Commands:
- otcDebug.runFullDiagnostic() - Run complete test
- otcDebug.switchToOTC() - Click OTC tab
- otcDebug.checkDropdown() - Look for dropdown
- otcDebug.checkDebugOutput() - Find debug text
- otcDebug.checkConsoleErrors() - Check for errors

Quick test: otcDebug.runFullDiagnostic()
`);