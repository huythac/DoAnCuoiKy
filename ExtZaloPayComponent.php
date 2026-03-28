<?php

declare(strict_types=1);

namespace App\Controller\Component;

/**
 * ExtZaloPayComponent
 *
 * Xử lý toàn bộ luồng thanh toán qua ZaloPay Open API v2.
 * - Tạo đơn thanh toán (paymentInApp)
 * - Xử lý Return URL và IPN callback (_solveResult)
 * - Cập nhật trạng thái đơn / hoàn tiền (updateOrderState, refund)
 */
class ExtZaloPayComponent extends AppComponent
{
    private string $endpoint;
    private int    $appID = 1819;

    // ─── Khởi tạo ────────────────────────────────────────────────────────────

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->endpoint = ZALOPAY_ENDPOINT;
    }

    // ─── Tạo đơn thanh toán ──────────────────────────────────────────────────

    /**
     * Tạo đơn thanh toán ZaloPay và trả về order_url để redirect người dùng.
     *
     * @param array|string $bill Thông tin đơn hàng hoặc billId
     * @return array  Keys: return_code, order_url, zp_trans_token, ...
     */
    public function paymentInApp(array|string $bill): array
    {
        if (!is_array($bill)) {
            $bill = $this->controller->Bill->getBillInfo($bill);
        }
        if (empty($bill)) {
            return ['code' => 'error'];
        }
        $bill   = &$bill['Bill'];
        $domain = $this->controller->Common->getDomainURL();
        $count  = (int)($bill['payment']['count'] ?? 0);
        $amount = $bill['summary']['remain'];
        $appTransId = showDateFormat('ymd', time(), 7) . '_' . $bill['_id'] . '_' . rand(100, 199999);
        $items = array_map(fn($od) => [
            'itemid'       => !empty($od['product_id']) ? $od['product_id'] : ('BNB' . rand(10000, 99999)),
            'itename'      => $od['name'] ?? 'Sản phẩm quà tặng',
            'itemprice'    => $od['price'],
            'itemquantity' => $od['num'],
        ], $bill['order']);
        $embedData = json_encode([
            'redirecturl'   => $domain . '/checkout/zalopay_complete',
            'promotioninfo' => '',
            'merchantinfo'  => '',
        ]);
        $order = [
            'app_id'       => $this->appID,
            'app_trans_id' => $appTransId,
            'app_user'     => 'Syndium',
            'app_time'     => time() * 1000,
            'amount'       => $amount,
            'item'         => json_encode($items),
            'embed_data'   => $embedData,
            'description'  => 'Syndium - Thanh toán đơn hàng #' . $bill['code'],
            'bankcode'     => '',
            'currency'     => 'VND',
        ];
        $order['mac'] = hash_hmac('sha256', implode('|', [
            $order['app_id'], $order['app_trans_id'], $order['app_user'],
            $order['amount'], $order['app_time'], $order['embed_data'], $order['item'],
        ]), ZALOPAY_MAC_KEY);
        $this->_log('request_payment', json_encode($order), [
            'transaction_id' => $bill['code'],
            'status'         => 'new',
            'grand_total'    => (float)$bill['summary']['grand_total'],
            'reference_id'   => $bill['code'],
        ]);
        $this->controller->Bill->saveWithKeys([
            '_id'                    => _getID($bill['_id']),
            'payment.transaction_no' => $appTransId,
            'payment.amount'         => $amount,
        ]);

        return $this->_doRequest('/v2/create', $order);
    }

    // ─── Xử lý kết quả / IPN ─────────────────────────────────────────────────

    /**
     * Xử lý Return URL (redirect sau thanh toán) và IPN webhook từ ZaloPay.
     *
     * @param array      $result  Dữ liệu từ ZaloPay (GET hoặc POST)
     * @param array|null $bill    Thông tin đơn hàng (tự tra nếu null)
     * @param bool       $ipn     true = IPN webhook, false = Return URL
     */
    public function _solveResult(array $result, ?array $bill = null, bool $ipn = false): void
    {
        $orderId = explode('_', $result['apptransid'])[1];

        // Ghi log kết quả
        $this->_log('payment_result', json_encode($result), [
            'transaction_id' => $result['apptransid'],
            'status'         => $result['status'],
            'grand_total'    => (float)$result['amount'],
            'reference_id'   => $orderId,
        ]);

        // Lấy đơn hàng nếu chưa có
        if (empty($bill)) {
            $bill = $this->controller->Bill->getBillInfo($orderId, [], []);
        }

        // Idempotency: đơn đã thanh toán đủ → bỏ qua
        if ($bill['Bill']['summary']['remain'] <= 0) {
            $this->_respond($ipn, null, '/billstate/' . $orderId);
            return;
        }

        // Đánh dấu session cho trang chi tiết đơn hàng
        $this->controller->Session->write('checkBillOK' . $bill['Bill']['_id'], true);

        // Kiểm tra kết quả giao dịch
        $ok = ((int)$result['status'] === 1 || (int)($result['code'] ?? 0) === 1);
        if (!$ok) {
            $urlFail = $this->controller->Common->createBillStateLink($bill['Bill']['_id']) . '?payment=1';
            if (!$ipn) {
                $this->controller->Flash->error(__('Thanh toán không thành công. Đơn hàng {0}.', $bill['Bill']['code']));
            }
            $this->_respond($ipn, 'error', $urlFail);
            return;
        }

        // Xác thực chữ ký 
        if (isset($result['checksum'])) {
            $rawHash  = implode('|', array_map(
                fn($k) => $result[$k],
                ['appid', 'apptransid', 'pmcid', 'bankcode', 'amount', 'discountamount', 'status']
            ));
            $expected = hash_hmac('sha256', $rawHash, ZALOPAY_CALLBACK_KEY);
            if (!hash_equals($expected, $result['checksum'])) {
                $this->_respond($ipn, 'error',
                    $this->controller->Common->createBillStateLink($bill['Bill']['_id']) . '?payment=1');
                return;
            }
        }

        // Chuẩn bị dữ liệu thanh toán
        $money = !empty($result['checksum'])
            ? $result['amount']
            : $bill['Bill']['payment']['amount'];

        if (empty($money) && $bill['Bill']['payment']['transaction_no'] !== $result['apptransid']) {
            $this->_respond($ipn, null, '/checkout/complete/' . $bill['Bill']['_id']);
            return;
        }

        $paymentData = [
            'bill_id'      => $orderId,
            'money'        => $money,
            'method'       => 25,
            'note'         => __('Khách hàng thanh toán Online qua ZaloPay'),
            'name'         => __('Khách hàng'),
            'payment_info' => $result,
        ];

        // Lưu thanh toán vào đơn hàng
        $this->controller->loadComponent('ExtBill');
        $ret = $this->controller->ExtBill->addPayment($paymentData);

        // Xóa session giỏ hàng
        foreach (['paying', 'order', 'bill', 'cart'] as $key) {
            $this->controller->Session->delete($key);
        }

        $urlOk = '/checkout/complete/' . $bill['Bill']['_id'];
        if ($this->controller->Session->check('pay_from_app')) {
            $urlOk .= '?token=' . $this->controller->Session->read('pay_from_app');
        }

        if ($ret['ok'] !== true) {
            $urlOk = $this->controller->Common->createBillStateLink($bill['Bill']['_id']) . '?payment=1';
            if (!$ipn) {
                $this->controller->Flash->error(__('Lưu thanh toán thất bại. Đơn hàng {0}.', $bill['Bill']['code']));
            }
        }

        $this->_respond($ipn, $ret['ok'] ? null : 'error', $urlOk);
    }

    // ─── Cập nhật trạng thái đơn hàng ────────────────────────────────────────

    /**
     * Đồng bộ trạng thái đơn hàng lên ZaloPay.
     *
     * @param array       $bill
     * @param string|null $newstate 
     * @return array|null
     */
    public function updateOrderState(array $bill, ?string $newstate = null): ?array
    {
        if (empty($bill['sendo_order_id'])) {
            return ['code' => 'error', 'msg' => __('Không tìm thấy sendo_order_id')];
        }

        $state = $newstate ?? $this->_mapBillState((int)($bill['state'] ?? 0));

        if (empty($state) || $state === 'cancel') {
            return null;
        }

        $data = [
            'order_id'  => (string)$bill['sendo_order_id'],
            'bill_code' => (string)$bill['code'],
            'status'    => $state,
            'note'      => '',
        ];

        $this->_log('update_order', json_encode($data), [
            'transaction_id' => $data['order_id'],
            'status'         => $state,
            'grand_total'    => (float)$bill['summary']['grand_total'],
            'reference_id'   => $data['order_id'],
        ]);

        $ret = $this->_doRequest('/update_order', $data);

        $msg = ($ret['error']['status'] ?? 0) == 1
            ? __('Đồng bộ trạng thái đơn hàng thành công')
            : __('Đồng bộ trạng thái đơn hàng thất bại. Xin thử lại!');

        return ['code' => 'ok', 'info' => $ret, 'msg' => $msg];
    }

    /**
     * Hoàn tiền đơn hàng qua ZaloPay.
     *
     * @param array $bill
     * @return array
     */
    public function refund(array $bill): array
    {
        $this->controller->loadComponent('ExtCashFlow');
        $paymentLogs = $this->controller->ExtCashFlow->getPaymentOfBill($bill['_id']);
        $refunds     = [];

        foreach ($paymentLogs as $pay) {
            if ($pay['method'] == 23) {
                $refunds[] = $this->_doRefund($pay['payment_info'], $bill);
            }
        }

        return ['code' => 'ok', 'info' => $refunds, 'msg' => __('Hoàn tiền đơn hàng thành công.')];
    }



    /**
     * Gửi HTTP POST tới ZaloPay API.
     *
     * @param string $path  Đường dẫn API 
     * @param array  $data  Payload gửi đi
     * @return array        JSON decoded response
     */
    private function _doRequest(string $path, array $data): array
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->endpoint . $path,
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        return !empty($response) ? (json_decode($response, true) ?? []) : [];
    }

    /**
     * Ghi log giao dịch ZaloPay vào DB.
     *
     * @param string $type   
     * @param string $json   Payload JSON cần lưu
     * @param array  $order  Thông tin đơn hàng tóm tắt
     */
    private function _log(string $type, string $json, array $order): void
    {
        $this->logIPN([
            'from_source'     => 'zalopay',
            'message_type'    => $type,
            'message_created' => date('Y-m-d H:i:s'),
            'json'            => $json,
            'order'           => $order,
            'create_info'     => ['created' => new \MongoDate()],
        ]);
    }

    /**
     * Hàm logIPN độc lập cho ZaloPay, lưu MongoDB như mô tả trong báo cáo.
     * @param array $data
     */
    public function logIPN(array $data): void
    {
        $this->controller->loadModel('ThirdPartyLog'); 
        $this->controller->ThirdPartyLog->saveWithKeys($data);
    }

    /**
     * Phản hồi kết quả về cho người dùng 
     *
     * @param bool        $ipn      true = IPN, false = browser redirect
     * @param string|null $errCode  null = thành công, 'error' = thất bại
     * @param string      $url      URL để redirect (dùng khi !$ipn)
     */
    private function _respond(bool $ipn, ?string $errCode, string $url): void
    {
        if ($ipn) {
            $this->controller->_responseJson(
                $errCode === 'error'
                    ? ['code' => 'error',   'message' => __('Ghi nhận kết quả thanh toán thất bại!')]
                    : ['code' => 'success', 'message' => __('Ghi nhận kết quả thanh toán thành công!')]
            );
        } else {
            $this->controller->redirect($url);
        }
    }

    private function _mapBillState(int $state): ?string
    {
        return match ($state) {
            1 => 'waiting_to_confirm',
            2 => 'confirmed',
            3 => 'processing',
            4 => 'ready_to_deliver',
            5, 7 => 'on_delivery',
            6 => 'delivery_success',
            8 => 'cancel',
            default => null,
        };
    }

    /**
     * Thực hiện hoàn tiền một giao dịch ZaloPay.
     */
    private function _doRefund(array $params, array $bill): array
    {
        $data = [
            'order_id'  => (string)$bill['sendo_order_id'],
            'bill_code' => (string)$bill['code'],
            'status'    => 'cancel',
            'refund'    => true,
            'note'      => __('Khách hủy đơn hàng này - Hoàn tiền'),
        ];

        $this->_log('refund', json_encode($data), [
            'transaction_id' => $data['order_id'],
            'status'         => 'cancel',
            'grand_total'    => (float)($params['money'] ?? 0),
            'reference_id'   => $data['order_id'],
        ]);

        return $this->_doRequest('/update_order', $data);
    }
}