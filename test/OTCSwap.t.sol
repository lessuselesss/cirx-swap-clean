// SPDX-License-Identifier: MIT
pragma solidity ^0.8.26;

import "forge-std/Test.sol";
import "../src/tokens/CIRXToken.sol";
import "../src/vesting/VestingContract.sol";
import "../src/swap/SimpleOTCSwap.sol";
import "@openzeppelin/contracts/token/ERC20/ERC20.sol";

contract MockERC20 is ERC20 {
    constructor(string memory name, string memory symbol) ERC20(name, symbol) {
        _mint(msg.sender, 1000000 * 10 ** 18);
    }

    function mint(address to, uint256 amount) external {
        _mint(to, amount);
    }
}

contract OTCSwapTest is Test {
    CIRXToken public cirxToken;
    VestingContract public vestingContract;
    SimpleOTCSwap public otcSwap;
    MockERC20 public usdc;

    address public owner = address(0x1);
    address public treasury = address(0x2);
    address public user = address(0x3);
    address public poolManager = address(0x4); // Mock pool manager

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

        // Deploy mock USDC
        usdc = new MockERC20("USD Coin", "USDC");

        // Add USDC as supported token ($1 per USDC)
        otcSwap.addSupportedToken(address(usdc), 1 * 10 ** 18);

        vm.stopPrank();

        // Give user some USDC
        usdc.mint(user, 100000 * 10 ** 18);
    }

    function testLiquidSwap() public {
        uint256 swapAmount = 1000 * 10 ** 18; // 1000 USDC

        vm.startPrank(user);

        // Approve OTC contract to spend USDC
        usdc.approve(address(otcSwap), swapAmount);

        // Get quote
        (uint256 cirxAmount, uint256 fee) = otcSwap.getLiquidQuote(address(usdc), swapAmount);

        // Execute liquid swap
        otcSwap.swapLiquid(address(usdc), swapAmount, cirxAmount);

        // Check user received CIRX tokens
        assertEq(cirxToken.balanceOf(user), cirxAmount);

        // Check treasury received fee
        assertEq(usdc.balanceOf(treasury), fee);

        vm.stopPrank();
    }

    function testOTCSwap() public {
        uint256 swapAmount = 10000 * 10 ** 18; // 10K USDC (should get 8% discount)

        vm.startPrank(user);

        // Approve OTC contract to spend USDC
        usdc.approve(address(usdc), swapAmount);
        usdc.approve(address(otcSwap), swapAmount);

        // Get quote
        (uint256 cirxAmount, uint256 fee, uint256 discountBps) = otcSwap.getOTCQuote(address(usdc), swapAmount);

        // Should get 8% discount for 10K purchase
        assertEq(discountBps, 800);

        // Execute OTC swap
        otcSwap.swapOTC(address(usdc), swapAmount, cirxAmount);

        // Check user has vesting position
        (uint256 totalAmount,,, uint256 claimableAmount, bool isActive) = vestingContract.getVestingInfo(user);

        assertEq(totalAmount, cirxAmount);
        assertEq(isActive, true);
        assertEq(claimableAmount, 0); // Nothing claimable immediately

        // Check treasury received fee
        assertEq(usdc.balanceOf(treasury), fee);

        vm.stopPrank();
    }

    function testVestingClaim() public {
        uint256 swapAmount = 1000 * 10 ** 18; // 1K USDC

        vm.startPrank(user);

        // Approve and execute OTC swap
        usdc.approve(address(otcSwap), swapAmount);
        (uint256 cirxAmount,,) = otcSwap.getOTCQuote(address(usdc), swapAmount);
        otcSwap.swapOTC(address(usdc), swapAmount, cirxAmount);

        // Fast forward 3 months (50% of vesting period)
        vm.warp(block.timestamp + 90 days);

        // Check claimable amount (should be ~50%)
        uint256 claimableAmount = vestingContract.getClaimableAmount(user);
        assertApproxEqRel(claimableAmount, cirxAmount / 2, 0.01e18); // Within 1%

        // Claim tokens
        vestingContract.claimTokens();

        // Check user received tokens
        assertEq(cirxToken.balanceOf(user), claimableAmount);

        // Fast forward to end of vesting
        vm.warp(block.timestamp + 90 days);

        // Claim remaining tokens
        vestingContract.claimTokens();

        // Check user received all tokens
        assertEq(cirxToken.balanceOf(user), cirxAmount);

        vm.stopPrank();
    }

    function testDiscountTiers() public view {
        // Test different discount tiers
        assertEq(otcSwap.calculateDiscount(500 * 10 ** 18), 0); // Below $1K: 0%
        assertEq(otcSwap.calculateDiscount(1000 * 10 ** 18), 500); // $1K: 5%
        assertEq(otcSwap.calculateDiscount(10000 * 10 ** 18), 800); // $10K: 8%
        assertEq(otcSwap.calculateDiscount(50000 * 10 ** 18), 1200); // $50K: 12%
    }
}
