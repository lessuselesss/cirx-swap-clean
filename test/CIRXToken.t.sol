// SPDX-License-Identifier: MIT
pragma solidity ^0.8.26;

import "forge-std/Test.sol";
import "../src/tokens/CIRXToken.sol";

contract CIRXTokenTest is Test {
    CIRXToken public token;
    address public owner = address(0x1);
    address public minter = address(0x2);
    address public user = address(0x3);
    address public user2 = address(0x4);

    uint256 public constant MAX_SUPPLY = 1_000_000_000 * 10 ** 18;
    uint256 public constant INITIAL_SUPPLY = 100_000_000 * 10 ** 18;

    event MinterAdded(address indexed minter);
    event MinterRemoved(address indexed minter);

    function setUp() public {
        vm.prank(owner);
        token = new CIRXToken(owner, INITIAL_SUPPLY);
    }

    /*//////////////////////////////////////////////////////////////
                        DEPLOYMENT TESTS
    //////////////////////////////////////////////////////////////*/

    function testDeployment() public view {
        assertEq(token.name(), "Circular");
        assertEq(token.symbol(), "CIRX");
        assertEq(token.decimals(), 18);
        assertEq(token.totalSupply(), INITIAL_SUPPLY);
        assertEq(token.balanceOf(owner), INITIAL_SUPPLY);
        assertEq(token.owner(), owner);
    }

    function testMaxSupply() public view {
        assertEq(token.MAX_SUPPLY(), MAX_SUPPLY);
    }

    function test_RevertIf_DeploymentExceedsMaxSupply() public {
        vm.prank(owner);
        vm.expectRevert("Initial supply exceeds max supply");
        new CIRXToken(owner, MAX_SUPPLY + 1);
    }

    /*//////////////////////////////////////////////////////////////
                        OWNERSHIP TESTS
    //////////////////////////////////////////////////////////////*/

    function testOwnershipTransfer() public {
        vm.prank(owner);
        token.transferOwnership(user);

        assertEq(token.owner(), user);
    }

    function test_RevertIf_UnauthorizedOwnershipTransfer() public {
        vm.prank(user);
        vm.expectRevert();
        token.transferOwnership(user2);
    }

    /*//////////////////////////////////////////////////////////////
                        MINTER MANAGEMENT TESTS
    //////////////////////////////////////////////////////////////*/

    function testAddMinter() public {
        vm.prank(owner);
        vm.expectEmit(true, false, false, false);
        emit MinterAdded(minter);
        token.addMinter(minter);

        assertTrue(token.minters(minter));
    }

    function testRemoveMinter() public {
        // First add minter
        vm.prank(owner);
        token.addMinter(minter);

        // Then remove
        vm.prank(owner);
        vm.expectEmit(true, false, false, false);
        emit MinterRemoved(minter);
        token.removeMinter(minter);

        assertFalse(token.minters(minter));
    }

    function test_RevertIf_AddZeroAddressMinter() public {
        vm.prank(owner);
        vm.expectRevert("Cannot add zero address as minter");
        token.addMinter(address(0));
    }

    function test_RevertIf_UnauthorizedAddMinter() public {
        vm.prank(user);
        vm.expectRevert();
        token.addMinter(minter);
    }

    function test_RevertIf_UnauthorizedRemoveMinter() public {
        vm.prank(owner);
        token.addMinter(minter);

        vm.prank(user);
        vm.expectRevert();
        token.removeMinter(minter);
    }

    /*//////////////////////////////////////////////////////////////
                        MINTING TESTS
    //////////////////////////////////////////////////////////////*/

    function testMintByAuthorizedMinter() public {
        vm.prank(owner);
        token.addMinter(minter);

        uint256 mintAmount = 1000 * 10 ** 18;
        uint256 initialBalance = token.balanceOf(user);
        uint256 initialSupply = token.totalSupply();

        vm.prank(minter);
        token.mint(user, mintAmount);

        assertEq(token.balanceOf(user), initialBalance + mintAmount);
        assertEq(token.totalSupply(), initialSupply + mintAmount);
    }

    function test_RevertIf_MintByUnauthorizedAddress() public {
        uint256 mintAmount = 1000 * 10 ** 18;

        vm.prank(user);
        vm.expectRevert("Only authorized minters can mint");
        token.mint(user2, mintAmount);
    }

    function test_RevertIf_MintExceedsMaxSupply() public {
        vm.prank(owner);
        token.addMinter(minter);

        // Try to mint amount that would exceed max supply
        uint256 excessiveAmount = MAX_SUPPLY - token.totalSupply() + 1;

        vm.prank(minter);
        vm.expectRevert("Minting would exceed max supply");
        token.mint(user, excessiveAmount);
    }

    function testMintUpToMaxSupply() public {
        vm.prank(owner);
        token.addMinter(minter);

        // Mint exactly up to max supply
        uint256 remainingSupply = MAX_SUPPLY - token.totalSupply();

        vm.prank(minter);
        token.mint(user, remainingSupply);

        assertEq(token.totalSupply(), MAX_SUPPLY);
    }

    /*//////////////////////////////////////////////////////////////
                        ERC20 FUNCTIONALITY TESTS
    //////////////////////////////////////////////////////////////*/

    function testTransfer() public {
        uint256 transferAmount = 1000 * 10 ** 18;

        vm.prank(owner);
        bool success = token.transfer(user, transferAmount);

        assertTrue(success);
        assertEq(token.balanceOf(user), transferAmount);
        assertEq(token.balanceOf(owner), INITIAL_SUPPLY - transferAmount);
    }

    function testApproveAndTransferFrom() public {
        uint256 approvalAmount = 1000 * 10 ** 18;

        // Owner approves user to spend tokens
        vm.prank(owner);
        token.approve(user, approvalAmount);

        assertEq(token.allowance(owner, user), approvalAmount);

        // User transfers tokens from owner to user2
        vm.prank(user);
        bool success = token.transferFrom(owner, user2, approvalAmount);

        assertTrue(success);
        assertEq(token.balanceOf(user2), approvalAmount);
        assertEq(token.balanceOf(owner), INITIAL_SUPPLY - approvalAmount);
        assertEq(token.allowance(owner, user), 0);
    }

    /*//////////////////////////////////////////////////////////////
                        ERC20 PERMIT TESTS
    //////////////////////////////////////////////////////////////*/

    function testPermitDomainSeparator() public view {
        // Test that domain separator is set correctly
        bytes32 domainSeparator = token.DOMAIN_SEPARATOR();
        assertTrue(domainSeparator != bytes32(0));
    }

    function testPermit() public {
        uint256 privateKey = 0xBEEF;
        address owner_ = vm.addr(privateKey);

        // Give owner some tokens first
        vm.prank(owner);
        token.transfer(owner_, 1000 * 10 ** 18);

        uint256 permitAmount = 500 * 10 ** 18;
        uint256 nonce = token.nonces(owner_);
        uint256 deadline = block.timestamp + 1 hours;

        // Create permit signature
        bytes32 structHash = keccak256(
            abi.encode(
                keccak256("Permit(address owner,address spender,uint256 value,uint256 nonce,uint256 deadline)"),
                owner_,
                user,
                permitAmount,
                nonce,
                deadline
            )
        );

        bytes32 hash = keccak256(abi.encodePacked("\x19\x01", token.DOMAIN_SEPARATOR(), structHash));
        (uint8 v, bytes32 r, bytes32 s) = vm.sign(privateKey, hash);

        // Execute permit
        token.permit(owner_, user, permitAmount, deadline, v, r, s);

        assertEq(token.allowance(owner_, user), permitAmount);
        assertEq(token.nonces(owner_), nonce + 1);
    }

    /*//////////////////////////////////////////////////////////////
                        FUZZ TESTS
    //////////////////////////////////////////////////////////////*/

    function testFuzzMint(uint256 amount) public {
        vm.assume(amount > 0);
        vm.assume(amount <= MAX_SUPPLY - token.totalSupply());

        vm.prank(owner);
        token.addMinter(minter);

        uint256 initialSupply = token.totalSupply();

        vm.prank(minter);
        token.mint(user, amount);

        assertEq(token.totalSupply(), initialSupply + amount);
        assertEq(token.balanceOf(user), amount);
    }

    function testFuzzTransfer(uint256 amount) public {
        vm.assume(amount > 0);
        vm.assume(amount <= INITIAL_SUPPLY);

        uint256 initialOwnerBalance = token.balanceOf(owner);

        vm.prank(owner);
        token.transfer(user, amount);

        assertEq(token.balanceOf(user), amount);
        assertEq(token.balanceOf(owner), initialOwnerBalance - amount);
    }

    /*//////////////////////////////////////////////////////////////
                        INTEGRATION TESTS
    //////////////////////////////////////////////////////////////*/

    function testMultipleMinters() public {
        address minter2 = address(0x5);
        uint256 mintAmount = 1000 * 10 ** 18;

        // Add multiple minters
        vm.startPrank(owner);
        token.addMinter(minter);
        token.addMinter(minter2);
        vm.stopPrank();

        // Both should be able to mint
        vm.prank(minter);
        token.mint(user, mintAmount);

        vm.prank(minter2);
        token.mint(user2, mintAmount);

        assertEq(token.balanceOf(user), mintAmount);
        assertEq(token.balanceOf(user2), mintAmount);
        assertEq(token.totalSupply(), INITIAL_SUPPLY + (mintAmount * 2));
    }

    function testMinterRemovalStopsMinting() public {
        uint256 mintAmount = 1000 * 10 ** 18;

        // Add and then remove minter
        vm.startPrank(owner);
        token.addMinter(minter);
        token.removeMinter(minter);
        vm.stopPrank();

        // Minting should fail
        vm.prank(minter);
        vm.expectRevert("Only authorized minters can mint");
        token.mint(user, mintAmount);
    }

    /*//////////////////////////////////////////////////////////////
                        EDGE CASE TESTS
    //////////////////////////////////////////////////////////////*/

    function testMintZeroAmount() public {
        vm.prank(owner);
        token.addMinter(minter);

        uint256 initialSupply = token.totalSupply();

        vm.prank(minter);
        token.mint(user, 0);

        // Supply should not change
        assertEq(token.totalSupply(), initialSupply);
        assertEq(token.balanceOf(user), 0);
    }

    function testMultipleConsecutiveMints() public {
        vm.prank(owner);
        token.addMinter(minter);

        uint256 mintAmount = 1000 * 10 ** 18;
        uint256 iterations = 5;

        for (uint256 i = 0; i < iterations; i++) {
            vm.prank(minter);
            token.mint(user, mintAmount);
        }

        assertEq(token.balanceOf(user), mintAmount * iterations);
        assertEq(token.totalSupply(), INITIAL_SUPPLY + (mintAmount * iterations));
    }
}
