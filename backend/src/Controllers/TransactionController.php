<?php

namespace App\Controllers;

use App\Models\Transaction;
use App\Validators\SwapRequestValidator;
use App\Services\CirxTransferService;
use App\Blockchain\BlockchainClientFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;

class TransactionController
{
    private SwapRequestValidator $validator;
    private CirxTransferService $cirxTransferService;

    public function __construct()
    {
        $this->validator = new SwapRequestValidator();
        $this->cirxTransferService = new CirxTransferService();
    }

    /**
     * Initiate a swap transaction
     */
    public function initiateSwap(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Validate input data
            $validationResult = $this->validator->validate($data);
            if (!$validationResult['valid']) {
                return $this->errorResponse($response, 400, 'Invalid input data.', $validationResult['errors']);
            }

            // Check for duplicate transaction
            $existingTransaction = Transaction::where('payment_tx_id', $data['txId'])->first();
            if ($existingTransaction) {
                return $this->errorResponse($response, 409, 'Transaction with this txId already exists.');
            }

            // Create new transaction record
            $swapId = Uuid::uuid4()->toString();
            $transactionData = [
                'id' => $swapId,
                'payment_tx_id' => $data['txId'],
                'payment_chain' => $data['paymentChain'],
                'cirx_recipient_address' => $data['cirxRecipientAddress'],
                'amount_paid' => $data['amountPaid'],
                'payment_token' => $data['paymentToken'],
                'swap_status' => Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
            ];

            $transaction = Transaction::create($transactionData);

            // Return success response
            $responseData = [
                'status' => 'success',
                'message' => 'Swap request received and being processed.',
                'swapId' => $swapId,
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response
                ->withStatus(202)
                ->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, 500, 'Internal server error.');
        }
    }

    /**
     * Get transaction status
     */
    public function getTransactionStatus(Request $request, Response $response, array $args): Response
    {
        try {
            $swapId = $args['swapId'];
            
            $transaction = Transaction::find($swapId);
            if (!$transaction) {
                return $this->errorResponse($response, 404, 'Transaction not found.');
            }

            $responseData = [
                'status' => $transaction->swap_status,
                'message' => $this->getStatusMessage($transaction->swap_status),
                'txId' => $transaction->payment_tx_id,
            ];

            // Include CIRX transfer transaction ID if available
            if ($transaction->cirx_transfer_tx_id) {
                $responseData['cirxTransferTxId'] = $transaction->cirx_transfer_tx_id;
            }

            $response->getBody()->write(json_encode($responseData));
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, 500, 'Internal server error.');
        }
    }


    /**
     * Create error response
     */
    private function errorResponse(Response $response, int $statusCode, string $message, array $errors = []): Response
    {
        $data = [
            'status' => 'error',
            'message' => $message,
        ];

        if (!empty($errors)) {
            $data['errors'] = $errors;
        }

        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get user-friendly status message
     */
    private function getStatusMessage(string $status): string
    {
        return match ($status) {
            Transaction::STATUS_PENDING_PAYMENT_VERIFICATION => 'Your payment is being verified.',
            Transaction::STATUS_PAYMENT_VERIFIED => 'Payment verified. CIRX transfer is being prepared.',
            Transaction::STATUS_CIRX_TRANSFER_PENDING => 'CIRX transfer is pending.',
            Transaction::STATUS_CIRX_TRANSFER_INITIATED => 'CIRX transfer has been initiated.',
            Transaction::STATUS_COMPLETED => 'Transaction completed successfully.',
            Transaction::STATUS_FAILED_PAYMENT_VERIFICATION => 'Payment verification failed.',
            Transaction::STATUS_FAILED_CIRX_TRANSFER => 'CIRX transfer failed.',
            default => 'Transaction status unknown.',
        };
    }

    /**
     * Get CIRX balance for a given address
     */
    public function getCirxBalance(Request $request, Response $response): Response
    {
        try {
            // Get address from URL path
            $address = $request->getAttribute('address');
            
            if (!$address) {
                return $this->errorResponse($response, 400, 'Address parameter is required.');
            }

            // Validate address format (basic validation)
            if (!preg_match('/^[a-zA-Z0-9]{20,}$/', $address)) {
                return $this->errorResponse($response, 400, 'Invalid address format.');
            }

            // Get CIRX blockchain client
            $clientFactory = new BlockchainClientFactory();
            $cirxClient = $clientFactory->createCirxClient();
            
            // Fetch balance using Circular Protocol API
            $balance = $cirxClient->getCirxBalance($address);
            
            // Return the balance
            $response->getBody()->write(json_encode([
                'success' => true,
                'address' => $address,
                'balance' => $balance,
                'timestamp' => time()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 500, 'Failed to fetch CIRX balance: ' . $e->getMessage());
        }
    }
}