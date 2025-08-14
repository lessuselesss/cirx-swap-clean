// SPDX-License-Identifier: MIT
pragma solidity ^0.8.26;

import "forge-std/Test.sol";
import "../src/tokens/CIRXToken.sol";
import "../src/vesting/VestingContract.sol";

contract VestingContractTest is Test {
    CIRXToken public cirxToken;
    VestingContract public vestingContract;

    address public owner = address(0x1);
    address public otcContract = address(0x2);
    address public user = address(0x3);
    address public user2 = address(0x4);

    uint256 public constant VESTING_DURATION = 180 days;
    uint256 public constant VEST_AMOUNT = 10000 * 10 ** 18;

    event VestingPositionCreated(address indexed user, uint256 amount, uint256 startTime);
    event TokensClaimed(address indexed user, uint256 amount);
    event ContractAuthorized(address indexed contractAddress);
    event ContractDeauthorized(address indexed contractAddress);

    function setUp() public {
        vm.startPrank(owner);

        // Deploy CIRX token
        cirxToken = new CIRXToken(owner, 1000000 * 10 ** 18);

        // Deploy vesting contract
        vestingContract = new VestingContract(address(cirxToken), owner);

        // Authorize OTC contract
        vestingContract.authorizeContract(otcContract);

        vm.stopPrank();

        // Give OTC contract some CIRX tokens
        vm.prank(owner);
        cirxToken.transfer(otcContract, 100000 * 10 ** 18);
    }

    /*//////////////////////////////////////////////////////////////
                        DEPLOYMENT TESTS
    //////////////////////////////////////////////////////////////*/

    function testDeployment() public view {
        assertEq(address(vestingContract.cirxToken()), address(cirxToken));
        assertEq(vestingContract.owner(), owner);
        assertEq(vestingContract.VESTING_DURATION(), VESTING_DURATION);
        assertEq(vestingContract.totalVesting(), 0);
    }

    function test_RevertIf_DeploymentZeroTokenAddress() public {
        vm.prank(owner);
        vm.expectRevert("CIRX token address cannot be zero");
        new VestingContract(address(0), owner);
    }

    /*//////////////////////////////////////////////////////////////
                        AUTHORIZATION TESTS
    //////////////////////////////////////////////////////////////*/

    function testAuthorizeContract() public {
        address newContract = address(0x5);

        vm.prank(owner);
        vm.expectEmit(true, false, false, false);
        emit ContractAuthorized(newContract);
        vestingContract.authorizeContract(newContract);

        assertTrue(vestingContract.authorizedContracts(newContract));
    }

    function testDeauthorizeContract() public {
        vm.prank(owner);
        vm.expectEmit(true, false, false, false);
        emit ContractDeauthorized(otcContract);
        vestingContract.deauthorizeContract(otcContract);

        assertFalse(vestingContract.authorizedContracts(otcContract));
    }

    function test_RevertIf_AuthorizeZeroAddress() public {
        vm.prank(owner);
        vm.expectRevert("Cannot authorize zero address");
        vestingContract.authorizeContract(address(0));
    }

    function test_RevertIf_UnauthorizedAuthorize() public {
        vm.prank(user);
        vm.expectRevert();
        vestingContract.authorizeContract(address(0x5));
    }

    function test_RevertIf_UnauthorizedDeauthorize() public {
        vm.prank(user);
        vm.expectRevert();
        vestingContract.deauthorizeContract(otcContract);
    }

    /*//////////////////////////////////////////////////////////////
                        VESTING POSITION CREATION TESTS
    //////////////////////////////////////////////////////////////*/

    function testCreateVestingPosition() public {
        vm.startPrank(otcContract);
        cirxToken.approve(address(vestingContract), VEST_AMOUNT);

        vm.expectEmit(true, false, false, false);
        emit VestingPositionCreated(user, VEST_AMOUNT, block.timestamp);
        vestingContract.createVestingPosition(user, VEST_AMOUNT);
        vm.stopPrank();

        // Check position was created
        (uint256 totalAmount, uint256 startTime, uint256 claimedAmount, uint256 claimableAmount, bool isActive) =
            vestingContract.getVestingInfo(user);

        assertEq(totalAmount, VEST_AMOUNT);
        assertEq(startTime, block.timestamp);
        assertEq(claimedAmount, 0);
        assertEq(claimableAmount, 0); // Nothing claimable immediately
        assertTrue(isActive);
        assertEq(vestingContract.totalVesting(), VEST_AMOUNT);
    }

    function test_RevertIf_CreatePositionUnauthorized() public {
        vm.prank(user);
        vm.expectRevert("Only authorized contracts can create positions");
        vestingContract.createVestingPosition(user2, VEST_AMOUNT);
    }

    function test_RevertIf_CreatePositionZeroUser() public {
        vm.prank(otcContract);
        vm.expectRevert("User address cannot be zero");
        vestingContract.createVestingPosition(address(0), VEST_AMOUNT);
    }

    function test_RevertIf_CreatePositionZeroAmount() public {
        vm.prank(otcContract);
        vm.expectRevert("Vesting amount must be greater than zero");
        vestingContract.createVestingPosition(user, 0);
    }

    function test_RevertIf_CreatePositionAlreadyExists() public {
        // Create first position
        vm.startPrank(otcContract);
        cirxToken.approve(address(vestingContract), VEST_AMOUNT * 2);
        vestingContract.createVestingPosition(user, VEST_AMOUNT);

        // Try to create second position for same user - should fail
        vm.expectRevert("User already has active vesting position");
        vestingContract.createVestingPosition(user, VEST_AMOUNT);
        vm.stopPrank();
    }

    /*//////////////////////////////////////////////////////////////
                        CLAIMABLE AMOUNT CALCULATION TESTS
    //////////////////////////////////////////////////////////////*/

    function testGetClaimableAmountAtStart() public {
        _createVestingPosition(user, VEST_AMOUNT);

        uint256 claimableAmount = vestingContract.getClaimableAmount(user);
        assertEq(claimableAmount, 0);
    }

    function testGetClaimableAmountPartialVesting() public {
        _createVestingPosition(user, VEST_AMOUNT);

        // Fast forward 30 days (1/6 of vesting period)
        vm.warp(block.timestamp + 30 days);

        uint256 claimableAmount = vestingContract.getClaimableAmount(user);
        uint256 expectedAmount = VEST_AMOUNT / 6; // 1/6 of total amount

        assertApproxEqRel(claimableAmount, expectedAmount, 0.01e18); // Within 1%
    }

    function testGetClaimableAmountHalfVesting() public {
        _createVestingPosition(user, VEST_AMOUNT);

        // Fast forward 90 days (half of vesting period)
        vm.warp(block.timestamp + 90 days);

        uint256 claimableAmount = vestingContract.getClaimableAmount(user);
        uint256 expectedAmount = VEST_AMOUNT / 2;

        assertApproxEqRel(claimableAmount, expectedAmount, 0.01e18); // Within 1%
    }

    function testGetClaimableAmountFullVesting() public {
        _createVestingPosition(user, VEST_AMOUNT);

        // Fast forward full vesting period
        vm.warp(block.timestamp + VESTING_DURATION);

        uint256 claimableAmount = vestingContract.getClaimableAmount(user);
        assertEq(claimableAmount, VEST_AMOUNT);
    }

    function testGetClaimableAmountAfterFullVesting() public {
        _createVestingPosition(user, VEST_AMOUNT);

        // Fast forward beyond vesting period
        vm.warp(block.timestamp + VESTING_DURATION + 30 days);

        uint256 claimableAmount = vestingContract.getClaimableAmount(user);
        assertEq(claimableAmount, VEST_AMOUNT);
    }

    function testGetClaimableAmountNoPosition() public view {
        uint256 claimableAmount = vestingContract.getClaimableAmount(user);
        assertEq(claimableAmount, 0);
    }

    /*//////////////////////////////////////////////////////////////
                        TOKEN CLAIMING TESTS
    //////////////////////////////////////////////////////////////*/

    function testClaimTokensPartial() public {
        _createVestingPosition(user, VEST_AMOUNT);

        // Fast forward 90 days (half vesting)
        vm.warp(block.timestamp + 90 days);

        uint256 claimableAmount = vestingContract.getClaimableAmount(user);
        uint256 initialBalance = cirxToken.balanceOf(user);

        vm.prank(user);
        vm.expectEmit(true, false, false, false);
        emit TokensClaimed(user, claimableAmount);
        vestingContract.claimTokens();

        // Check tokens were transferred
        assertEq(cirxToken.balanceOf(user), initialBalance + claimableAmount);

        // Check position was updated
        (,, uint256 claimedAmount, uint256 newClaimableAmount, bool isActive) = vestingContract.getVestingInfo(user);
        assertEq(claimedAmount, claimableAmount);
        assertEq(newClaimableAmount, 0); // Should be 0 after claiming
        assertTrue(isActive); // Still active since not fully claimed
    }

    function testClaimTokensFull() public {
        _createVestingPosition(user, VEST_AMOUNT);

        // Fast forward full vesting period
        vm.warp(block.timestamp + VESTING_DURATION);

        uint256 initialBalance = cirxToken.balanceOf(user);

        vm.prank(user);
        vestingContract.claimTokens();

        // Check all tokens were transferred
        assertEq(cirxToken.balanceOf(user), initialBalance + VEST_AMOUNT);

        // Check position was deactivated
        (,, uint256 claimedAmount, uint256 claimableAmount, bool isActive) = vestingContract.getVestingInfo(user);
        assertEq(claimedAmount, VEST_AMOUNT);
        assertEq(claimableAmount, 0);
        assertFalse(isActive); // Should be deactivated
        assertEq(vestingContract.totalVesting(), 0); // Should be removed from total
    }

    function test_RevertIf_ClaimTokensNothingClaimable() public {
        _createVestingPosition(user, VEST_AMOUNT);

        vm.prank(user);
        vm.expectRevert("No tokens available to claim");
        vestingContract.claimTokens();
    }

    function testMultipleClaims() public {
        _createVestingPosition(user, VEST_AMOUNT);

        // First claim after 60 days
        vm.warp(block.timestamp + 60 days);

        vm.prank(user);
        vestingContract.claimTokens();

        // Second claim after another 60 days
        vm.warp(block.timestamp + 60 days);

        vm.prank(user);
        vestingContract.claimTokens();

        // Final claim after full vesting
        vm.warp(block.timestamp + 60 days);

        vm.prank(user);
        vestingContract.claimTokens();

        // Check total claimed equals vest amount
        assertEq(cirxToken.balanceOf(user), VEST_AMOUNT);

        // Check position is deactivated
        (,,,, bool isActive) = vestingContract.getVestingInfo(user);
        assertFalse(isActive);
    }

    /*//////////////////////////////////////////////////////////////
                        EMERGENCY RECOVERY TESTS
    //////////////////////////////////////////////////////////////*/

    function testEmergencyRecoverNonCIRXTokens() public {
        // Deploy a different token
        CIRXToken differentToken = new CIRXToken(owner, 1000 * 10 ** 18);

        // Send some to vesting contract
        vm.prank(owner);
        differentToken.transfer(address(vestingContract), 100 * 10 ** 18);

        uint256 ownerInitialBalance = differentToken.balanceOf(owner);

        // Owner should be able to recover different tokens
        vm.prank(owner);
        vestingContract.emergencyRecover(address(differentToken), 100 * 10 ** 18);

        assertEq(differentToken.balanceOf(owner), ownerInitialBalance + 100 * 10 ** 18);
    }

    function test_RevertIf_EmergencyRecoverVestingTokens() public {
        _createVestingPosition(user, VEST_AMOUNT);

        // Should fail - cannot recover tokens that are vesting
        vm.prank(owner);
        vm.expectRevert("Cannot recover vesting tokens");
        vestingContract.emergencyRecover(address(cirxToken), VEST_AMOUNT);
    }

    function testEmergencyRecoverExcessCIRXTokens() public {
        _createVestingPosition(user, VEST_AMOUNT);

        // Send extra CIRX to contract
        uint256 extraAmount = 1000 * 10 ** 18;
        vm.prank(owner);
        cirxToken.transfer(address(vestingContract), extraAmount);

        uint256 ownerInitialBalance = cirxToken.balanceOf(owner);

        // Should be able to recover excess CIRX
        vm.prank(owner);
        vestingContract.emergencyRecover(address(cirxToken), extraAmount);

        assertEq(cirxToken.balanceOf(owner), ownerInitialBalance + extraAmount);
    }

    /*//////////////////////////////////////////////////////////////
                        INTEGRATION TESTS
    //////////////////////////////////////////////////////////////*/

    function testMultipleUsers() public {
        uint256 amount1 = 5000 * 10 ** 18;
        uint256 amount2 = 8000 * 10 ** 18;

        _createVestingPosition(user, amount1);
        _createVestingPosition(user2, amount2);

        assertEq(vestingContract.totalVesting(), amount1 + amount2);

        // Fast forward and claim for both users
        vm.warp(block.timestamp + VESTING_DURATION);

        vm.prank(user);
        vestingContract.claimTokens();

        vm.prank(user2);
        vestingContract.claimTokens();

        assertEq(cirxToken.balanceOf(user), amount1);
        assertEq(cirxToken.balanceOf(user2), amount2);
        assertEq(vestingContract.totalVesting(), 0);
    }

    /*//////////////////////////////////////////////////////////////
                        FUZZ TESTS
    //////////////////////////////////////////////////////////////*/

    function testFuzzVestingCalculation(uint256 amount, uint256 timeElapsed) public {
        vm.assume(amount > 0 && amount <= 100000 * 10 ** 18); // Reduced max to stay within available balance
        vm.assume(timeElapsed <= VESTING_DURATION * 2);

        // Give OTC contract enough tokens for the fuzz test
        vm.prank(owner);
        cirxToken.transfer(otcContract, amount);

        _createVestingPosition(user, amount);
        vm.warp(block.timestamp + timeElapsed);

        uint256 claimableAmount = vestingContract.getClaimableAmount(user);

        if (timeElapsed >= VESTING_DURATION) {
            assertEq(claimableAmount, amount);
        } else {
            uint256 expectedAmount = (amount * timeElapsed) / VESTING_DURATION;
            assertEq(claimableAmount, expectedAmount);
        }
    }

    /*//////////////////////////////////////////////////////////////
                        HELPER FUNCTIONS
    //////////////////////////////////////////////////////////////*/

    function _createVestingPosition(address _user, uint256 _amount) internal {
        vm.startPrank(otcContract);
        cirxToken.approve(address(vestingContract), _amount);
        vestingContract.createVestingPosition(_user, _amount);
        vm.stopPrank();
    }
}
