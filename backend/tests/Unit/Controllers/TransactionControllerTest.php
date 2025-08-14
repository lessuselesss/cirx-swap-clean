<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Controllers\TransactionController;
use App\Models\Transaction;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * @covers \App\Controllers\TransactionController
 */
class TransactionControllerTest extends TestCase
{
    private TransactionController $controller;
    private ServerRequestFactory $requestFactory;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TransactionController();
        $this->requestFactory = new ServerRequestFactory();
        $this->responseFactory = new ResponseFactory();
    }

    public function test_initiate_swap_returns_success_response_with_valid_data()
    {
        $requestData = [
            'txId' => '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234',
            'paymentChain' => 'ethereum',
            'cirxRecipientAddress' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'amountPaid' => '1.0',
            'paymentToken' => 'ETH'
        ];

        $request = $this->createRequest('POST', '/transactions/initiate-swap', $requestData);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->initiateSwap($request, $response);

        $this->assertEquals(202, $result->getStatusCode());
        $this->assertEquals('application/json', $result->getHeaderLine('Content-Type'));

        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('success', $body['status']);
        $this->assertArrayHasKey('swapId', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertEquals('Swap request received and being processed.', $body['message']);
        
        // Verify UUID format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $body['swapId']
        );
    }

    public function test_initiate_swap_creates_transaction_record()
    {
        $requestData = [
            'txId' => '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234',
            'paymentChain' => 'ethereum',
            'cirxRecipientAddress' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'amountPaid' => '1.0',
            'paymentToken' => 'ETH'
        ];

        $request = $this->createRequest('POST', '/transactions/initiate-swap', $requestData);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->initiateSwap($request, $response);
        $body = json_decode((string) $result->getBody(), true);

        // Verify transaction was created in database
        $transaction = Transaction::find($body['swapId']);
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($requestData['txId'], $transaction->payment_tx_id);
        $this->assertEquals($requestData['paymentChain'], $transaction->payment_chain);
        $this->assertEquals($requestData['cirxRecipientAddress'], $transaction->cirx_recipient_address);
        $this->assertEquals('1.000000000000000000', $transaction->amount_paid);
        $this->assertEquals($requestData['paymentToken'], $transaction->payment_token);
        $this->assertEquals('pending_payment_verification', $transaction->swap_status);
    }

    public function test_initiate_swap_returns_error_with_missing_fields()
    {
        $invalidData = [
            'txId' => '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234',
            // Missing required fields
        ];

        $request = $this->createRequest('POST', '/transactions/initiate-swap', $invalidData);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->initiateSwap($request, $response);

        $this->assertEquals(400, $result->getStatusCode());

        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('errors', $body);
    }

    public function test_initiate_swap_returns_error_with_invalid_tx_id_format()
    {
        $requestData = [
            'txId' => 'invalid-tx-id',
            'paymentChain' => 'ethereum',
            'cirxRecipientAddress' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'amountPaid' => '1.0',
            'paymentToken' => 'ETH'
        ];

        $request = $this->createRequest('POST', '/transactions/initiate-swap', $requestData);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->initiateSwap($request, $response);

        $this->assertEquals(400, $result->getStatusCode());

        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertArrayHasKey('errors', $body);
        $this->assertArrayHasKey('txId', $body['errors']);
    }

    public function test_initiate_swap_returns_error_with_invalid_amount()
    {
        $requestData = [
            'txId' => '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234',
            'paymentChain' => 'ethereum',
            'cirxRecipientAddress' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'amountPaid' => '-1.0', // Invalid negative amount
            'paymentToken' => 'ETH'
        ];

        $request = $this->createRequest('POST', '/transactions/initiate-swap', $requestData);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->initiateSwap($request, $response);

        $this->assertEquals(400, $result->getStatusCode());

        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('error', $body['status']);
    }

    public function test_initiate_swap_returns_error_with_unsupported_chain()
    {
        $requestData = [
            'txId' => '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234',
            'paymentChain' => 'unsupported-chain',
            'cirxRecipientAddress' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'amountPaid' => '1.0',
            'paymentToken' => 'ETH'
        ];

        $request = $this->createRequest('POST', '/transactions/initiate-swap', $requestData);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->initiateSwap($request, $response);

        $this->assertEquals(400, $result->getStatusCode());

        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('error', $body['status']);
    }

    public function test_initiate_swap_returns_error_for_duplicate_tx_id()
    {
        $requestData = [
            'txId' => '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234',
            'paymentChain' => 'ethereum',
            'cirxRecipientAddress' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'amountPaid' => '1.0',
            'paymentToken' => 'ETH'
        ];

        // Create existing transaction with same txId
        $existingTransaction = $this->createTransaction([
            'payment_tx_id' => $requestData['txId']
        ]);
        Transaction::create($existingTransaction);

        $request = $this->createRequest('POST', '/transactions/initiate-swap', $requestData);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->initiateSwap($request, $response);

        $this->assertEquals(409, $result->getStatusCode()); // Conflict

        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertStringContainsString('already exists', $body['message']);
    }

    public function test_get_transaction_status_returns_transaction_data()
    {
        $transactionData = $this->createTransaction();
        $transaction = Transaction::create($transactionData);

        $request = $this->createRequest('GET', "/transactions/{$transaction->id}/status");
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->getTransactionStatus($request, $response, ['swapId' => $transaction->id]);

        $this->assertEquals(200, $result->getStatusCode());

        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals($transaction->swap_status, $body['status']);
        $this->assertEquals($transaction->payment_tx_id, $body['txId']);
        $this->assertArrayHasKey('message', $body);
    }

    public function test_get_transaction_status_returns_not_found_for_invalid_id()
    {
        $request = $this->createRequest('GET', '/transactions/invalid-id/status');
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->getTransactionStatus($request, $response, ['swapId' => 'invalid-id']);

        $this->assertEquals(404, $result->getStatusCode());

        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertStringContainsString('not found', $body['message']);
    }

    public function test_get_transaction_status_includes_cirx_transfer_tx_id_when_available()
    {
        $transactionData = $this->createTransaction([
            'swap_status' => 'cirx_transfer_initiated',
            'cirx_transfer_tx_id' => '0xabcdef123456789abcdef123456789abcdef123456789abcdef123456789abcdef'
        ]);
        $transaction = Transaction::create($transactionData);

        $request = $this->createRequest('GET', "/transactions/{$transaction->id}/status");
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->getTransactionStatus($request, $response, ['swapId' => $transaction->id]);

        $this->assertEquals(200, $result->getStatusCode());

        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('cirx_transfer_initiated', $body['status']);
        $this->assertEquals($transactionData['cirx_transfer_tx_id'], $body['cirxTransferTxId']);
    }

    protected function createRequest(string $method, string $uri, array $data = []): Request
    {
        $request = $this->requestFactory->createServerRequest($method, $uri);
        
        if (!empty($data)) {
            $request = $request->withParsedBody($data);
            $request = $request->withHeader('Content-Type', 'application/json');
        }
        
        return $request;
    }
}