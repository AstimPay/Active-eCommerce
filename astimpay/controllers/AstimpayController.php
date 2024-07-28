<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\SellerPackageController;
use App\Http\Controllers\WalletController;
use App\Library\AstimPay;
use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\CustomerPackagePayment;
use App\Models\Order;
use App\Models\SellerPackage;
use App\Models\SellerPackagePayment;
use App\Models\User;
use App\Models\Wallet;
use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Session;

class AstimpayController extends Controller
{

    public function pay(Request $request)
    {
        if (Session::has('payment_type')) {
            $paymentType = Session::get('payment_type');
            $paymentData = Session::get('payment_data');

            if ($paymentType == 'cart_payment') {
                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
                $amount = $combined_order->grand_total;
            } elseif ($paymentType == 'order_re_payment') {
                $order = Order::findOrFail($paymentData['order_id']);
                $amount = $order->grand_total;
            } elseif ($paymentType == 'wallet_payment') {
                $amount = $paymentData['amount'];
            } elseif ($paymentType == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail($paymentData['customer_package_id']);
                $amount = $customer_package->amount;
            } elseif ($paymentType == 'seller_package_payment') {
                $seller_package = SellerPackage::findOrFail($paymentData['seller_package_id']);
                $amount = $seller_package->amount;
            }
        }

        $requestData = [
            'full_name'    => Auth::user()->name ?? 'John Doe',
            'email'        => Auth::user()->email ?? 'john@doe.com',
            'amount'       => $amount,
            'metadata'     => [
                'user_id'           => Auth::user()->id,
                'payment_type'      => Session::get('payment_type'),
                'combined_order_id' => Session::get('combined_order_id'),
                'payment_data'      => Session::get('payment_data'),
            ],
            'redirect_url' => route('astimpay.success'),
            'return_type'  => 'GET',
            'cancel_url'   => route('astimpay.cancel'),
            'webhook_url'  => route('astimpay.ipn'),
        ];

        try {
            $astimpay = new AstimPay(config('astimpay.api_key'), config('astimpay.api_url'));
            $paymentUrl = $astimpay->initPayment($requestData);
            return redirect($paymentUrl);
        } catch (Exception $e) {
            flash(translate('Something Went Wrong'))->error();
            return redirect()->route('cart');
        }
    }

    public function success(Request $request)
    {
        try {
            $astimpay = new AstimPay(config('astimpay.api_key'), config('astimpay.api_url'));
            $response = $astimpay->verifyPayment($request->invoice_id);
        } catch (Exception $e) {
            flash(translate('Something Went Wrong'))->error();
            return redirect()->route('cart');
        }

        if ($response['status'] !== 'COMPLETED') {
            flash(translate('Your payment is pending for verification.'))->error();
            return redirect()->route('purchase_history.index');
        }

        $payment_type = $response['metadata']['payment_type'];
        $combined_order_id = $response['metadata']['combined_order_id'];
        $payment_data = $response['metadata']['payment_data'];

        if ($payment_type == 'cart_payment') {
            return (new CheckoutController)->checkout_done($combined_order_id, json_encode($response));
        }

        if ($payment_type == 'order_re_payment') {
            return (new CheckoutController)->orderRePaymentDone($payment_data, json_encode($request->all()));
        }

        if ($payment_type == 'wallet_payment') {
            return (new WalletController)->wallet_payment_done($payment_data, json_encode($response));
        }

        if ($payment_type == 'customer_package_payment') {
            return (new CustomerPackageController)->purchase_payment_done($payment_data, json_encode($response));
        }
        if ($payment_type == 'seller_package_payment') {
            return (new SellerPackageController)->purchase_payment_done($payment_data, json_encode($response));
        }
    }

    public function ipn(Request $request)
    {
        try {
            $astimpay = new AstimPay(config('astimpay.api_key'), config('astimpay.api_url'));
            $response = $astimpay->executePayment();
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }

        if ($response['status'] !== 'COMPLETED') {
            Log::error('Your payment is pending for verification.');
        }

        $user_id = $response['metadata']['user_id'];
        $payment_type = $response['metadata']['payment_type'];
        $combined_order_id = $response['metadata']['combined_order_id'];
        $payment_data = $response['metadata']['payment_data'];

        if ($payment_type == 'cart_payment') {
            return (new CheckoutController)->checkout_done($combined_order_id, json_encode($response));
        }

        if ($payment_type == 'order_re_payment') {
            return (new CheckoutController)->orderRePaymentDone($payment_data, json_encode($request->all()));
        }

        if ($payment_type == 'wallet_payment') {
            $user = User::findOrFail($user_id);
            $user->balance = $user->balance + $payment_data['amount'];
            $user->save();

            $wallet = new Wallet;
            $wallet->user_id = $user->id;
            $wallet->amount = $payment_data['amount'];
            $wallet->payment_method = $payment_data['payment_method'];
            $wallet->payment_details = json_encode($response);
            $wallet->save();
        }

        if ($payment_type == 'customer_package_payment') {
            $customer_package = CustomerPackage::findOrFail($payment_data['customer_package_id']);
            $customer_package_id = $payment_data['customer_package_id'];

            $user = User::findOrFail($user_id);
            $user->customer_package_id = $payment_data['customer_package_id'];
            $user->remaining_uploads += $customer_package->product_upload;
            $user->save();

            $customer_package_payment = new CustomerPackagePayment;
            $customer_package_payment->user_id = $user->id;
            $customer_package_payment->customer_package_id = $customer_package_id;
            $customer_package_payment->amount = $customer_package->amount;
            $customer_package_payment->payment_method = $payment_data['payment_method'];
            $customer_package_payment->payment_details = json_encode($response);
            $customer_package_payment->save();
        }

        if ($payment_type == 'seller_package_payment') {
            $user = User::findOrFail($user_id);
            $seller = $user->shop;
            $seller->seller_package_id = $payment_data['seller_package_id'];
            $seller_package = SellerPackage::findOrFail($payment_data['seller_package_id']);
            $seller->product_upload_limit = $seller_package->product_upload_limit;
            $seller->package_invalid_at = date('Y-m-d', strtotime($seller->package_invalid_at . ' +' . $seller_package->duration . 'days'));
            $seller->save();

            $seller_package = new SellerPackagePayment;
            $seller_package->user_id = $user_id;
            $seller_package->seller_package_id = $payment_data['seller_package_id'];
            $seller_package->payment_method = $payment_data['payment_method'];
            $seller_package->payment_details = json_encode($response);
            $seller_package->approval = 1;
            $seller_package->offline_payment = 2;
            $seller_package->save();
        }
    }

    public function cancel(Request $request)
    {
        flash(translate('Payment failed'))->error();
        return redirect()->route('cart');
    }
}
