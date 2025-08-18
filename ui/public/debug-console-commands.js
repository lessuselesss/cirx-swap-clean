// ~~~ Marked for Refactor/Migration 2025-08-16 ~~~~
// Browser Console Debug Commands for OTC Dropdown
console.log('🔍 OTC Dropdown Debug Commands Loaded');

// Command 1: Check debug output in DOM
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

// Command 2: Find and click OTC tab
function switchToOTC() {
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
  const dropdownButtons = document.querySelectorAll('button[type="button"]');
  let otcDropdown = null;
  
  dropdownButtons.forEach(btn => {
    if (btn.textContent.includes('CIRX') && (btn.textContent.includes('%') || btn.querySelector('svg'))) {
      otcDropdown = btn;
    }
  });
  
  if (otcDropdown) {
    console.log('✅ OTC dropdown found:', otcDropdown);
    console.log('   Text content:', otcDropdown.textContent);
    return otcDropdown;
  } else {
    console.log('❌ OTC dropdown not found');
    console.log('   Available CIRX elements:', Array.from(document.querySelectorAll('*')).filter(el => el.textContent.includes('CIRX')).map(el => el.tagName + ': ' + el.textContent.slice(0, 50)));
    return null;
  }
}

// Command 4: Run full diagnostic
function runFullDiagnostic() {
  console.log('🔍 Running full OTC dropdown diagnostic...\n');
  
  const initialDebug = checkDebugOutput();
  console.log('Initial state:', initialDebug);
  
  const otcTabFound = switchToOTC();
  
  setTimeout(() => {
    const afterDebug = checkDebugOutput();
    console.log('After switching to OTC:', afterDebug);
    
    const dropdownFound = checkDropdown();
    
    console.log('\n📊 Summary:');
    if (afterDebug && afterDebug.showDropdown && dropdownFound) {
      console.log('🎉 SUCCESS: OTC dropdown should be visible and working!');
    } else {
      console.log('❌ ISSUE: Dropdown not working. Debug info:');
      console.log('   OTC tab found:', otcTabFound);
      console.log('   Debug output:', afterDebug);
      console.log('   Dropdown in DOM:', !!dropdownFound);
    }
  }, 1000);
}

// Make available globally
window.checkDebugOutput = checkDebugOutput;
window.switchToOTC = switchToOTC;
window.checkDropdown = checkDropdown;
window.runFullDiagnostic = runFullDiagnostic;

console.log('🎯 Available commands: checkDebugOutput(), switchToOTC(), checkDropdown(), runFullDiagnostic()');