// SPDX-License-Identifier: MIT
pragma solidity ^0.8.26;

import "forge-std/Test.sol";
import "../src/tokens/CIRXToken.sol";
import "../src/vesting/VestingContract.sol";
import "../src/swap/SimpleOTCSwap.sol";
import "@openzeppelin/contracts/token/ERC20/ERC20.sol";

contract MockERC20 is ERC20 {
    uint8 private _decimals;

    constructor(string memory name, string memory symbol, uint8 decimals_) ERC20(name, symbol) {
        _decimals = decimals_;
        _mint(msg.sender, 1000000 * 10 ** decimals_);
    }

    function decimals() public view override returns (uint8) {
        return _decimals;
    }

    function mint(address to, uint256 amount) external {
        _mint(to, amount);
    }
}

contract SimpleOTCSwapTest is Test {
    CIRXToken public cirxToken;
    VestingContract public vestingContract;
    SimpleOTCSwap public otcSwap;
    MockERC20 public usdc;
    MockERC20 public usdt;

    address public owner = address(0x1);
    address public treasury = address(0x2);
    address public user = address(0x3);
    address public user2 = address(0x4);
    address public poolManager = address(0x5); // Mock pool manager

    uint256 public constant USDC_PRICE = 1 * 10 ** 18; // $1
    uint256 public constant USDT_PRICE = 1 * 10 ** 18; // $1

    event LiquidSwap(address indexed user, address indexed inputToken, uint256 inputAmount, uint256 cirxAmount);
    event OTCSwap(
        address indexed user, address indexed inputToken, uint256 inputAmount, uint256 cirxAmount, uint256 discountBps
    );
    event TokenSupported(address indexed token, uint256 price);
    event PriceUpdated(address indexed token, uint256 newPrice);
    event DiscountTierAdded(uint256 minAmount, uint256 discountBps);

    function setUp() public {
        vm.startPrank(owner);

        // Deploy CIRX token
        cirxToken = new CIRXToken(owner, 0);

        // Deploy vesting contract
        vestingContract = new VestingContract(address(cirxToken), owner);

        // Deploy OTC swap contract
        otcSwap = new SimpleOTCSwap(poolManager, address(cirxToken), address(vestingContract), treasury, owner);

        // Set up permissions
        cirxToken.addMinter(address(otcSwap));
        vestingContract.authorizeContract(address(otcSwap));

        // Deploy mock tokens
        usdc = new MockERC20("USD Coin", "USDC", 6);
        usdt = new MockERC20("Tether USD", "USDT", 6);

        // Add supported tokens
        otcSwap.addSupportedToken(address(usdc), USDC_PRICE);
        otcSwap.addSupportedToken(address(usdt), USDT_PRICE);

        vm.stopPrank();

        // Give users some tokens
        usdc.mint(user, 100000 * 10 ** 6); // 100K USDC
        usdt.mint(user, 100000 * 10 ** 6); // 100K USDT
        usdc.mint(user2, 100000 * 10 ** 6);
        usdt.mint(user2, 100000 * 10 ** 6);
    }

    /*//////////////////////////////////////////////////////////////
                        DEPLOYMENT TESTS
    //////////////////////////////////////////////////////////////*/

    function testDeployment() public view {
        assertEq(address(otcSwap.poolManager()), poolManager);
        assertEq(address(otcSwap.cirxToken()), address(cirxToken));
        assertEq(address(otcSwap.vestingContract()), address(vestingContract));
        assertEq(otcSwap.treasury(), treasury);
        assertEq(otcSwap.owner(), owner);
        assertEq(otcSwap.liquidFee(), 30); // 0.3%
        assertEq(otcSwap.otcFee(), 15); // 0.15%
    }

    function testDefaultDiscountTiers() public view {
        // Test that default discount tiers are set correctly
        assertEq(otcSwap.calculateDiscount(500 * 10 ** 18), 0); // Below $1K: 0%
        assertEq(otcSwap.calculateDiscount(1000 * 10 ** 18), 500); // $1K: 5%
        assertEq(otcSwap.calculateDiscount(10000 * 10 ** 18), 800); // $10K: 8%
        assertEq(otcSwap.calculateDiscount(50000 * 10 ** 18), 1200); // $50K: 12%
    }

    /*//////////////////////////////////////////////////////////////
                        TOKEN MANAGEMENT TESTS
    //////////////////////////////////////////////////////////////*/

    function testAddSupportedToken() public {
        MockERC20 newToken = new MockERC20("New Token", "NEW", 18);
        uint256 price = 5 * 10 ** 18; // $5

        vm.prank(owner);
        vm.expectEmit(true, false, false, false);
        emit TokenSupported(address(newToken), price);
        otcSwap.addSupportedToken(address(newToken), price);

        assertTrue(otcSwap.supportedTokens(address(newToken)));
        assertEq(otcSwap.tokenPrices(address(newToken)), price);
    }

    function testUpdateTokenPrice() public {
        uint256 newPrice = 1.1 * 10 ** 18; // $1.10

        vm.prank(owner);
        vm.expectEmit(true, false, false, false);
        emit PriceUpdated(address(usdc), newPrice);
        otcSwap.updateTokenPrice(address(usdc), newPrice);

        assertEq(otcSwap.tokenPrices(address(usdc)), newPrice);
    }

    function test_RevertIf_AddZeroAddressToken() public {
        vm.prank(owner);
        vm.expectRevert("Token address cannot be zero");
        otcSwap.addSupportedToken(address(0), USDC_PRICE);
    }

    function test_RevertIf_AddTokenZeroPrice() public {
        MockERC20 newToken = new MockERC20("New Token", "NEW", 18);
        vm.prank(owner);
        vm.expectRevert("Price must be greater than zero");
        otcSwap.addSupportedToken(address(newToken), 0);
    }

    function test_RevertIf_UpdateUnsupportedTokenPrice() public {
        MockERC20 unsupportedToken = new MockERC20("Unsupported", "UNS", 18);
        vm.prank(owner);
        vm.expectRevert("Token not supported");
        otcSwap.updateTokenPrice(address(unsupportedToken), 1 * 10 ** 18);
    }

    function test_RevertIf_UnauthorizedAddToken() public {
        MockERC20 newToken = new MockERC20("New Token", "NEW", 18);
        vm.prank(user);
        vm.expectRevert();
        otcSwap.addSupportedToken(address(newToken), 1 * 10 ** 18);
    }

    /*//////////////////////////////////////////////////////////////
                        DISCOUNT TIER TESTS
    //////////////////////////////////////////////////////////////*/

    function testAddDiscountTier() public {
        uint256 minAmount = 100000 * 10 ** 18; // $100K
        uint256 discountBps = 1500; // 15%

        vm.prank(owner);
        vm.expectEmit(true, false, false, false);
        emit DiscountTierAdded(minAmount, discountBps);
        otcSwap.addDiscountTier(minAmount, discountBps);

        // Should now get 15% discount for $100K+ purchases
        assertEq(otcSwap.calculateDiscount(minAmount), discountBps);
        assertEq(otcSwap.calculateDiscount(minAmount - 1), 1200); // Previous tier (12%)
    }

    function test_RevertIf_AddExcessiveDiscountTier() public {
        vm.prank(owner);
        vm.expectRevert("Discount cannot exceed 20%");
        otcSwap.addDiscountTier(1000 * 10 ** 18, 2001);
    }

    /*//////////////////////////////////////////////////////////////
                        LIQUID SWAP TESTS
    //////////////////////////////////////////////////////////////*/

    function testLiquidSwap() public {
        uint256 swapAmount = 1000 * 10 ** 6; // 1000 USDC (6 decimals)

        vm.startPrank(user);
        usdc.approve(address(otcSwap), swapAmount);

        // Get quote
        (uint256 cirxAmount, uint256 fee) = otcSwap.getLiquidQuote(address(usdc), swapAmount);

        uint256 initialCirxBalance = cirxToken.balanceOf(user);
        uint256 initialTreasuryBalance = usdc.balanceOf(treasury);

        vm.expectEmit(true, true, false, false);
        emit LiquidSwap(user, address(usdc), swapAmount, cirxAmount);
        otcSwap.swapLiquid(address(usdc), swapAmount, cirxAmount);

        // Check user received CIRX tokens
        assertEq(cirxToken.balanceOf(user), initialCirxBalance + cirxAmount);

        // Check treasury received fee
        assertEq(usdc.balanceOf(treasury), initialTreasuryBalance + fee);

        // Check user's USDC balance decreased
        assertEq(usdc.balanceOf(user), 100000 * 10 ** 6 - swapAmount);

        vm.stopPrank();
    }

    function testLiquidSwapWithSlippageProtection() public {
        uint256 swapAmount = 1000 * 10 ** 6;

        vm.startPrank(user);
        usdc.approve(address(otcSwap), swapAmount);

        (uint256 cirxAmount,) = otcSwap.getLiquidQuote(address(usdc), swapAmount);

        // Try with minimum output higher than quote - should fail
        vm.expectRevert("Insufficient output amount");
        otcSwap.swapLiquid(address(usdc), swapAmount, cirxAmount + 1);

        vm.stopPrank();
    }

    function test_RevertIf_LiquidSwapUnsupportedToken() public {
        MockERC20 unsupportedToken = new MockERC20("Unsupported", "UNS", 18);
        uint256 swapAmount = 1000 * 10 ** 18;

        vm.prank(user);
        vm.expectRevert("Token not supported");
        otcSwap.swapLiquid(address(unsupportedToken), swapAmount, 0);
    }

    /*//////////////////////////////////////////////////////////////
                        OTC SWAP TESTS
    //////////////////////////////////////////////////////////////*/

    function testOTCSwap() public {
        uint256 swapAmount = 10000 * 10 ** 6; // 10K USDC (should get 8% discount)

        vm.startPrank(user);
        usdc.approve(address(otcSwap), swapAmount);

        // Get quote
        (uint256 cirxAmount, uint256 fee, uint256 discountBps) = otcSwap.getOTCQuote(address(usdc), swapAmount);
        assertEq(discountBps, 800); // Should get 8% discount

        uint256 initialTreasuryBalance = usdc.balanceOf(treasury);

        vm.expectEmit(true, true, false, false);
        emit OTCSwap(user, address(usdc), swapAmount, cirxAmount, discountBps);
        otcSwap.swapOTC(address(usdc), swapAmount, cirxAmount);

        // Check user has vesting position
        (uint256 totalAmount,, uint256 claimedAmount, uint256 claimableAmount, bool isActive) =
            vestingContract.getVestingInfo(user);

        assertEq(totalAmount, cirxAmount);
        assertEq(claimedAmount, 0);
        assertEq(claimableAmount, 0); // Nothing claimable immediately
        assertTrue(isActive);

        // Check treasury received fee
        assertEq(usdc.balanceOf(treasury), initialTreasuryBalance + fee);

        vm.stopPrank();
    }

    function testOTCSwapDifferentDiscountTiers() public {
        // Test $1K purchase (5% discount)
        _testOTCSwapWithDiscount(1000 * 10 ** 6, 500);

        // Test $10K purchase (8% discount)
        _testOTCSwapWithDiscount(10000 * 10 ** 6, 800);

        // Test $50K purchase (12% discount)
        _testOTCSwapWithDiscount(50000 * 10 ** 6, 1200);
    }

    function test_RevertIf_OTCSwapUnsupportedToken() public {
        MockERC20 unsupportedToken = new MockERC20("Unsupported", "UNS", 18);
        uint256 swapAmount = 1000 * 10 ** 18;

        vm.prank(user);
        vm.expectRevert("Token not supported");
        otcSwap.swapOTC(address(unsupportedToken), swapAmount, 0);
    }

    /*//////////////////////////////////////////////////////////////
                        QUOTE TESTS
    //////////////////////////////////////////////////////////////*/

    function testLiquidQuote() public view {
        uint256 inputAmount = 1000 * 10 ** 6; // 1000 USDC
        (uint256 cirxAmount, uint256 fee) = otcSwap.getLiquidQuote(address(usdc), inputAmount);

        // Expected: 1000 USDC * $1 = $1000 worth
        // Fee: 1000 * 0.3% = 3 USDC
        // After fee: 997 USDC = 997 CIRX (assuming $1 CIRX)
        uint256 expectedFee = (inputAmount * 30) / 10000; // 0.3%
        uint256 amountAfterFee = inputAmount - expectedFee;
        // Convert to 18 decimals: (amountAfterFee * 10^12 * $1) / 10^18 = amountAfterFee * 10^12
        uint256 expectedCirx = amountAfterFee * 10 ** 12; // Convert 6 decimals to 18 decimals

        assertEq(fee, expectedFee);
        assertEq(cirxAmount, expectedCirx);
    }

    function testOTCQuote() public view {
        uint256 inputAmount = 10000 * 10 ** 6; // 10K USDC
        (uint256 cirxAmount, uint256 fee, uint256 discountBps) = otcSwap.getOTCQuote(address(usdc), inputAmount);

        assertEq(discountBps, 800); // 8% discount for $10K

        uint256 expectedFee = (inputAmount * 15) / 10000; // 0.15%
        uint256 amountAfterFee = inputAmount - expectedFee;
        // Convert to 18 decimals: (amountAfterFee * 10^12 * $1) / 10^18 = amountAfterFee * 10^12
        uint256 usdValueAfterFee = amountAfterFee * 10 ** 12; // Convert 6 decimals to 18 decimals
        uint256 expectedCirx = usdValueAfterFee + (usdValueAfterFee * 800) / 10000; // 8% bonus

        assertEq(fee, expectedFee);
        assertEq(cirxAmount, expectedCirx);
    }

    function test_RevertIf_QuoteUnsupportedToken() public {
        MockERC20 unsupportedToken = new MockERC20("Unsupported", "UNS", 18);
        vm.expectRevert("Token not supported");
        otcSwap.getLiquidQuote(address(unsupportedToken), 1000 * 10 ** 18);
    }

    function test_RevertIf_QuoteZeroAmount() public {
        vm.expectRevert("Input amount must be greater than zero");
        otcSwap.getLiquidQuote(address(usdc), 0);
    }

    /*//////////////////////////////////////////////////////////////
                        ADMIN FUNCTION TESTS
    //////////////////////////////////////////////////////////////*/

    function testUpdateTreasury() public {
        address newTreasury = address(0x6);

        vm.prank(owner);
        otcSwap.updateTreasury(newTreasury);

        assertEq(otcSwap.treasury(), newTreasury);
    }

    function testUpdateFees() public {
        uint256 newLiquidFee = 25; // 0.25%
        uint256 newOtcFee = 10; // 0.10%

        vm.prank(owner);
        otcSwap.updateFees(newLiquidFee, newOtcFee);

        assertEq(otcSwap.liquidFee(), newLiquidFee);
        assertEq(otcSwap.otcFee(), newOtcFee);
    }

    function test_RevertIf_UpdateTreasuryZeroAddress() public {
        vm.prank(owner);
        vm.expectRevert("Treasury cannot be zero address");
        otcSwap.updateTreasury(address(0));
    }

    function test_RevertIf_UpdateExcessiveFees() public {
        vm.prank(owner);
        vm.expectRevert("Liquid fee cannot exceed 10%");
        otcSwap.updateFees(1001, 15);
    }

    function test_RevertIf_UnauthorizedUpdateTreasury() public {
        vm.prank(user);
        vm.expectRevert();
        otcSwap.updateTreasury(address(0x6));
    }

    /*//////////////////////////////////////////////////////////////
                        INTEGRATION TESTS
    //////////////////////////////////////////////////////////////*/

    function testCompleteFlow() public {
        uint256 swapAmount = 10000 * 10 ** 6; // 10K USDC

        vm.startPrank(user);
        usdc.approve(address(otcSwap), swapAmount);

        // Execute OTC swap
        (uint256 cirxAmount,,) = otcSwap.getOTCQuote(address(usdc), swapAmount);
        otcSwap.swapOTC(address(usdc), swapAmount, cirxAmount);

        // Fast forward 3 months (50% of vesting)
        vm.warp(block.timestamp + 90 days);

        // Claim 50% of tokens
        vestingContract.claimTokens();

        assertApproxEqRel(cirxToken.balanceOf(user), cirxAmount / 2, 0.01e18); // Within 1%

        // Fast forward to end of vesting
        vm.warp(block.timestamp + 90 days);

        // Claim remaining tokens
        vestingContract.claimTokens();

        assertEq(cirxToken.balanceOf(user), cirxAmount);

        vm.stopPrank();
    }

    function testMultipleUsersMultipleTokens() public {
        // User 1: USDC liquid swap
        vm.startPrank(user);
        usdc.approve(address(otcSwap), 1000 * 10 ** 6);
        (uint256 cirxAmount1,) = otcSwap.getLiquidQuote(address(usdc), 1000 * 10 ** 6);
        otcSwap.swapLiquid(address(usdc), 1000 * 10 ** 6, cirxAmount1);
        vm.stopPrank();

        // User 2: USDT OTC swap
        vm.startPrank(user2);
        usdt.approve(address(otcSwap), 10000 * 10 ** 6);
        (uint256 cirxAmount2,,) = otcSwap.getOTCQuote(address(usdt), 10000 * 10 ** 6);
        otcSwap.swapOTC(address(usdt), 10000 * 10 ** 6, cirxAmount2);
        vm.stopPrank();

        // Check balances
        assertEq(cirxToken.balanceOf(user), cirxAmount1);

        // User2 should have vesting position
        (uint256 totalAmount,,,, bool isActive) = vestingContract.getVestingInfo(user2);
        assertEq(totalAmount, cirxAmount2);
        assertTrue(isActive);
    }

    /*//////////////////////////////////////////////////////////////
                        FUZZ TESTS
    //////////////////////////////////////////////////////////////*/

    function testFuzzLiquidSwap(uint256 amount) public {
        vm.assume(amount > 0);
        vm.assume(amount <= 50000 * 10 ** 6); // Max 50K USDC

        usdc.mint(user, amount);

        vm.startPrank(user);
        usdc.approve(address(otcSwap), amount);

        (uint256 cirxAmount, uint256 fee) = otcSwap.getLiquidQuote(address(usdc), amount);
        otcSwap.swapLiquid(address(usdc), amount, cirxAmount);

        assertEq(cirxToken.balanceOf(user), cirxAmount);
        assertEq(usdc.balanceOf(treasury), fee);

        vm.stopPrank();
    }

    function testFuzzDiscountCalculation(uint256 usdAmount) public view {
        vm.assume(usdAmount <= 1000000 * 10 ** 18); // Max $1M

        uint256 discount = otcSwap.calculateDiscount(usdAmount);

        if (usdAmount >= 50000 * 10 ** 18) {
            assertEq(discount, 1200); // 12%
        } else if (usdAmount >= 10000 * 10 ** 18) {
            assertEq(discount, 800); // 8%
        } else if (usdAmount >= 1000 * 10 ** 18) {
            assertEq(discount, 500); // 5%
        } else {
            assertEq(discount, 0); // 0%
        }
    }

    /*//////////////////////////////////////////////////////////////
                        HELPER FUNCTIONS
    //////////////////////////////////////////////////////////////*/

    function _testOTCSwapWithDiscount(uint256 amount, uint256 expectedDiscount) internal {
        address testUser = address(uint160(uint256(keccak256(abi.encode(amount)))));
        usdc.mint(testUser, amount);

        vm.startPrank(testUser);
        usdc.approve(address(otcSwap), amount);

        (uint256 cirxAmount,, uint256 discountBps) = otcSwap.getOTCQuote(address(usdc), amount);
        assertEq(discountBps, expectedDiscount);

        otcSwap.swapOTC(address(usdc), amount, cirxAmount);

        (uint256 totalAmount,,,, bool isActive) = vestingContract.getVestingInfo(testUser);
        assertEq(totalAmount, cirxAmount);
        assertTrue(isActive);

        vm.stopPrank();
    }
}
