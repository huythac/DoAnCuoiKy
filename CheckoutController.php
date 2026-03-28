<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;

class CheckoutController extends AppController
{
    public string $name = 'Checkout';
    public array $admin_fields_get = array('_id');
    public string $layout = 'admin';
    public array $allowedActions = [
        'onestep', 'shipfee', 'querydr', 'reset', 'add2cart', 'save', 'added', 'payment', 'complete', 'fail', 'chkFirstOrder', 'qrpayment', 'chkDiscount', 'prepayment', 'cart', 'customer', 'delivery', 'codeVerifyPhone', 'quickorder', 'vnpay_return', 'VnPayIPN', 'createBillWeekly', 'paymentGateway',
        'createBill', 'shopeePay', 'shopeePayVerify', 'shopeePayNTS', 'appPayment', 'pay', 'paynotify', 'payconfirm', 'cancelpayment', 'paymethods', 'order', 'checkCodePhone', 'paypal', 'ppcomplete', 'momo_complete', 'momo_notify', 'prepareBill', 'kredivoReturn', 'saveStepOrder', 'inpreceiver',
        'mobileprepayment', 'checkOtherPaymentCode', 'savegiftcard', 'checkGiftCard', 'removeGiftCardInUsed', 'smartpay', 'apotapay', 'zalopay_complete', 'zalopay_notify', 'receiver'
    ];

    public function initialize(): void
    {
        parent::initialize();
        foreach (array("ExtCheckout", 'ExtProduct', 'ExtBillLog', 'ExtUser', 'ExtShop', 'ExtBill', 'ExtShopHoliday') as $c) {
            $this->loadComponent($c);
        }
        foreach (array('Product', 'Shop', 'User', "Bill", "Category", 'BillLog') as $m) {
            $this->loadModel($m);
        }
    }

    /**
     * Kiểm tra hoá đơn thanh toán
     * @param int $billd_id
     * @param string $method
     * @return void
     * @access public
     *
     */
    public function appPayment($bill_id, $method)
    {
        $bill = $this->Bill->getBillInfo($bill_id, ['create_info.status' => ['$ne' => 9]]);

        if (empty($bill)) {
            $this->redirect('/checkout/fail');
        }

        $this->Session->write('paying', $bill_id);
        $sskey = "checkBillOK" . (string)$bill_id;
        $this->Session->write($sskey, true);

        $bill['Bill']['payment']['method'] = $method;
        $update = ['_id' => _getID($bill_id), 'payment.method' => $method];
        $this->Bill->saveWithKeys($update);

        $ret = $this->ExtCheckout->_doPayment($bill['Bill']);

        $this->redirect($ret['redirect']);

        // $this->ExtCheckout->payment($bill);
    }

    /**
     * đã thêm vào giỏ hàng
     * @param int|null $pid
     * @return void
     * @access public
     */
    public function added($pid = null)
    {

        $p = $this->_getRawInfo($pid, "products", ['_id', 'code', 'name', 'thumb', 'images', 'source.database']);

        if (empty($p)) {
            $this->Flash->error(__('Dữ liệu không hợp lệ.'));
            $this->redirect('/', 301);
        }

        $collection = Photo;

        if (!empty($p['source']['database'])) {
            $collection .= '-' . $p['source']['database'];
        }

        if (empty($p['thumb']) && !empty($p['images'])) {
            $p['thumb'] = $p['images'][0];
        }

        $p['image_url'] = setImage($p['thumb'] ?? "", $collection, "420x0-cc");

        $this->set('p', $p);

        $this->set("title_for_layout", __('Đã thêm sản phẩm vào giỏ hàng'));
    }

    /**
     * Thêm vào đơn hàng
     * @return void
     * @access public
     */
    public function add2cart()
    {

        // xóa đi lần đếm sai
        $this->Session->delete("giftpop.counter");

        $data = $this->_getPostData();

        if (!empty($data)) {
            $data = &$data['Checkout'];
        } elseif (!empty($_GET['data'])) {

            if ($this->isMobile) {
                $data = $_GET['data'];
            } else {
                $this->redirect($this->referer());
            }
        }

        $ret = $this->ExtCheckout->add2cart($data);
        if($ret){
            // cap nhat lai current city && current district
            if (!empty($data['Bill']['to_user']['location']['district_id'])) {
                $this->Common->setCurrentDistrict($data['Bill']['to_user']['location']['district_id']);
            }

            if (!empty($data['phone_number'])) {
                $ret = $this->ExtCheckout->saveQuickOrder($data);
                $this->redirect($ret['redirect']);
            } else {
                $this->Session->delete('checkout.cart');

                // $this->redirect($this->referer());

                if ($this->_isAjaxRequest()) {
                    $this->_responseJson(['code' => 'ok', 'msg' => __('Thêm sản phẩm vào giỏ hàng thành công')]);
                }

                $this->redirect('/checkout/cart');
            }
        } else {
            if ($this->_isAjaxRequest()) {
                $this->_responseJson(['code' => 'error', 'msg' => __('Thêm sản phẩm vào giỏ hàng thất bại. Xin thử lại sau.')]);
            } else {
                $this->redirect($ret['redirect']);
            }
        }



    }

    /**
     * Chuẩn bị dữ liệu cho mỗi bước đặt hàng
     * @param mixed $target
     * @param mixed $location
     * @param mixed $occasion
     * @return void
     * @access public
     */
    public function quickorder($target = null, $location = null, $occasion = null)
    {

        $occasion = $location;
        $userId = $this->Auth->user("_id");

        $location = $this->request->getParam('province');

        $quickOrder = $this->Session->read("quickOrder");
        $data = $this->_getPostData();
        if (!empty($data)) {

            // reset bills
            if ($this->Session->check('cart')) {
                $this->Session->delete('cart');
                $this->Session->delete('addCartTime');
                $this->Common->deleteOrderSearch();
            }

            if ($data['Checkout']['step'] == 2) {

                $location = $data['Checkout']['province_slug'];

                $this->loadModel("Province");
                $this->loadModel("District");
                $step2 = $data['Checkout'];

                $p = $this->Province->getProvinceBySlug($location, ['_id', 'slug', 'name']);

                $step2 = am($step2, $p['Province']);
                $step2['show_text'] = __('Giao đến: {0}', $step2['name']);

                $quickOrder['step2'] = $step2;

                $d = $this->District->getById($step2['district_id'], ['_id', 'slug', 'name']);
                $d['slug'] = _SEO($d['name']);

                $this->Session->write("current_city", $step2['slug']);
                $this->Session->write("current_city_obj", $p['Province']);

                $this->Session->write("current_district", $step2['district_id']);
                $this->Session->write("current_district_obj", $d);

                $this->Session->write("quickOrder", $quickOrder);

                $this->redirect('/' . $this->countryInfo['slug'] . '/checkout/quickorder/' . $target . '/' . $location);
            }
        } else {

            if (empty($quickOrder)) {
                $quickOrder = [];
            }

            // kiểm tra xem đã có những thông tin nào trên URL để tạo quickOrrder
            if (!empty($target) && empty($quickOrder['step1'])) {
                $this->loadComponent('ExtRelationship');
                $quickOrder['step1'] = $this->ExtRelationship->getDetail($target, false, ['_id', 'slug', 'name', 'date', 'date_attr', 'price_from']);
                $quickOrder['step1']['show_text'] = __('Tặng: {0}', $quickOrder['step1']['name']);
            }

            if (!empty($location) && empty($quickOrder['step2'])) {

                $this->loadModel("Province");
                // $this->loadModel("District");
                $step2 = [];

                $p = $this->Province->getProvinceBySlug($location, ['_id', 'slug', 'name']);

                $step2 = am($step2, $p['Province']);
                $step2['show_text'] = __('Giao đến: {0}', $step2['name']);

                $quickOrder['step2'] = $step2;

                $this->Session->write("current_city", $step2['slug']);
                $this->Session->write("current_city_obj", $p['Province']);
                $this->Session->delete("current_district");
                $this->Session->delete("current_district_obj");
            }

            if (!empty($occasion) && empty($quickOrder['step3'])) {

                $this->loadComponent('ExtOccasion');
                $quickOrder['step3'] = $this->ExtOccasion->getDetail($occasion, false);
                $quickOrder['step3']['show_text'] = __('Dịp: {0}', $quickOrder['step3']['name']);
            }


            // neu location khac voi location da chon truoc do
            if (!empty($quickOrder['step2']['slug']) && !empty($location) && $location != $quickOrder['step2']['slug']) {

                $p = $this->Province->getProvinceBySlug($location, ['_id', 'slug', 'name']);


                $step2 = [];
                $step2 = am($step2, $p['Province']);
                $step2['show_text'] = __('Giao đến: {0}', $step2['name']);


                $quickOrder['step2'] = $step2;

                $this->Session->write("current_city", $step2['slug']);
                $this->Session->delete("current_district");
                $this->Session->write("current_city_obj", $p['Province']);
            }
        }



        if (!empty($occasion)) {

            $this->loadComponent('ExtOccasion');
            $quickOrder['step3'] = $this->ExtOccasion->getDetail($occasion, false);
            $quickOrder['step3']['show_text'] = __('Dịp: {0}', $quickOrder['step3']['name']);


            $this->loadComponent('ExtBill');
            $update = [];

            if (!empty($_GET['date'])) {

                $date = $this->Common->date_convert($_GET['date']);
                $dateInt = xtostrtotime($date . ' 00:00:00');

                if ($dateInt < time()) {
                    $dateInt =  xtostrtotime(date(date("Y") . date("/m/d", $dateInt)));
                    if ($dateInt < time()) {
                        $dateInt += YEAR;
                    }
                }

                // lưu ngày giao vào bước 1
                $quickOrder['step1']['date'] = showDateFormat("d/m/Y", $dateInt);
                $quickOrder['step1']['hour'] = '8-12';

                $$update['to_user']['date'] = $quickOrder['step1']['date'];
            }


            $update['to_user']['occasion_id'] = $quickOrder['step3']['_id'];

            $this->ExtBill->updateCurrentBill($update);

            $this->Session->write("quickOrder", $quickOrder);

            // load danh sách các sản phẩm quà tặng
            $this->loadComponent("ExtProduct");
            // $masterId = $this->Session->read('master_id');
            // if(empty($masterId)){
            //     $masterId = '6066c157066c9a03055f2b10';
            // }

            $_params = [
                // 'relationship_id' => (string)$quickOrder['step1']['_id'],
                // 'occasion_id' => (string)$quickOrder['step3']['_id'],
                // 'master_id' => $masterId
            ];

            $limit = 40;
            list($products, $total, $elastic) = $this->ExtProduct->loadProducts($_params, $limit, 0);

            // pr($elastic);
            // pr($products);

            $this->set("products", $products);
            $this->Common->setCountryInfo();


            $viewFile = 'step4';
            $title = __('Bước 4: Chọn quà tặng');
        } else if (!empty($location)) {

            //-----------------------------------------------------
            // lay danh sach ngay le, dip tang
            //-----------------------------------------------------
            $cond = ['default' => true];
            if (!empty($quickOrder['step1']['_id'])) {
                $cond['relationship_id'] = $quickOrder['step1']['_id'];
            }


            $this->loadComponent('ExtOccasion');
            list($occasions, $total) = $this->ExtOccasion->getAll($cond);
            $occasionGroup = $this->ExtOccasion->group($occasions, @$quickOrder['step2']['slug']);


            //-----------------------------------------------------
            // Lấy những dịp đã lưu
            //-----------------------------------------------------
            $savedList = null;
            $_t = null;

            if (!empty($quickOrder['step1']['relative']['_id'])) {
                // người này đã thêm vào danh sách, lấy những ngày đã lưu ra
                $cond = ['relative_id' =>  (string)$quickOrder['step1']['relative']['_id']];
                list($savedList, $_t, $occ, $relatives, $action) = $this->ExtUser->getRelativeOccassions($cond, 0, 99, false);

                // reindex occasion
                $occasions = $this->Common->reindexKeyArray($occasions);
            }


            $this->set('occasionGroup', $occasionGroup);
            $this->set('occasions', $occasions);
            $this->set('savedList', $savedList);
            $this->set('savedListCount', $_t);

            $viewFile = 'step3';
            $title = __('Bước 3: Chọn dịp tặng quà');
        } else if (!empty($target)) {

            $this->loadComponent('ExtRelationship');


            $quickOrder['step1'] = $this->ExtRelationship->getDetail($target, false, ['_id', 'slug', 'name', 'date', 'date_attr', 'price_from']);
            $quickOrder['step1']['show_text'] = __('Tặng: {0}', $quickOrder['step1']['name']);


            $viewFile = 'step2';
            $title = __('Bước 2: Chọn khu vực');
        } else {

            // lay danh sach cac moi quan he
            $this->loadComponent('ExtRelationship');


            $cond = ['default' => true];
            list($relationships, $total) = $this->ExtRelationship->getAll($cond);

            $relationGroup = $this->ExtRelationship->group($relationships, @$quickOrder['step1']['slug']);

            if (!empty($userId)) {
                $this->loadComponent('ExtUser');

                $existsRelationGroup = $this->ExtUser->getExistsRelatives($relationGroup);
            } else {
                $existsRelationGroup = null;
            }


            $this->set('relationGroup', $relationGroup);
            $this->set('existsRelationGroup', $existsRelationGroup);

            $title = __('Bước 1: Chọn người nhận');
            $viewFile = 'step1';
        }

        if (empty($products)) {
            $this->Session->write("quickOrder", $quickOrder);
        }

        $this->_updatePostDataToView(null, $data);

        $this->set('quickOrder', $quickOrder);

        $this->set("title_for_layout", $title);
        $this->render($viewFile);
    }

    /**
     * Tạo hoá đơn
     * @param string $id
     * @return void
     * @access public
     */
    public function createBill($id)
    {
        // $this->loadComponent("ExtChatbotTool");
        // $this->ExtChatbotTool->sendMsg('1418198874948803', 'Quý khách đã đặt hàng thành công. Mã đơn hàng là #H7N123');
        // exit;
        if (strtolower($id) === 'none') {
            $price = @(float)$_GET['price'];
            if ($price < 100000) {
                $price *= 1000;
            }

            $product = ['Product' => [
                'name' => 'Tự thiết kế',
                '_id' => '',
                'image' => '',
                'prices' => ['regular' => $price, 'regular_show' => $price],
                'shop_id' => '58672d62f8bf0ee020000033',
                'code' => 'NONE',
            ]];
        } else {
            $id = strtoupper($id);
            $product = $this->ExtProduct->getDetail($id, [], false, 4);
            if (empty($product)) {
                $this->Flash->error(__('Rất tiếc! Chúng tôi không tim thấy sản phẩm bạn muốn xem. Xin vui lòng thử lại ạ.'));
                $this->redirect('/');
            }
        }

        $this->Session->write('createBill', $_GET);

        //$orders = $this->Session->read('cart');

        $orders = [
            [
                'name' => $product['Product']['name'],
                'num' => 1,
                '_id' => $product['Product']['_id'],
                'product_id' => $product['Product']['_id'],
                'code' => $product['Product']['code'],
                'image' => $product['Product']['image'],
                'price' => $product['Product']['prices']['regular_show'],
                'shop_id' => $product['Product']['shop_id'],
            ]
        ];


        // tạo message

        $note = '';
        if (!empty($_GET['note'])) {
            $note = "Ghi chú: " . $_GET['note'];
        }

        if (!empty($_GET['gene'])) {
            $gene = $_GET['gene'] == 1 ? 'nam' : 'nữ';
            $note .= "\nTặng cho: " . $gene;
        }

        if (!empty($_GET['cate'])) {
            $this->loadModel('Category');
            $cate = $this->Category->getACategoryBySlug($_GET['cate']);
            $note .= "\nHoa tặng: " . $cate['Category']['name'];
        }

        if (!empty($_GET['design'])) {
            $this->loadModel('Codetbl');
            $designs = $this->Codetbl->getList('PRO', 'DSG');
            $design = $designs[$_GET['design']];
            $note .= "\nThiết kế dạng: " . $design;
        }

        $this->loadComponent("ExtShoppingCart");
        $this->ExtShoppingCart->update($orders);
        // $this->Session->write('cart', $orders);

        $checkout = $this->Session->read('checkout');
        $data = $this->_getPostData();
        if (!empty($data)) {

            $this->Session->write('checkout.prepayment', $data);

            // tạo đơn hàng
            $bill = $checkout['delivery'];

            if (!empty($checkout['customer'])) {
                $bill = array_merge_recursive($bill, $checkout['customer']);
            } else {
                $bill['Bill']['customer_id'] = $this->Auth->user('_id');
            }

            if (!empty($checkout['cart'])) {
                $bill = array_merge_recursive($bill, $checkout['cart']);
            }

            $bill = array_merge_recursive($bill, $data);
            $bill['Bill']['order'] = $orders;
            $bill['Bill']['cus_from'] = 7; // from chatbot
            $bill['Bill']['design_id'] = @$_GET['design'];
            // $bill['Bill']['design_id'] = @$_GET['design'];



            $ret = $this->ExtCheckout->saveOrderNew($bill);

            $this->redirect($ret['redirect']);
        } else {
            $data = $this->Session->read('checkout.prepayment');
        }

        $discount = [];

        // Kiểm tra mã giảm giá
        if (!empty($checkout['cart']['Bill']['summary']['discount']['promote_code'])) {
            $code = trim((string)$checkout['cart']['Bill']['summary']['discount']['promote_code']);
            $shop_id = $this->Auth->user('shop_id');


            $discount = $this->ExtCheckout->chkDiscount($shop_id, $code);
            // $discount['code'] = $code;

            if (!empty($discount)) {

                if ($discount['apply_for'] == 1) {
                    $subtotal = 0;
                    foreach ($orders as $key => $value) {
                        if (!empty($value['price_discount'])) {
                            $subtotal += @($value['price_discount'] * $value['num']);
                        } else {
                            $subtotal += @($value['price'] * $value['num']);
                        }
                    }

                    // $ship_fee = @(float)$checkout['delivery']['Bill']['summary']['ship_fee'];

                    // if(!empty($ship_fee)){
                    //     $subtotal += $ship_fee;
                    // }

                    if (!empty($discount['percent'])) {
                        $discount['money'] = round(($discount['percent'] * $subtotal) / 100, 0);
                    }

                    // $discount['money'] = $discount['money'];
                    // $discount['percent'] = @$discount['percent'];
                } else {
                }
            }
        }


        $this->loadModel('Province');
        $this->loadModel('District');
        // lấy thông tin tỉnh thành
        $provinces = $this->Province->getListProvinces();

        $keys = array_keys($provinces);
        $districts = $this->District->getList($keys[0]);

        $user_id = $this->Auth->user('_id');
        $address_list = [];

        if (!empty($user_id)) {
            $this->loadModel("UserAddress");
            $address_list = $this->UserAddress->getAddressOfUser($user_id);
        }

        if (!empty($_GET['customer_phone'])) {
            $u = $this->User->getUserByPhone($_GET['customer_phone'], []);

            @$this->Auth->login($u['User']);
        } else if (!empty($_GET['customer_email'])) {
            $u = $this->User->getUserByEmail($_GET['customer_email'], []);
            @$this->Auth->login($u['User']);
        }

        $this->_updatePostDataToView(null, $data);

        $addressTypes = $this->_getCodeList('list', 'ADR', 'TPE');

        $this->set('addressTypes', $addressTypes);
        $this->set('address_list', $address_list);
        $this->set('provinces', $provinces);
        $this->set('districts', $districts);
        $this->set('note', $note);


        $this->loadModel('Codetbl');
        $payments = $this->Codetbl->getAll('PAY', 'TPE');

        // loại cái trả tiền mặt
        // unset($payments[0]);

        $this->loadModel("Shop");
        $shop = $this->Shop->getAShop($orders[0]['shop_id']);


        $this->set('checkout', $checkout);
        $this->set('shop', $shop);
        $this->set('discount', $discount);
        $this->set('payments', $payments);
        $this->set('cart', $orders);

        $this->layout = 'mobile';
    }
    /**
     * Tạo đơn hàng địnhkỳ
     * @param string $id
     * @return void
     * @access public
     */
    public function createBillWeekly($id)
    {

        // tách lấy id
        $id = strtoupper($id);

        if (empty($id)) {
            $this->Session->write('Rất tiếc! Chúng tôi không tim thấy gói hoa bạn muốn xem. Xin vui lòng thử lại ạ.');
            $this->redirect('/');
        }

        $product = $this->ExtProduct->getDetail($id, [], false, 4);
        if (empty($product)) {
            $this->Flash->error(__('Rất tiếc! Chúng tôi không tim thấy gói hoa bạn muốn xem. Xin vui lòng thử lại ạ.'));
            $this->redirect('/');
        }

        // Lưu đơn hàng
        $data = $this->_getPostData();
        if (!empty($data)) {
            $this->Session->write('fromSource', 'layout_mobile');

            $ret = $this->ExtCheckout->saveTienLoi($data);

            if ($ret['code'] == 'ok') {
                $this->redirect($ret['redirect']);
            }
        }


        // lấy mã chính xác của gói hoa định kỳ
        $product = $this->ExtProduct->getDetail($product['Product']['belong_to'], [], false, 4);

        $this->loadModel('Province');
        $this->loadModel('District');
        // lấy thông tin tỉnh thành
        $provinces = $this->Province->getListProvinces();

        $keys = array_keys($provinces);
        $districts = $this->District->getList($keys[0]);


        $user_id = $this->Auth->user('_id');
        $address_list = [];
        if (!empty($user_id)) {
            $this->loadModel("UserAddress");
            $address_list = $this->UserAddress->getAddressOfUser($user_id);
        }

        if (!empty($_GET['customer_phone'])) {
            $u = $this->User->getUserByPhone($_GET['customer_phone'], []);

            @$this->Auth->login($u['User']);
        } else if (!empty($_GET['customer_email'])) {
            $u = $this->User->getUserByEmail($_GET['customer_email'], []);
            @$this->Auth->login($u['User']);
        }

        if (!empty($u)) {
            $data['Bill']['Customer'] = @$u['User'];
        }

        $this->loadModel('Codetbl');
        $payments = $this->Codetbl->getAll('PAY', 'TPE');

        $this->set('payments', $payments);

        $this->set('address_list', $address_list);
        $this->set('provinces', $provinces);
        $this->set('districts', $districts);

        $this->set('product', $product['Product']);
        $this->set('user', $u['User']);

        $this->set('title_for_layout', $product['Product']['name']);
        $this->set('desc_for_layout', $product['Product']['intro']);
        $this->set('image_for_layout', $product['Product']['image']);
        $this->layout = 'mobile';
    }

    /**
     * Cập nhật số lượng đặt hàng
     */
    // public function updnum()
    // {
    //     $this->_requiredAjax();

    //     if(!empty($_POST)){
    //         $this->loadComponent("ExtShoppingCart");
    //         $cart = $this->ExtShoppingCart->_getCurrentCart();

    //         // $cart = $this->Session->read('cart');
    //         $cart[$_POST['key']]['num'] = abs($_POST['num']);
    //         // $this->Session->write('cart', $cart);
    //         $this->ExtShoppingCart->update($cart);

    //         $this->_responseJson(['code' => 'ok']);
    //     } else {
    //         $this->_responseJson(['code' => 'error', 'msg' => 'Cập nhật số lượng đặt hàng thất bại!']);
    //     }
    // }

    /**
     * ZaloPay init payment
     * @param string|int $bill_id
     * @return void
     * @access public
     */
    public function zalopay_payment($bill_id)
    {
        $this->loadComponent('ExtZaloPay');
        $result = $this->ExtZaloPay->paymentInApp($bill_id);

        if (!empty($result['order_url'])) {
            $this->redirect($result['order_url']);
        } else {
            $this->Flash->error(__('Khởi tạo thanh toán ZaloPay thất bại!'));
            $this->redirect('/checkout/fail');
        }
    }

    /**
     * ZaloPay complete
     * @return void
     * @access public
     */
    public function zalopay_complete()
    {
        $this->loadComponent('ExtZaloPay');
        $this->ExtZaloPay->_solveResult($_GET);
    }

    /**
     * ZaloPay complete
     * @return void
     * @access public
     */
    public function zalopay_notify()
    {
        $this->loadComponent('ExtZaloPay');
        $this->ExtZaloPay->_solveResult($_POST, null, true);
    }

    /**
     * Momo complete
     * @return void
     * @access public
     */
    public function momo_complete()
    {
        $this->loadComponent('ExtMomo');
        $this->ExtMomo->_solveMomoResult($_GET);
    }

    /**
     * momo notify
     * @return void
     * @access public
     */
    public function momo_notify()
    {
        $this->loadComponent('ExtMomo');

        // if(!empty($_GET['signature'])){
        //     $data = &$_GET;
        // } else {
        //     $data = &$_POST;
        // }

        $this->_solvePostData();
        $data = am($_GET, $_POST);
        // $query = http_build_query($data);

        // $this->redirect('https://syndium.vn/checkout/momo_notify?' . $query);

        $this->ExtMomo->_solveMomoResult($data, null, true);
    }

    /**
     * Thêm 1 gói vào đơn hàng
     * @param int $combo_id
     * @return void
     * @access public
     */
    public function addcombo2cart($combo_id)
    {
        // $this->_requiredAjax();
        $ret = $this->ExtCheckout->addcombo2cart($combo_id);
        $this->redirect('/checkout/order');
    }


    /**
     * Lưu tiến trình đặt hàng của khách hàng
     * @return void
     * @access public
     */
    public function saveStepOrder()
    {
        $this->_requiredAjax();
        // Configure::write('debug', true);
        // pr($currentBill);
        $ret = $this->ExtCheckout->saveStepOrder();
        $this->_responseJson(['code' => 'ok', '_id' => @$ret['billid']]);
    }

    /**
     * Khach dang giftcard
     * @return void
     * @access public
     */
    public function savegiftcard()
    {
        $data = $this->_getPostData();
        if (!empty($data)) {

            if(empty($data['payment']['method'])){
                $data['payment']['method'] = 28;
            }

            $ret = $this->ExtCheckout->saveOrderGiftCard($data);

            if (!empty($_GET['token'])) {
                $this->Session->write("onMobile", $_GET['token']);
            }

            if (!empty($ret['redirect'])) {
                $this->redirect($ret['redirect']);
                exit;
            }
        }

        // chưa lưu được đơn hàng
        $this->redirect($this->referer());
    }

    /**
     * Lưu đơn hàng
     * @return void
     * @access public
     */
    public function save()
    {

        $this->loadComponent("ExtShoppingCart");
        $orders = $this->ExtShoppingCart->_getCurrentCart();

        // $orders = $this->Session->read('cart');
        if (empty($orders)) {
            $this->Flash->error(__('Bạn chưa có sản phẩm nào trong đơn hàng.'));
            $this->redirect('/');
        }

        // if($this->Session->check("normal_step") != true){
        //     $this->Flash->error(__('Dữ liệu không hợp lệ (2)'));
        //     $this->redirect('/');
        // }
        $data = $this->_getPostData();
        if (!empty($data)) {



            // xử lý thời gian đặt hàng
            $this->ExtCheckout->_prepareSaveBill($data);




            // dùng để bỏ đi tình trạng lưu tạm của đơn hàng
            $data['Bill']['create_info']['status'] = 3;

            $ret = $this->ExtCheckout->saveOrderNew($data);

            if (!empty($_GET['token'])) {
                $this->Session->write("onMobile", $_GET['token']);
            }


            if(!empty($ret['redirect'])){
                $this->redirect($ret['redirect']);
            }
        }

        // chưa lưu được đơn hàng
        $this->redirect('/checkout/prepayment');
    }

    /**
     * Huỷ thanh toán
     * @return void
     * @access public
     */
    function cancelpayment()
    {
        // IONPAYTEST05202012041802346402
        $this->loadComponent("ExtNicePay");
        $data  = [
            'tXid' => 'IONPAYTEST05202012051309055534',
            'payMethod' => '05',
            'cancelType' => 1,
            'amt' => 1497100,
            'referenceNo' => 'Ref_5f7bf5b3df070000e3004532_866'
        ];

        $this->ExtNicePay->cancelPayment($data);
    }

    /**
     * Kiểm tra các thẻ thanh toán có hợp lệ không?
     * @return void
     * @access public
     *
     */
    public function checkGiftCard()
    {
        $this->_requiredAjax();

        if (empty($_POST['code']) || empty($_POST['source']) || empty($_POST['total'])) {
            $this->_responseJson(['code' => 'error', 'msg' => __('Dữ liệu không hợp lệ.')]);
        }

        $this->loadComponent("ExtGiftCard");
        $ret = $this->ExtGiftCard->verifyAllGiftCard($_POST);

        $this->_responseJson($ret);
    }

    /**
     * xóa 1 thẻ quà tặng trong danh sách đang sử dụng để thanh toán
     * @return void
     * @access public
     */
    public function removeGiftCardInUsed()
    {
        $this->_requiredAjax();

        if (empty($_POST['code'])) {
            $this->_responseJson(['code' => 'error', 'msg' => __('Dữ liệu không hợp lệ.')]);
        }

        $this->loadComponent("ExtGiftCard");
        $ret = $this->ExtGiftCard->removeGiftCardInUsed($_POST['code']);

        $this->_responseJson(['code' => 'ok', 'msg' => __('Đã xóa khỏi danh sách.')]);
    }

    /**
     * Check tu order_info in GET
     * @return void
     * @access public
     */
    function _checkAndGetBill()
    {
        // Khong co ma don hang
        if (empty($_GET['order_id'])) {
            $this->Flash->error(__('Có lỗi xảy ra trong quá trình thanh toán. Xin vui lòng thanh toán lại đơn hàng này. (1)'));
            $this->redirect('/');
        }

        // bill id in GET
        $billId = $_GET['order_id'];

        // load thong tin don hang
        $this->loadModel("Bill");
        $userId = _getID($this->Auth->user("_id"));
        $cond = ['customer_id' => $userId];
        $cond = []; /// TODO
        $bill = $this->Bill->getBillInfo($billId, $cond);

        // print_r($bill);
        //         pr($billId);
        //         print_r($this->params);
        //         exit;


        if (empty($bill)) {
            $this->Flash->error(__('Có lỗi xảy ra trong quá trình thanh toán. Xin vui lòng thanh toán lại đơn hàng này. (2)'));
            $this->redirect('/');
        }

        return $bill;
    }

    /**
     * Confirm payment with nicepay
     * @param int|null $billdId
     * @return void
     * @access public
     */
    function payconfirm($billId = null)
    {

        // pr($_POST);
        // exit;
        if (!empty($_POST['referenceNo'])) {
            $arr = explode("_", $_POST['referenceNo']);
            $billId = $arr[1];

            // thanh toán thành công
            $ret = $this->ExtCheckout->paidWithNicePay($billId, $_POST);
            if ($ret == false) {
                $this->Flash->error(__('Thanh toán thất bại. Xin vui lòng thử lại với phương thức thanh toán khác bên dưới.'));
                $this->redirect('/' . $this->countryInfo['slug'] . '/checkout/paymethods?order_id=' . $billId);
            }
        }

        $bill = $this->_checkAndGetBill();


        // init nice pay
        $this->loadComponent("ExtNicePay");


        $bonus = $this->_getCodeList('all', 'MON', 'BIL');
        $percent = (float)$bonus[0]['Codetbl']['value'] / 100;
        $credit = round($bill['Bill']['summary']['grand_total'] * $percent, 0);



        $methodInfo = [
            'code' => $bill['Bill']['code'],
            'money' => $bill['Bill']['summary']['grand_total'],
            'credit' => $credit,
            'name' => '',
            'info' => '',
            'template' => 'va-how-to-pay',
            'nicepay' => $bill['Bill']['payment']['nicepay'],
            'method' => [
                'name' => 'Mandiri',
                'icons' => [
                    [
                        'id' => '',
                        'img' => '/img/nicepay/bca.png',
                    ]
                ]
            ]
        ];

        if (!empty($bill['Bill']['payment']['nicepay'])) {
            if (!empty($bill['Bill']['payment']['nicepay']['bankCd'])) {
                $sub = $bill['Bill']['payment']['nicepay']['bankCd'];
            } else if (!empty($bill['Bill']['payment']['nicepay']['mitraCd'])) {
                $sub = $bill['Bill']['payment']['nicepay']['mitraCd'];
            }
            $selectedMethod = $this->ExtNicePay->getSelectedMethod($bill['Bill']['payment']['method'], $sub);

            if (!empty($selectedMethod)) {

                switch ($selectedMethod['id']) {
                    case '19':
                        $methodInfo['template'] = 'cc-how-to-pay';
                        break;
                    case '13':
                        $methodInfo['template'] = 'va-how-to-pay';
                        break;
                    case '12':
                        $methodInfo['template'] = 'va-how-to-pay';
                        break;
                    case '16':
                        $methodInfo['template'] = 'cs-how-to-pay';
                        break;
                    case '14':
                        $methodInfo['template'] = 'ew-how-to-pay';
                        break;
                    case '17':
                        $methodInfo['template'] = 'pl-how-to-pay';
                        break;
                    case '18':
                        $methodInfo['template'] = 'qr-how-to-pay';
                        break;
                }

                $methodInfo['method'] = $selectedMethod;
            }
        }

        $this->loadModel('Cms');

        // kiểm tra phương thức thanh toán
        // switch($method){
        //     case 'creditcard':

        //         // thanh toan qua the tin dung
        //     break;

        //     case 'debit_online':
        //     break;

        //     default:
        //         $methodInfo['info'] = $this->Cms->getTextById('5fc8d473ad72000019005d8d');
        //     break;
        // }


        $this->Common->setCountryInfo();
        $this->set('methodInfo', $methodInfo);
        $this->set('bill', $bill['Bill']);
        // $this->set('hideWhyXTO', true);
        $this->set("title_for_layout", __('Thanh toán đơn hàng'));
    }

    /**
     * Confirm payment with nicepay
     * @return void
     * @access public
     */
    function paynotify()
    {
        $this->loadComponent("ExtNicePay");
        $this->ExtNicePay->verifyNotify($_POST);
        exit;
    }

    /**
     * Thanh toan khi o cac quoc gia khac
     * @param string|null $method
     * @param string|null $sub
     * @return void
     * @access public
     */
    function pay($method = null, $sub = null)
    {

        $bill = $this->_checkAndGetBill();

        $this->loadComponent("ExtNicePay");
        $data = $this->_getPostData();
        if (!empty($data)) {

            if (!empty($data['Bill']['payment']['method'])) {
                $arr = explode("_", $data['Bill']['payment']['method']);

                $bill['Bill']['payment']['method'] = (int)$arr[0];
                $bill['Bill']['payment']['submethod'] = $arr[1];
            }

            // if($bill['Bill']['payment']['method'] == 19){

            //     $ret = $this->ExtNicePay->paymentCreditCard($bill['Bill'], $_POST);
            // } else {

            $ret = $this->ExtNicePay->payment($bill['Bill']);
            // }
        }

        $bonus = $this->_getCodeList('all', 'MON', 'BIL');
        $percent = (float)$bonus[0]['Codetbl']['value'] / 100;
        $credit = round($bill['Bill']['summary']['grand_total'] * $percent, 0);

        $method = $bill['Bill']['payment']['method'];
        $sub = $bill['Bill']['payment']['submethod'];

        $methodInfo = [
            'code' => $bill['Bill']['code'],
            'money' => $bill['Bill']['summary']['grand_total'],
            'credit' => $credit,
            'image'     => '/img/nicepay/mandiri.png',
            'certify_image' => '/img/nicepay/security-guarantee.png',
            'name' => 'Mardivi Virtual Account',
            'info' => '',
            'template' => 'pay-virtual-account'
        ];

        $this->loadModel('Cms');

        // kiểm tra phương thức thanh toán
        switch ($method) {
            case 19:

                // nếu chưa có khởi tạo nicepay thì khởi tạo và thanh toán
                if (@($bill['Bill']['payment']['nicepay']['payMethod'] != '01')) {
                    $this->ExtNicePay->payment($bill['Bill']);
                }

                if ($sub == 'creditcard') {

                    // thanh toan qua the tin dung
                    $methodInfo['template'] = 'pay-credit-card';
                    $methodInfo['name'] = __('Thẻ tín dụng');
                    $methodInfo['icons'] = [
                        [
                            'id' => '',
                            'img' => '/img/nicepay/visa.png',
                            'name' => 'Visa'
                        ],
                        [
                            'id' => '',
                            'img' => '/img/nicepay/master.png',
                            'name' => 'Master Card'
                        ],
                        [
                            'id' => '',
                            'img' => '/img/nicepay/american-express.png',
                            'name' => 'American Express'
                        ],
                        [
                            'id' => '',
                            'img' => '/img/nicepay/jcb.png',
                            'name' => 'JCB'
                        ]
                    ];


                    $timeStamp = date('YmdHis', xtostrtotime("+7 hours"));
                    $this->set('timeStamp', $timeStamp);

                    $merchantToken = $this->ExtNicePay->merchantToken($bill, $timeStamp);
                    $this->set('merchantToken', $merchantToken);

                    $lang = $this->Session->read("language2");
                    $NICEPAY_CALLBACK_URL = URL_BASE . '/' . $lang . '/' . $this->countryInfo['slug'] . '/checkout/payconfirm';
                    $this->set('NICEPAY_CALLBACK_URL', $NICEPAY_CALLBACK_URL);
                } else {
                    // thẻ ghi nợ online
                    $methodInfo['template'] = 'pay-debit-card';
                }

                break;


            default:

                $mm = $this->ExtNicePay->getSelectedMethod($method, $sub);
                if (!empty($mm)) {
                    $methodInfo['image'] = $mm['items']['icons'][0]['img'];
                    $methodInfo['name'] = $mm['items']['name'];
                }

                $methodInfo['info'] = $this->Cms->getTextById('5fc8d473ad72000019005d8d');
                break;
        }

        $this->_updatePostDataToView(null, $data);

        $this->Common->setCountryInfo();

        $this->set('methodInfo', $methodInfo);
        $this->set('method', $method);
        $this->set('bill', $bill['Bill']);
        // $this->set('hideWhyXTO', true);
        $this->set("title_for_layout", __('Thanh toán đơn hàng'));
    }

    /**
     * Chọn phương thức thanh toán khác
     * @return void
     * @access public
     */
    function paymethods()
    {

        $bill = $this->_checkAndGetBill();


        $this->loadComponent("ExtNicePay");
        $data = $this->_getPostData();
        if (!empty($data)) {
            $arr = explode("_", $data['Bill']['payment']['method']);

            $bill['Bill']['payment']['method'] =  (int)$arr[0];
            $bill['Bill']['payment']['submethod'] = $arr[1];

            $update = [
                '_id' => _getID($bill['Bill']['_id']),
                'payment' => $bill['Bill']['payment']
            ];

            $this->Bill->saveWithKeys($update);

            $ret = $this->ExtNicePay->payment($bill['Bill']);
        }


        // if($this->countryInfo['_id'] == 'ID'){

        $payments = $this->ExtNicePay->getPaymentMethods();
        // } else {
        //     $payments = $this->Codetbl->getAll('PAY', 'TPE');
        // }

        $bonus = $this->_getCodeList('all', 'MON', 'BIL');
        $percent = (float)$bonus[0]['Codetbl']['value'] / 100;
        $credit = round($bill['Bill']['summary']['grand_total'] * $percent, 0);

        $this->_updatePostDataToView(null, $data);

        $methodInfo = [
            'code' => $bill['Bill']['code'],
            'money' => $bill['Bill']['summary']['grand_total'],
            'credit' => $credit,
            'image'     => '/img/nicepay/mandiri.png',
            'certify_image' => '/img/nicepay/security-guarantee.png',
            'name' => 'Mardivi Virtual Account',
            'info' => '',
            'template' => 'virtual_account'
        ];

        $this->set("methodInfo", $methodInfo);
        $this->set("payments", $payments);
        $this->set("bill", $bill['Bill']);
        $this->Common->setCountryInfo();
        $this->set("title_for_layout", __('Chọn phương thức thanh toán'));
    }

    /**
     * VnPayIPN
     * @return void
     * @access public
     */
    public function VnPayIPN()
    {
        $this->ExtCheckout->VNPayReturn(true);
    }

    /**
     * VNPay Return
     * @return void
     * @access public
     */
    public function vnpay_return()
    {
        $this->ExtCheckout->VNPayReturn();
    }

    /**
     * trang nhận kết quả thanh toán từ các đơn vị khác
     * @param string|null $method
     * @return void
     * @access public
     */
    public function paymentGateway($method = null)
    {
        $this->_solvePostData();
        $data = am($_GET, $_POST);

        if (empty($method) || empty($data)) {
            // $this->_responseJson(['code' => 'ok', 'msg' => __('Đang chờ thanh toán từ %s', $data['merchant_code'])]);
            $this->_responseJson(['code' => 'error', 'msg' => __('Dữ liệu không hợp lệ. (1)')]);
        }

        switch (strtolower($method)) {
            case 'payme':
                $data = &$_POST;
                if (empty($data['partnerTransaction'])) {
                    $this->_responseJson(['code' => 'error', 'msg' => __('Dữ liệu không hợp lệ. (2)')]);
                }

                $this->loadComponent('ExtPayMe');

                $arr = explode("_", $data['partnerTransaction']);

                $bill = $this->_getRawInfo($arr[0], "bills");
                $ret = $this->ExtPayMe->_solveResult($data, $bill, true, true);
                break;
        }

        $this->_responseJson(['code' => 'error', 'msg' => __('Dữ liệu không hợp lệ. (3)')]);
    }


    /**
     * nhạn ket qua tu apotapay
     * @param string $action
     * @return void
     * @access public
     */
    public function apotapay($action)
    {
        $this->_solvePostData();

        $this->loadComponent("ExtApotaPay");

        switch ($action) {
            case 'ipn':
                $this->ExtApotaPay->ipn($_POST);
                break;
            case 'ipnwebview':
                $this->ExtApotaPay->ipnwebview($_POST);
                break;
            case 'complete':
                $this->ExtApotaPay->complete($_GET);
                break;
            default:
                echo __('Dữ liệu không hợp lệ.');
                exit;
        }

        exit;
    }

    /**
     * nhạn ket qua tu smartpay
     * @param string $action
     * @return void
     * @access public
     */
    public function smartpay($action)
    {
        $this->_solvePostData();

        $this->loadComponent("ExtSmartPay");

        switch ($action) {
            case 'ipn':
                $this->ExtSmartPay->ipn($_POST);
                break;
            case 'ipnwebview':
                $this->ExtSmartPay->ipnwebview($_POST);
                break;
            case 'complete':
                $this->ExtSmartPay->complete($_POST);
                break;
            default:
                echo __('Dữ liệu không hợp lệ.');
                break;
        }

        exit;
    }


    /**
     * Thanh toán
     * @param string $bill_id
     * @return void
     * @access public
     */
    public function payment($bill_id = '')
    {
        if (!empty($bill_id)) {
            $this->Session->write('paying', $bill_id);
        } else {
            $bill_id = $this->Session->read('paying');
        }

        $sskey = "checkBillOK" . (string)$bill_id;
        $this->Session->write($sskey, true);


        // lấy thông tin đơn hàng
        $bill = $this->Bill->getBillInfo($bill_id, ['create_info.status' => ['$ne' => 9]]);

        if (empty($bill)) {
            $this->Flash->error(__('Rất tiếc! Chúng tôi không tìm thấy đơn hàng của bạn để tiếp tục thanh toán. Xin vui lòng kiểm tra lại.'));

            $this->redirect('/');
        }

        $this->ExtCheckout->payment($bill);
    }

    /**
     * Thanh toán bằng QR
     * @param string $bill_id
     * @return void
     * @access public
     */
    public function qrpayment($bill_id = '')
    {
        if (!empty($bill_id)) {
            $this->Session->write('paying', $bill_id);
        } else {
            $bill_id = $this->Session->read('paying');
        }

        // lấy thông tin đơn hàng
        $bill = $this->ExtBill->getBillInfo($bill_id, false, true, '', []);

        if (empty($bill)) {
            $this->Flash->error(__('Rất tiếc! Chúng tôi không tìm thấy đơn hàng của bạn để tiếp tục thanh toán. Xin vui lòng kiểm tra lại.'));

            $this->redirect('/users/info');
        }

        $this->layout = 'home';
        $this->set('bill', $bill['Bill']);
    }



    /**
     * Paypal Payment Complete
     * @return void
     * @access public
     */
    public function ppcomplete()
    {
        $this->_requiredAjax();

        // $_POST['_id'] = '61b44c239b1b0000ef004b12';
        // $_POST['method'] = '9';
        // $_POST['ref'] = '570c96987f8b9a2c0f3776b8';

        if (!empty($_POST)) {

            $cond = [
                '_id' => _getID($_POST['_id']),
                // 'customer_id' => _getID($_POST['ref']),
                //'payment.method' => (int)$_POST['method'],
                // 'summary.remain' => ['$gt' => 0]
            ];

            $this->loadModel("Bill");
            $bill = $this->Bill->find('first', ['conditions' => $cond])->first();


            if (empty($bill)) {
                $this->_responseJson(['code' => 'error', 'msg' => 'Đơn hàng không tồn tại trong hệ thống']);
            } else {


                $paymentData = [
                    'bill_id' => $bill['Bill']['_id'],
                    'money' => $bill['Bill']['summary']['remain'], // lấy số tiền còn lại cần thanh toán
                    'method' => 9, //
                    'note' => __('Khách hàng thanh toán qua Paypal'),
                    'name' => __('Khách hàng'),
                    'payment_info' => $_POST['info']
                ];

                $this->loadComponent("ExtBill");
                $ret = $this->ExtBill->addPayment($paymentData);

                // $update = array(
                //     '_id' => _getID($bill['Bill']['_id']),
                //     'create_info.status' => 4,
                //     'payment.date' => new \MongoDate(),
                //     'payment.method' => 9,
                //     'payment.payment_info' => $info
                // );

                // $update['summary.just_pay'] = $bill['Bill']['summary']['grand_total'];
                // $update['summary.remain'] = 0;

                // $bill['Bill']['summary']['just_pay'] = $update['summary.just_pay'];

                // // $error = false;
                // $ret = $this->Bill->saveWithKeys($update);

                // if ($this->Bill->saveWithKeys($update)) {
                //     $error = true;
                // }

                // if (!$error) {
                // cap nhat lai doanh thu cho shop
                // $this->ExtCheckout->updateRevenues($bill);

                $this->Session->delete('paying');
                // $this->Session->delete('order');
                $this->Session->delete('bill');

                if ($this->Session->check('pay_from_app')) {
                    $this->redirect('/mobiles/complete/ok');
                } else {
                    $this->_responseJson(['code' => 'ok', '_id' => @(string)$bill['Bill']['_id']]);
                }
                // } else {
                //     $this->_responseJson(['code' => 'error', 'msg' => 'Có lỗi xảy ra trong quá trình thanh toán. Xin vui lòng thử lại.']);
                // }
            }
        }

        $this->_responseJson(['code' => 'error', 'msg' => 'Có lỗi xảy ra trong quá trình thực hiện. Xin vui lòng thử lại.']);
    }

    /**
     * Đặt hàng thành công
     * @param string $bill_id
     * @return void
     * @access public
     */
    public function complete($bill_id = '')
    {
        $complete_screen = $this->Session->read('complete_screen');



        // $this->loadComponent("ExtShoppingCart");
        // $orders = $this->ExtShoppingCart->_getCurrentCart();

        // // $orders = $this->Session->read('cart');

        // $selected = $this->ExtCheckout->getSelectedProductFromOrder($orders);

        $bill = $this->Bill->find('first', ['fields' => ['_id', 'code', 'order', 'customer_id', 'payment', 'summary', 'create_info'], 'conditions' => ['_id' => _getID($bill_id)]])->first();

        $product_id = @$bill['Bill']['order'][0]['product_id'];


        // xóa những sản phẩm đã mua ra khỏi giỏ hàng
        $this->Common->runShell("background removeCartItem vietnam", $bill['Bill']['customer_id']);

        $createBill = $this->Session->read('createBill');
        if (empty($createBill)) {
            $this->ExtProduct->getRelatedProducts($product_id);
        } else {
            if (!empty($createBill['psid'])) {
                $this->loadComponent('ExtChatbotTool');
                $this->ExtChatbotTool->sendMsg($createBill['psid'], 'Quý khách đã đặt hàng thành công. Mã đơn hàng là #' . $bill['Bill']['code']);
            }

            $this->set('showInChatBot', true);
        }

        $link = $this->Common->createBillStateLink($bill['Bill']['_id']);

        $this->loadModel("User");
        $customer = $this->User->getSingleUserInfo($bill['Bill']['customer_id'], ['info.email']);

        $this->Common->setCountryInfo();

        // $remainProducts = $this->ExtCheckout->getSelectedProductFromOrder($orders, false);

        // $this->ExtShoppingCart->update($remainProducts);

        $this->Session->delete('complete_screen');
        // $this->Session->write('cart', $remainProducts);
        $this->Session->delete('checkout');
        // $this->Session->delete('cart');
        $this->Session->delete('createBill');
        $this->Session->delete('saveOrderNew');
        $this->Session->delete('chkFirstOrder');
        $this->Session->delete('splitOrders');



        $this->set('title_for_layout', __('Đặt hàng thành công!'));
        $this->set('complete_screen', $complete_screen);
        $this->set('bill', $bill);
        $this->set('customer', $customer['User']);


        $this->set('linkState', $link);

        if (!empty($_GET['token'])) {
            $token = $_GET['token'];
        } else {
            $token = $this->Session->read("onMobile");
        }


        if (!empty($token)) {
            $this->layout = 'app';
            $_GET['token'] = $token;

            $this->_responseApp([
                'action' => 'order_complete',
                'bill' => $bill['Bill'],
                'class' => 'ok',
                'title' => __('Lưu đơn hàng thành công!'),
                'message' => __("Cám ơn Quý khách đã đặt hàng với Syndium! Mã đơn hàng: {0}. Đơn hàng của bạn đã được lưu lại, chúng tôi sẽ thông báo đến bạn khi có bất kỳ thay đổi nào về tình trạng đơn hàng.", $bill['Bill']['code'])
            ], false);
        } else {
            $this->layout = 'home';
            $this->set('disableAlertMessage', true);
            $this->set('bottomFixed', true);
        }
    }

    /**
     * Đặt hàng thất bại
     * @param string $value
     * @return void
     * @access public
     */
    public function fail($value = '')
    {
        if (!empty($_GET['token'])) {
            $token = $_GET['token'];
        } else {
            $token = $this->Session->read("onMobile");
        }


        if (!empty($token)) {
            $_GET['token'] = $token;

            $this->_responseApp([
                'action' => 'order_fail',
                'class' => 'error',
                'title' => __('Lưu đơn hàng thất bại!'),
                'message' => __("Đặt hàng thất bại. Xin vui lòng thử lại lần nữa.")
            ], false);
        } else {
            $this->set('title_for_layout', __('Đặt hàng thất bại'));
            $this->layout = 'home';
        }
    }

    /**
     * Checkout
     */
    // public function onestep($id = null)
    // {
    //     // Lấy cart từ session
    //     $order = $this->Session->read('order');
    //     if(empty($order)){
    //         $this->Flash->error(__('Bạn chưa có sản phẩm trong giỏ hàng'));
    //         $this->redirect('/');
    //     }

    //     $this->loadModel('Language', 'Model');
    //     $languages  = $this->Language->getAllLanguage();

    //     // get payment methods
    //     $this->loadModel('Codetbl');
    //     $payments = $this->Codetbl->getAll('PAY', 'TPE');

    //     // loại cái trả tiền mặt
    //     unset($payments[0]);


    //     // lấy thông tin sản phẩm
    //     $product =  $this->ExtProduct->getDetail($order['Bill']['product_id']);

    //     // lấy chương trình giảm giá cho ngày lễ
    //     $Holiday = MyApp::uses('Holiday', 'Model');
    //     $holiday = $Holiday->getActivedItem();

    //     // Kiểm tra chương trình giảm giá cho ngày lễ
    //     $this->ExtShopHoliday->_prepareShopHoliday($holiday, $product['Product']['shop']);

    //     // kiểm tra xem có đặt hàng cho ngày lễ không?
    //     $this->ExtShopHoliday->applyHoliday($holiday['Holiday'], $order['Bill'], $product['Product']);

    //     // lấy phí giao hàng
    //     if(!empty($order['Bill']['to_user']['district_id'])){
    //         $order['Bill']['get_at_shop'] = 0;
    //         $order['Bill']['shop_id'] = $product['Product']['shop_id'];
    //         $order['Bill']['summary']['subtotal'] = $product['Product']['prices']['regular_show'];
    //         $order['Bill']['summary']['ship_fee'] = $this->ExtCheckout->getShipFee($order['Bill']);
    //     } else {
    //         $order['Bill']['summary']['ship_fee'] = 0;
    //     }

    //     $productJson = [
    //         '_id' => $product['Product']['_id'],
    //         'name' => $product['Product']['name'],
    //         'image' => @$product['Product']['image']
    //     ];

    //     // build payment list
    //     $paymentsJson =  [];
    //     foreach ($payments as $key => $value) {
    //         $paymentsJson[$value['Codetbl']['value']] = __($value['Codetbl']['name']);
    //     }

    //     $country = $this->countryInfo['_id'];
    //     $province = null;
    //     $shop = $product['Product']['shop_id'];
    //     $this->loadModel('Country');
    //     $this->loadModel('Province');

    //     $countries = $this->Country->getlist();

    //     $current_city = $this->Session->read('current_city');
    //     $this->loadModel('District');
    //     $p = $this->Province->getProvinceBySlug($current_city);

    //     // trường hợp lọc theo khu vực miễn phí giao hàng
    //     $current_district = $this->Session->read('current_district');
    //     if(!empty($current_district)){
    //         $district_ids = [$current_district];
    //     } else {
    //         // lấy hết những khu vực hỗ trợ giao hàng
    //         $district_ids = $this->Shop->getAllowListDistrict($product['Product']['shop_id']);
    //     }

    //     $districts = $this->District->getListDistrictByIds($district_ids);

    //     $provinces = [$p['Province']['_id'] =>  $p['Province']['name']];

    //     //ghi 1 record vào BillLog
    //     // $this->ExtBillLog->saveNew(__('Tiến hành đặt hàng "%s"', $product['Product']['name']));
    //     $this->ExtBillLog->saveNew(['action' => 'order_step_1', 'p' =>  $product['Product']]);


    //     // kiểm tra xem nếu người này login và có đặt hàng rồi hay chưa?
    //     $user_id = $this->Auth->user('_id');
    //     $firstOrder = false;
    //     $validPhone = false;
    //     if(VKT_ENABLED){
    //         if(!empty($user_id)){
    //             // kiểm tra đơn hàng
    //             $firstOrder = $this->ExtBill->isFirstOrder($user_id, $product['Product']['shop_id']);

    //             if($firstOrder){
    //                 $this->Session->write('chkFirstOrder', true);
    //             } else{
    //                 $this->Session->delete('chkFirstOrder');
    //             }

    //             // kiểm tra xem người đang login này đã xác thực số đt hay chưa?
    //             $validPhone = $this->Auth->user('info.phone_verify');
    //         }
    //     }




    //     if(!empty($shopHoliday)){
    //         $product['Product']['shop']['discount'] = 0;
    //     }

    //     // $firstOrder = true;
    //     $this->set('firstOrder', $firstOrder);
    //     $this->set('validPhone', $validPhone);

    //     $this->set('countries', $countries);
    //     $this->set('provinces', $provinces);
    //     $this->set('districts', $districts);
    //     $this->set('productJson', json_encode($productJson));
    //     $this->set('product', $product['Product']);
    //     $this->set('languages', $languages);
    //     $this->set('payments', $payments);
    //     $this->set('paymentsJson', json_encode($paymentsJson));

    //     $this->set('order', $order);
    //     $this->set('title_for_layout', __('Đặt hàng'));
    //     $this->layout = 'home';
    // }

    /**
     * Xem lại đơn hàng và chọn phương thức thanh toán
     * @return void
     * @access public
     */
    public function order()
    {


        $this->loadComponent("ExtShoppingCart");
        $orders = $this->ExtShoppingCart->_getCurrentCart();

        // $orders = $this->Session->read('cart');
        if (empty($orders)) {
            $this->Flash->error(__('Bạn chưa có sản phẩm nào trong đơn hàng.'));
            $this->redirect('/');
        } else {
            $orders = array_values($orders);
            $this->ExtShoppingCart->update($orders);
            // $this->Session->write('cart', $orders);
        }
        $data = $this->_getPostData();
        if (!empty($data)) {
            $customer_id = _getID($this->Auth->user('_id'));
            $data['Bill']['customer_id'] = $customer_id;

            $ret = $this->ExtBill->save($data['Bill']);
            $this->redirect($ret['redirect']);
        }

        $this->loadModel('Codetbl');
        $payments = $this->Codetbl->getAll('PAY', 'TPE');

        // loại cái trả tiền mặt
        unset($payments[0]);


        // if(!empty($orders[0]['is_combo'])){
        //     $payments[3]['Codetbl']['desc'] = 'Thanh toán theo hợp đồng';
        //     $payments = [3 => $payments[3]];
        // }

        $this->loadModel('Province');
        $this->loadModel('District');
        // lấy thông tin tỉnh thành
        $provinces = $this->Province->getListProvinces();

        $keys = array_keys($provinces);
        $districts = $this->District->getList($keys[0]);

        $this->loadModel("UserAddress");
        $address_list = $this->UserAddress->getAddressOfUser($this->Auth->user('_id'));

        $this->set('payments', $payments);
        $this->set('address_list', $address_list);
        $this->set('provinces', $provinces);
        $this->set('districts', $districts);
        $this->set('title_for_layout', __('Thông tin giao hàng'));
        $this->layout = 'order';
    }


    /**
     * Thông tin người mua hàng
     * @return void
     * @access public
     */
    public function customer()
    {
        $user_id = $this->Auth->user('_id');
        if (!empty($user_id)) {
            $this->redirect(['action' => 'delivery']);
        }
        $data = $this->_getPostData();
        if (!empty($data)) {
            $this->Session->write('checkout.customer', $data);
            $this->redirect(['action' => 'delivery']);
        } else {
            $data = $this->Session->read('checkout.customer');
        }

        $this->set('step', 1);
        $this->set('title_for_layout', 'Thông tin người mua hàng');
        $this->layout = 'order';
    }

    /**
     * Kiểm tra mã thanh toán
     * @return void
     * @access public
     */
    public function checkOtherPaymentCode()
    {
        $this->checkGiftCard();
    }

    /**
     * Nhập người nhận quà
     * @return void
     * @access public
     */
    public function inpreceiver()
    {
        $this->loadComponent('ExtBill');
        $currentBill = $this->ExtBill->getCurrentBill();

        if(!empty($_GET['rid'])){
            $currentBill['to_user']['relative_id'] = $_GET['rid'];
        }

        if (empty($currentBill)) {
            $this->Flash->error(__('Không tìm thấy thông tin đặt hàng. Xin vui lòng thử lại.'));
            $this->redirect('/checkout/cart');
        }

        $curDistrict = $this->_getRawInfo(@$currentBill['to_user']['location']['district_id'], 'countries', ['_id', 'name']);
        $curProvince = $this->_getRawInfo(@$currentBill['to_user']['location']['province_id'], 'countries', ['_id', 'name']);
        $data = $this->_getPostData();

        // prx($data);

        if (!empty($data)) {

            if(isset($data['_csrfToken'])){
                unset($data['_csrfToken']);
            }

            //-----------------------------------------------------
            // Nếu chọn người đã lưu
            //-----------------------------------------------------
            if(!empty($data['Bill']['to_user']['relative_id'])){
                // lấy dữ liệu từ hệ thống ra
                $relative = $this->ExtUser->getRelativeInfo($currentBill['to_user']['relative_id']);

                // lưu vào bill
                $data['Bill']['to_user']['full_name'] = $relative['person']['full_name'];
                $data['Bill']['to_user']['phone'] = $relative['person']['phone_number'];
                $data['Bill']['to_user']['phone_code'] = $relative['person']['phone_code'];

                // địa chỉ
                if(!empty($data['Bill']['to_user']['location']['old_id'])){
                    foreach($relative['person']['locations'] as $loc){
                        if($loc['_id'] == $data['Bill']['to_user']['location']['old_id']){
                            if(isset($loc['province'])){
                                unset($loc['province']);
                            }
                            if(isset($loc['district'])){
                                unset($loc['district']);
                            }
                            if(isset($loc['country'])){
                                unset($loc['country']);
                            }
                            $data['Bill']['to_user']['location'] = $loc;
                            break;
                        }
                    }
                }
            } else {
                // dữ liệu nhập thủ công
                $fullAddress = join(', ', [$data['Bill']['to_user']['location']['address'], $curDistrict['name'], $curProvince['name'], 'Việt Nam']);

                // Lấy tọa độ từ địa chỉ
                if (!empty($data['Bill']['to_user']['location']['w3w'])) {
                    $this->loadComponent("ExtW3W");
                    $coords = $this->ExtW3W->W3W_2_Coordinates($data['Bill']['to_user']['location']['w3w']);

                    if (!empty($coords['lng'])) {
                        $destLngLat = [
                            $coords['lng'],
                            $coords['lat']
                        ];
                    } else {
                        $data['Bill']['to_user']['location']['w3w'] = null;
                    }
                } else {
                    // $this->loadComponent("ExtAwsGrabMap");
                    // $destLngLat = $this->ExtAwsGrabMap->getLatLngFromAddress($fullAddress);
                }


                if (!empty($destLngLat)) {
                    $data['Bill']['to_user']['location']['pin']  = ['lat' => $destLngLat[1], 'lon' => $destLngLat[0]];
                } else {
                    $data['Bill']['to_user']['location']['pin']  = null;
                }

                // chỉ lưu khi không phải là người đang login
                if(($_GET['show'] ?? "other") != "me"){
                    // nếu chưa có relative_id thì tạo luôn 1 user mới
                    $newRelative = [
                        'relationship_id' => $data['Bill']['to_user']['relationship_id'],
                        'occasion_id' => $data['Bill']['to_user']['occasion_id'],
                        'params' => [
                            'date' => $currentBill['to_user']['date_origin'],
                        ],
                        'person' => [
                            'full_name' => $data['Bill']['to_user']['full_name'],
                            'phone_number' => $data['Bill']['to_user']['phone'],
                            'phone_code' => $data['Bill']['to_user']['phone_code'],
                            'phones' => [
                                [
                                    "label" => 'work',
                                    "code" =>  $data['Bill']['to_user']['phone_code'],
                                    "number" =>  $data['Bill']['to_user']['phone'],
                                ]
                            ],
                            'locations' => [$data['Bill']['to_user']['location']]
                        ]
                    ];

                    $ret = $this->ExtUser->addRelative($newRelative, null);

                    $data['Bill']['to_user']['relative_id'] = $ret['_id'];
                    if (!empty($ret['eventid'])) {
                        $data['Bill']['to_user']['relative_occasion_id'] = $ret['eventid'];
                    }
                }
            }

            // kiểm tra dữ liệu nhập vào
            // $this->loadModel('User');
            // $data['Bill']['to_user']['phone_number'] = $data['Bill']['to_user']['phone'];
            // $this->User->data = ['info' => $data['Bill']['to_user']];

            // $errors = $this->User->invalidFields();

            // if(!empty($errors) && isset($errors['info.phone_number']) && isset($errors['info.phone_number']['_checkPhoneData'])){
            //     if ($this->_isAjaxRequest()) {
            //         $this->_responseJson(['code' => 'error', 'msg' => $errors['info.phone_number']['_checkPhoneData']]);
            //     }
            // }

            $currentBill = array_replace_recursive($currentBill, $data['Bill']);


            // không chọn nghệ sĩ nào
            // if (empty($currentBill['to_user']['artist_id'])) {
               /// TODO
            // } else {
            //     // có chọn nghệ sĩ
            //     $artist = $this->_getRawInfo($currentBill['to_user']['artist_id'], 'artists', [
            //         'location.province_id',
            //         'location.district_id',
            //         'location.pin',
            //         'name',
            //         'phone',
            //     ]);

            //     //
            //     $artist['location']['address'] = __('(đang xử lý)');
            //     $artist['phone_code'] = '+84';
            //     $artist['full_name'] = $artist['name'];

            //     if (empty($artist['phone'])) {
            //         $artist['phone'] = '035-680-5699';
            //     }

            //     unset($artist['name']);
            //     unset($artist['_id']);

            //     $artist['relationship_id'] = '';
            //     $artist['relative_id'] = '';
            //     $artist['occasion_id'] = '';

            //     $currentBill['to_user'] = array_replace_recursive($currentBill['to_user'], $artist);

            //     $currentBill['summary']['money_plus'] = 'on';
            // }

            $this->ExtBill->updateCurrentBill($currentBill);

            if ($this->_isAjaxRequest()) {
                $this->_responseJson(['code' => 'ok']);
            }


            $this->redirect('/checkout/prepayment');
        } else {
            if (!empty($_GET['remove-artist'])) {
                $currentBill['to_user']['artist_id'] = null;
            }
            if (!empty($_GET['remove-relative'])) {
                $currentBill['to_user']['relative_id'] = null;
            }

            // cập nhật dữ liệu xóa
            $this->ExtBill->updateCurrentBill($currentBill);

            if(!empty($currentBill['to_user']['relative_id'])){
                $relative = $this->ExtUser->getRelativeInfo($currentBill['to_user']['relative_id']);
                // $relative = $this->_getRawInfo($currentBill['to_user']['relative_id'], 'user_relationships', ['_id', 'relationship_id', 'person.avatar', 'person.full_name', 'person.phones', 'person.locations']);
                $this->set("relative", $relative);
            }
        }

        if (!empty($currentBill['to_user']['artist_id'])) {
            $this->loadComponent('ExtArtist');
            $artist = $this->ExtArtist->getDetail($currentBill['to_user']['artist_id']);



            $this->set('artist', $artist);
            $this->set('groups', $this->_getCodeList('list', 'ART', 'GRP'));
        } else {
            if (empty($currentBill['to_user']['relationship_id'])) {
                $quickOrder = $this->Session->read('quickOrder');
                if (!empty($quickOrder['step1'])) {
                    $currentBill['to_user']['relationship_id'] = $quickOrder['step1']['_id'];
                }
            }

            if (empty($currentBill['to_user']['occasion_id'])) {
                $quickOrder = $this->Session->read('quickOrder');
                if (!empty($quickOrder['step3'])) {
                    $currentBill['to_user']['occasion_id'] = $quickOrder['step3']['_id'];
                }
            }
        }

        // xem chế độ người nhận
        $show =  @$_GET['show'] == "me" ? "me" : "other";


        if($show == "me"){
            $user = $this->Auth->user();

            // hiển thị thông tin người đang đăng nhập
            $currentBill['to_user']['relative_id'] = null;
            $currentBill['to_user']['full_name'] = $user['info']['full_name'];
            $currentBill['to_user']['phone'] = $user['info']['phone_number'];
            $currentBill['to_user']['phone_code'] = $user['info']['phone_code'];
        }


        // $this->_updatePostDataToView(null, $data);

        $this->set("show", $show);

        $this->loadModel('Country');
        $this->set('codes', $this->Country->getPhoneCodeList());


        $this->loadComponent("ExtRelationship");
        $relationOptions = $this->ExtRelationship->getHtmlOptions();
        $this->set('relationOptions', $relationOptions);

        $this->loadComponent("ExtOccasion");
        $occasionOptions = $this->ExtOccasion->getHtmlOptions();
        $this->set('occasionOptions', $occasionOptions);


        $this->_updatePostDataToView("Bill", $currentBill);


        $this->set('curDistrict', $curDistrict);
        $this->set('curProvince', $curProvince);

        $this->set('title_for_layout', __('Nhập thông tin người nhận'));
        $this->set('desc_for_layout', __('Nhập thông tin người nhận'));
    }

    /**
     * Xác nhận các thay đổi tùy chọn khi đặt hàng
     */
    function confirmChanged() {
        $data = $this->_getPostData();
        if(!empty($data)){


            $this->loadComponent('ExtBill');
            $currentBill = $this->ExtBill->getCurrentBill();

            switch($data['action']){
                case 'receiver':
                    //-----------------------------------------------------
                    // Nếu chọn người đã lưu
                    //-----------------------------------------------------
                    if(!empty($data['Bill']['to_user']['relative_id'])){

                        $currentBill = array_replace_recursive($currentBill, $data['Bill']);

                        // lấy dữ liệu từ hệ thống ra
                        $relative = $this->ExtUser->getRelativeInfo($currentBill['to_user']['relative_id']);

                        // lưu vào bill
                        $currentBill['to_user']['full_name'] = $relative['person']['full_name'];
                        $currentBill['to_user']['phone'] = $relative['person']['phone_number'];
                        $currentBill['to_user']['phone_code'] = $relative['person']['phone_code'];

                        // địa chỉ
                        if(!empty($currentBill['to_user']['location']['old_id'])){
                            foreach($relative['person']['locations'] as $loc){
                                if($loc['_id'] == $currentBill['to_user']['location']['old_id']){
                                    if(isset($loc['province'])){
                                        unset($loc['province']);
                                    }
                                    if(isset($loc['district'])){
                                        unset($loc['district']);
                                    }
                                    if(isset($loc['country'])){
                                        unset($loc['country']);
                                    }
                                    $currentBill['to_user']['location'] = $loc;
                                    break;
                                }
                            }
                        }


                    }
                break;
                case 'paymentMethod':
                    // thay đổi phương thức thanh toán
                    $currentBill['payment'] = ['method' => $data['Bill']['payment']['method'] ];
                break;
                case 'vat':

                    if(!empty($data['vat_info'])){
                        $currentBill['vat'] = json_decode($data['vat_info'], true);
                        $currentBill['hasvat'] = true;
                    }

                    break;

                default:
                // ko có phù hợp action nào
                $this->redirect('/checkout/prepayment');
            }
        }

        $this->ExtBill->updateCurrentBill($currentBill);

        $this->redirect('/checkout/prepayment');
    }

    /**
     * Hiển thị giỏ hàng
     * @return void
     * @access public
     */
    public function cart()
    {
        $this->loadComponent("ExtShoppingCart");
        $this->loadComponent('ExtBill');

        if(!empty($_GET['rm-all'])){
            // xóa hết các sản phẩm trong giỏ hàng
            $userId = $this->Auth->user("_id");
            $this->ExtShoppingCart->removeSelectedItems($userId);

            $this->redirect('/checkout/cart');
        }

        $this->loadComponent("ExtUser");
        $this->ExtUser->_autoLoginFromLink('/checkout/cart');

        // xóa phần tính phí đã lưu tạm
        $this->Session->delete("shippingFee");


        $orders = $this->ExtShoppingCart->_getCurrentCart();

        // $orders = $this->Session->read('cart');

        $discount = null;

        // // lấy giá giảm
        // $from = xtostrtotime(date('Y-m-d 12:00:00'));
        // $to = xtostrtotime(date('Y-m-d 13:00:00'));

        // // $to = xtostrtotime(date('Y-m-d 23:59:00'));

        // $isDiscountHour = ($from <= time() && time() <= $to) && $orders[0]['code'] !== 'D6634';

        // if($isDiscountHour && empty($data['Bill']['summary']['discount']['promote_code'])){
        //     $data['Bill']['summary']['discount']['promote_code'] = 'GIOVANG-1214';
        // }

        // lấy danh sách cate_ids
        $cate_ids = [];
        $type_ids = [];

        $subtotal = 0;

        if (!empty($orders)) {


            foreach ($orders as $key => $value) {
                $cate_ids = am($value['cate_ids'], $cate_ids);
                $type_ids[] = $value['design'];
                $subtotal += ($value['price'] * abs((int)$value['num']));
            }

            $current_city = $this->Session->read('current_city');
            if (empty($current_city)) {
                $this->Common->setCurrentCity($value['current_city']);
            }

            $current_district = $this->Session->read('current_district_obj');
            if (empty($current_district)) {
                $this->Common->setCurrentDistrict($value['district_id']);
            }
        }

        $this->loadModel("Codetbl");


        $isDiscountHour = false;



        // kiểm tra xem category có nằm trong danh sách campaign đang chạy không?
        // $this->loadComponent("ExtCampaign");
        // $campaignActive = $this->ExtCampaign->getActiveCampaign($cate_ids);



        // if(!empty($this->request->data) || $campaignActive){
        $discount = null;

        $data = $this->_getPostData();

        if (!empty($data)) {

            // Kiểm tra mã giảm giá
            if (!empty($data['Bill']['summary']['discount']['promote_code'])) {


                $code = trim((string)$data['Bill']['summary']['discount']['promote_code']);
                $code = preg_replace('/  */', '', $code);
                $data['Bill']['summary']['discount']['promote_code'] = $code;


                // mã giảm giá từ XTO
                if (@$data['Bill']['summary']['discount']['source'] == 'xto') {
                    $shop_id = $this->Auth->user('shop_id');
                    $discount = $this->ExtCheckout->chkDiscount($shop_id, $code, $cate_ids, $type_ids);

                    // $subtotal = 0;
                    // if(!empty($orders)){
                    //     foreach ($orders as $key => $value) {
                    //         if(!empty($value['price_discount'])){
                    //             $subtotal += @($value['price_discount'] * $value['num']);
                    //         } else {
                    //             $subtotal += @($value['price'] * $value['num']);
                    //         }

                    //     }
                    // }

                    if (!empty($discount)) {
                        // if($discount['apply_for'] == 1) {
                        if (!empty($discount['percent'])) {
                            $discountMoney = round(($discount['percent'] * $subtotal) / 100, -3);
                            if (empty($discount['money'])) {
                                $discount['money'] = $discountMoney;
                            } else {
                                $discount['money'] = min($discountMoney, $discount['money']);
                            }
                        }
                        // }


                        $applyFor = $this->_getCodeList('list', 'DIS', 'FOR');
                        $discount['apply_for_text'] = $applyFor[$discount['apply_for']];
                    } else {
                        $discount = null;
                        $this->Flash->error(__('Mã giảm giá không hợp lệ. Xin quý khách vui lòng kiểm tra lại mã giảm giá!'));
                    }
                } else {
                    $this->loadComponent("ExtGiftNetwork");
                    $ret = $this->ExtGiftNetwork->verifyVoucher([(string)$code]);

                    if ($ret['code'] == 'ok') {
                        // mã này hợp lý
                        // $this->set("GNCInfo", $ret['info']);

                        if (!empty($ret['info']['totalFaceValue'])) {
                            $faceValue = $ret['info']['totalFaceValue'];
                        } else {
                            $faceValue = $ret['info']['vouchers'][0]['faceValue'];
                        }

                        $discount = [
                            'money' => $faceValue,
                            'apply_for' => 1,
                            'code' => (string)$code,
                            'bill_money' => 0,
                            'apply_for_text' => __('Áp dụng cho giá trị hoa')
                        ];
                    } else {
                        $discount = null;
                        $this->Flash->error(__('Mã giảm giá không hợp lệ. Xin vui lòng thử lại'));
                    }
                }
            }
            // else if(!empty($campaignActive)){


            //     list($discount['percent'], $_ship) = $this->ExtCampaign->getPercentDiscount(time(), $campaignActive);
            //     $discountMoney = round( ($discount['percent'] * $subtotal) / 100, -3);
            //     if(empty($discount['money'])){
            //         $discount['money'] = $discountMoney;
            //     } else {
            //         $discount['money'] = min($discountMoney, $discount['money']);
            //     }
            // }
            else {
            }

            //-----------------------------------------------------
            // Danh dau nhung san pham duoc chon thanh toan
            //-----------------------------------------------------
            foreach ($orders as $idx => $od) {
                $orders[$idx]['checked'] = false;
            }

            // đánh dấu lại những sản phẩm được chọn
            foreach ($data['Bill']['product_id'] as $pid) {
                foreach ($orders as $idx => $od) {
                    if ($od['product_id'] == $pid) {
                        $orders[$idx]['checked'] = true;
                    }
                }
            }

            // prx($orders, true);
            // prx($this->request->data, true);

            // $this->Session->write('cart', $orders);
            $this->ExtShoppingCart->update($orders);
            unset($data['Bill']['product_id']);

            $this->Session->write('useDiscount', $discount);

            if (@$data['action'] == 'redirect') {

                $this->redirect('/checkout/prepayment');

                // nếu đây là khách hàng đặt hàng lần đầu thì cho input người nhận hàng vào (không cần tạo mối quan hệ)

                $userId = $this->Auth->user('_id');
                $first = $this->ExtBill->isFirstOrder($userId, null);

                // $first = true; /// TODO
                if ($first || true) {
                    // đến trang nhập thông tin người nhận hàng
                    $nextPage = '/checkout/inpreceiver';
                } else {
                    // đến trang chọn người nhận hàng
                    $nextPage = '/checkout/receiver';
                }

                $this->Session->write('checkout.cart', $data);

                $this->redirect($nextPage);
                exit;
            } else {
            }
        } else {
            $data = $this->Session->read('checkout.cart');
            if (empty($data) && !empty($orders)) {
                $this->loadModel('Shop');
                $checkCate = array_intersect($this->Shop->cateDisabledDiscount, $cate_ids);

                if (empty($checkCate)) {

                    $hour = xto2usertime(time());

                    // tu dong ap dung gio vang
                    if (12 <= $hour && $hour <= 14) {
                        $data['Bill']['summary']['discount']['promote_code'] = "GIOVANG";
                    } else {
                        $masterDiscount = $this->Codetbl->getSimpleFields('MST', 'DIS');
                        if (!empty($masterDiscount)) {
                            $keys = array_keys($orders);
                            $item = $orders[$keys[0]];
                            $discount = $this->ExtCheckout->chkDiscount(null, $masterDiscount['name'], $item['cate_ids'], $item['design'], [], $subtotal);

                            if (!empty($discount)) {

                                if (!empty($discount['percent'])) {
                                    $discountMoney = round(($discount['percent'] * $subtotal) / 100, -3);
                                    if (empty($discount['money'])) {
                                        $discount['money'] = $discountMoney;
                                    } else {
                                        $discount['money'] = min($discountMoney, $discount['money']);
                                    }
                                }

                                $applyFor = $this->_getCodeList('list', 'DIS', 'FOR');
                                $discount['apply_for_text'] = $applyFor[$discount['apply_for']];

                                $data['Bill']['summary']['discount']['promote_code'] = $masterDiscount['name'];
                            } else {
                                $data['Bill']['summary']['discount']['promote_code'] = '';
                            }

                            $this->Session->write('useDiscount', $discount);
                        }
                    }
                }
            } else if (empty($data['Bill']['summary']['discount']['promote_code'])) {
                $discount = null;
                $this->Session->write('useDiscount', null);
            } else {
                $discount = $this->Session->read('useDiscount');
            }

            if (!empty($data['Bill']['summary']['discount']['promote_code']) && $data['Bill']['summary']['discount']['promote_code'] == 'CHALLENGE_PRIZE') {
                $data['Bill']['summary']['discount']['promote_code'] = '';
            }
        }


        // $this->set('campaignActive', $campaignActive);


        $current_city = $this->Session->read("current_city");


        $curBill = $this->ExtBill->getCurrentBill();

        $pitem = !empty($orders) ? $orders[array_keys($orders)[0]] : null;



        if (empty($curBill)) {

            if (empty($pitem['hour'])) {

                if (!empty($pitem['time_range']['from'])) {
                    $ff = explode(':', $pitem['time_range']['from']);
                    $tt = explode(':', $pitem['time_range']['to']);
                    $pitem['hour'] = (int)$ff[0] . '-' . (int)$tt[0];
                } else {
                    $pitem['hour'] = '8-12';
                }
            }

            $hour = $pitem['hour'];

            $arr = explode('-', $hour);

            $timeRange = [
                'from' => str_pad($arr[0], 2, '0', STR_PAD_LEFT) . ':00',
                'to' => str_pad($arr[1], 2, '0', STR_PAD_LEFT) . ':00',
            ];

            $datetime = null;
            if(isset($pitem['datetime'])){
                $datetime  = is_object($pitem['datetime']) ? $pitem['datetime']->sec : $pitem['datetime'] ?? null;
            }

            if (isset($datetime) && !is_numeric($datetime)) {
                $datetime = xto2usertime(strtotime($pitem['datetime']), 7);
            }

            // trường hợp chưa có thông tin đặt hàng
            $curBill = [

                'tracking' => $pitem['tracking'] ?? [],
                'to_user' => [
                    'time_range' => $timeRange,
                    'date' => $datetime,
                    'date_origin' => date('d-m-Y', $datetime),
                    'hour' => $hour,
                    'relationship_id' => @$pitem['relationship_id'],
                    'occasion_id' =>  @$pitem['occasion_id'],
                    'location' => [
                        'province_id' => $pitem['province_id'] ?? "",
                        'district_id' => $pitem['district_id'] ?? "",
                    ]
                ]
            ];

            // update current province & district
            $this->Common->setCurrentCity($pitem['current_city'] ?? "hcm");
            $this->Common->setCurrentDistrict($pitem['district_id'] ?? "");
        }

        if (empty($curBill['to_user']['location']['province_id'])) {

            // if(empty($pitem['province_id'])){
            //     $this->Flash->error(__('Dữ liệu đơn hàng không hợp lệ. Vui lòng thêm lại sản phẩm'));

            //     if(!empty($pitem['product_id'])){
            //         $this->redirect('/detail/' . $pitem['product_id'] . '.html');
            //     } else {
            //         $this->redirect('/');
            //     }

            // }

            $this->Common->setCurrentCity($pitem['current_city'] ?? "hcm");
            $this->Common->setCurrentDistrict($pitem['district_id'] ?? "");
            $curBill['to_user']['location']['province_id'] = @$pitem['province_id'];
            $curBill['to_user']['location']['district_id'] = @$pitem['district_id'];

        }

        $this->ExtBill->updateCurrentBill($curBill);

        // prx($curBill);
        if (!empty($orders)){
            $this->ExtShoppingCart->checkAvailable($orders);
        }

        $this->loadModel('Shop');
        $checkCate = array_intersect($this->Shop->cateDisabledDiscount, $cate_ids);
        $this->set('allowDiscount', empty($checkCate));

        // reset price = 0 for qt0dong
        // $orders[0]['qt0dong'] = true;
        if (!empty($orders[0]['qt0dong'])) {
            $orders[0]['price'] = $orders[0]['price_origin'] = 0;
            $discount = null;
        }

        if ($this->isMobile) {
            $this->set('hideHeader', true);
        }

        $this->_updatePostDataToView(null, $data);

        $this->set('discount', $discount);
        $this->set('quickOrder', $this->Session->read('quickOrder'));
        $this->set('orders', $orders);
        $this->set('cartPage', true);
        $this->set('hideSubscription', true);
        $this->set('show_select_province', false);
        $this->set('bottomFixed', true);
        $this->Common->setCountryInfo();
        $userName = $this->Auth->user('info.full_name');
        $this->set('title_for_layout', __('Giỏ hàng') . ' [' . $userName . ']');
        $this->set('desc_for_layout', __('Giỏ hàng của bạn'));
        $this->layout = 'home';

        if ($this->isMobile || $this->webview) {
            $this->render("cart_mobile");
        }
    }

    /**
     * Load dữ liệu lên từ bill lưu tạm và chuẩn bị cho Prepayment
     * @param int|null $id
     * @param string $code
     * @return void
     * @access public
     */
    function prepareBill($id = null, $code = '-1')
    {
        if (empty($id)) {
            $this->Flash->error(__("Dữ liệu không hợp lệ. Xin vui lòng thử lại sau."));
            $this->redirect('/');
        }

        $check = md5('xto_temp' . $id);

        if ($code != $check) {
            $this->Flash->error(__("Dữ liệu không hợp lệ. Xin vui lòng thử lại sau."));
            $this->redirect('/');
        }

        $this->loadModel("Bill");
        $this->loadComponent('ExtBill');

        $this->Session->write("normal_step", true);

        // chỉ lấy cho đơn hàng lưu tạm. Các đơn khác không xử lý
        // $bill = $this->Bill->getBillInfo($id, ['create_info.status' => 2]);
        $bill = $this->Bill->getBillInfo($id);


        if (empty($bill)) {
            $this->Flash->error(__("Dữ liệu không hợp lệ. Xin vui lòng thử lại sau."));
            $this->redirect('/');
        }


        // tạo lại dữ liệu mới

        $this->ExtBill->updateCurrentBill($bill['Bill']);

        $bill = &$bill['Bill'];

        // Lấy thông tin khách hàng và đăng nhập
        $this->loadModel("User");
        $user = $this->User->getSingleUserInfo($bill['customer_id'], ['_id', 'info', 'username', 'create_info', 'code_refer']);
        if (empty($user)) {
            $this->Flash->error(__("Dữ liệu không hợp lệ. Xin vui lòng thử lại sau."));
            $this->redirect('/');
        }

        $this->Auth->login($user['User']);

        // chuẩn bị dữ liệu
        $this->loadComponent("ExtShoppingCart");
        $this->ExtShoppingCart->update($bill['order']);
        // $this->Session->write('cart', $bill['order']);

        //current_city
        $this->Common->setCurrentCity($bill['to_user']['location']['province_id']);

        // $bill['hide_sender'] = true;

        $prepayment = ['Bill' => $bill];
        $checkout = [
            'prepayment' => $prepayment,
            'cart' => [
                'Bill' => ['summary' => ['discount' => ['promote_code'  => $bill['summary']['discount']['promote_code']]]]
            ]
        ];


        $this->Session->write('checkout', $checkout);

        $this->redirect("/checkout/prepayment");
    }

    /**
     * Thanh toán đơn hàng
     * @return void
     * @access public
     */
    public function prepayment()
    {


        $this->loadComponent('ExtBill');

        $this->Session->delete("sendOTPOrder_count");

        /// TODO
        // $this->Session->delete("giftcards");

        // if($this->Session->check("normal_step") != true){
        //     $this->Flash->error(__('Dữ liệu không hợp lệ (2)'));
        //     $this->redirect('/');
        // }
        $currentBill = $this->ExtBill->getCurrentBill();

        if(empty($currentBill) && empty($_POST)){
            $this->Flash->error(__('Đơn hàng không hợp lệ! Xin vui lòng chọn lại quà tặng!'));
            $this->redirect('/checkout/cart');
        }

        if (!empty($_GET['no-discount'])) {

            //-----------------------------------------------------
            // Xóa mã giảm giá khỏi đơn hàng
            //-----------------------------------------------------

            $code = $currentBill['discount_freecode'];

            foreach ($currentBill['summary']['other_discounts'] as $idx => $gc) {
                if ($gc['gift_code'] == $code) {
                    unset($currentBill['summary']['other_discounts'][$idx]);
                }
            }

            $currentBill['discount_freecode'] = '';
            $currentBill['summary']['discount']['promote_code'] = '';
            $currentBill['summary']['discount']['money_ship_code'] = '';
            $currentBill['summary']['discount']['source'] = '';

            $currentBill['summary']['other_discounts'] = array_values($currentBill['summary']['other_discounts']);

            //-----------------------------------------------------
            // Update
            //-----------------------------------------------------
            $update = [
                'discount_freecode' => $currentBill['discount_freecode'],
                'summary' => $currentBill['summary']
            ];

            $this->ExtBill->UpdateCurrentBill($update);

            $this->_responseJson(['code' => 'ok']);
        }

        $this->loadModel("Discount");
        $this->loadComponent("ExtShoppingCart");
        $orders = $this->ExtShoppingCart->_getCurrentCart();
        // $orders = $this->Session->read('cart');

        if (empty($orders)) {

            // kiểm tra xem có đơn hàng lưu tạm nào không?
            $userId = $this->Auth->user("_id");
            if (!empty($userId)) {
                $this->loadModel("Bill");
                $bill = $this->Bill->getTempBillOfUser($userId, ['_id']);
                if (!empty($bill)) {
                    $link = '/checkout/prepareBill/' . $bill['Bill']['_id'] . '/' . md5('xto_temp' . $bill['Bill']['_id']);
                    $this->redirect($link);
                }
            }

            $this->Flash->error(__('Bạn chưa có sản phẩm nào trong đơn hàng.'));
            $this->redirect('/');
        } else {
            // $orders = array_values($orders);
            // $this->Session->write('cart', $orders);
        }

        $orders = array_values($orders);

        // app dung ma moi
        if (!empty($_GET['new-code'])) {
            $discount = $this->Discount->getADiscount(null, trim((string)$_GET['new-code']));
            if (!empty($discount)) {

                $found = false;
                // xóa mã giảm giá mặc định của XTO đi
                foreach ($orders as $idx => $order) {
                    if (!empty($order['discount_source']) && $order['discount_source'] == '1') {
                        $order['price'] = $order['price_origin'];
                        $order['has_discount'] = false;
                        unset($order['apply_for']);
                        unset($order['discount_source']);
                        unset($order['discount_code']);

                        $found = true;
                        $orders[$idx] = $order;
                    }
                }

                if ($found) {
                    // $this->Session->write('cart', $orders);
                    $this->ExtShoppingCart->update($orders);
                }

                $this->Session->write("checkout.cart.Bill.summary.discount.promote_code", $discount['code']);
                $this->Session->write("checkout.cart.Bill.summary.discount.source", $discount['provider']);

                // $this->redirect('/checkout/prepayment');
                $this->_responseJson(['code' => 'ok']);
            }
        }


        $checkout = $this->Session->read('checkout');



        // $orders = [['code' => 1]]; /// TODO


        $shop_id = _getID($orders[0]['shop_id']);


        $curProvince = $this->Session->read('current_city_obj');
        // $curProvince = $this->_getRawInfo($current_city_obj['_id'], 'countries', ['_id', 'slug', 'name']);

        // $current_district = $this->Session->read('current_district');
        // $curDistrict = $this->District->getById($current_district, ['_id', 'name']);

        if ((string)$shop_id == $this->shop_id_default) {

            $this->loadComponent("ExtShop");
            $shop_id = $this->ExtShop->getXTOPartnerShopId($curProvince['_id']);
            if (empty($shop_id)) {
                $shop_id = $this->shop_id_default;
            }
        }

        $shop = $this->Shop->getAShop($shop_id, null, ['location.province_id']);


        $userId = $this->Auth->user('_id');

        if (!empty($currentBill['_id'])) {
            $billId = $currentBill['_id'];
            $link = '/checkout/prepareBill/' . $billId . '/' . md5('xto_temp' . $billId);
        };

        $user_id = $this->Auth->user("_id");
        if (empty($user_id)) {
            $this->loadComponent('ExtProvince');

            // lấy thông tin tỉnh thành (để khách hàng chọn cho phần tạo tài khoản)
            $provinces = $this->ExtProvince->loadProvincesAllowList($this->countryInfo['_id'], ['_id', 'name']);
            $this->set('provinces', $provinces);
        }


        // $address_list = [];


        // $district = $this->Session->read("current_district_obj");

        // if(empty($district)){
            $district = $this->_getRawInfo($currentBill['to_user']['location']['district_id'], 'countries', ['_id', 'name']);
            if(!empty($district)){
                $district['slug'] = _SEO($district['name']);
                $this->Session->write("current_district_obj", $district);
            }

        // }


        // if(!empty($user_id)){
        //     $this->loadModel("UserAddress");
        //     $address_list = $this->UserAddress->getAddressOfUser($user_id, $curProvince['Province']['_id'], $district['_id']);
        // }

        // bill log
        $this->loadComponent("ExtBillLog");
        $this->ExtBillLog->saveNew(['action' => 'preview_order']);



        $addressTypes = $this->_getCodeList('list', 'ADR', 'TPE');
        $this->loadModel("Codetbl");
        $dlvTime = $this->Codetbl->getSimpleFields('DLV', 'TIM');

        $this->set("deliveryTime", $dlvTime['value']);
        $this->set('addressTypes', $addressTypes);


        $this->loadModel('Codetbl');


        // $this->loadModel("Shop");
        // $shop = $this->Shop->getAShop($orders[0]['shop_id']);
        $shop = $this->_getRawInfo($orders[0]['shop_id'], 'shops', ['_id', 'name', 'avatar', 'slug', 'location', 'phone']);
        $shop = ['Shop' => $shop];

        $upstair = $this->Codetbl->getNameOfCode('DLI', 'UPS', 1);
        $vatPercent = $this->Codetbl->getNameOfCode('VAT', 'PER', 1);

        // $allowPriority = $this->Codetbl->getNameOfCode('ALO', 'PRI', 1);

        $allowPriority = !empty($this->masterInfo['allow_quickly']);

        $space = 4;
        $this->set('upstair', $upstair);
        $this->set('space', $space);

        $this->set("allowPriority", $allowPriority);

        // $this->set('timePrice', $timePrice);
        // $this->set('onCampaign', $onCampaign);

        //-----------------------------------------------------
        // kiem tra xem có bat buoc phai verify khach hang truoc khi dat hang khong?
        //-----------------------------------------------------
        $countryCode = $this->Session->read("cus_from_country");
        if (empty($countryCode)) {
            $ip = $this->Common->_getUserIp();
            $countryCode = $this->Common->_getUserLocationInfo($ip);

            $this->Session->write("cus_from_country", $countryCode);
        }

        $outsideVN = !empty($countryCode) && strtoupper($countryCode) != 'VN';

        if ($this->webview || $outsideVN) {
            $verifyotp = 0;
        } else {
            // nếu là webview thì không cần verify
            $verifyotp = $this->Codetbl->getNameOfCode('CUS', 'VER', 1);
            $verifyotp = empty($verifyotp) ? 0 : 1;
        }

        $this->set('verifyotp', $verifyotp);

        $orders = $this->ExtCheckout->getSelectedProductFromOrder($orders);

        if (!empty($currentBill['to_user']['relative_id']) && empty($currentBill['to_user']['full_name'])) {
            $this->loadComponent("ExtUser");
            $relative = $this->ExtUser->getRelativeInfo($currentBill['to_user']['relative_id']);

            if(!empty($relative)){
                $currentBill['to_user']['full_name'] = $relative['person']['full_name'];
                $currentBill['to_user']['phone'] = $relative['person']['phone_number'];
                $currentBill['to_user']['phone_code'] = $relative['person']['phone_code'];
            }
        }


        // pr($splitOrders);
        // exit;

        $this->set('checkout', $checkout);


        $this->loadModel('Country');
        $this->set('phoneCodes', $this->Country->getPhoneCodeList());

        $this->set('shop', $shop);


        // $this->set('districtsCount', $districtsCount = 10);



        $this->set('curProvince', $curProvince);
        $this->set('curDistrict', $district);
        // $this->set('currentCate', $cate['Category']);


        // $this->set('payments', $payments);

        $this->set('vatPercent', $vatPercent);
        $this->set('cart', $orders);
        $this->set('disableAlertMessage', true);
        $this->set('hideSubscription', true);
        // $this->set('hideWhyXTO', true);
        $this->set('bottomFixed', true);
        $this->Common->setCountryInfo();



        $params = $this->ExtCheckout->_prepareOrderSummary($orders, false, $currentBill);
        // prx($params);
        $this->set($params);

        $this->set('quickOrder', @$_SESSION["quickOrder"]);

        /// TODO


        $eventId = @$currentBill['to_user']['relative_occasion_id'];
        if (!empty($eventId)) {
            $currentBill['event'] = $this->ExtUser->getEventInfo($eventId, $userId);
        }



        if (!empty($userId)) {

            $user = $this->Auth->user();

            if (empty($data['Bill']['Customer'])) {
                $currentBill['Customer'] = $user;
            }
            $this->set("title_for_layout", __("Đặt hàng: {0}", $user["info"]["full_name"] . '-' . $user["info"]["phone_number"]));
        } else {
            $this->set("title_for_layout", __("Đặt hàng: {0}", __('Khách mới')));
        }

        // $data['Bill']['to_user']['location']['district_id'] = null;
        // $data['Bill']['summary']['ship_fee'] = 0; // tinh phi ship
        $currentBill['payment']['method'] = 0; // reset = chưa chọn
        $currentBill['customer_id'] = (string)$this->Auth->user("_id");

        if(empty($currentBill['summary']['grand_total'])){
            $currentBill['order'] = $orders;
            $currentBill['shop_id'] = $orders[0]['shop_id'];

            $this->Bill->init($currentBill);
            $this->ExtCheckout->calculateSummaryAgain($currentBill);
        }

        list($allowCreditOptions, $wallet) = $this->ExtCheckout->getAllowCreditOptions($currentBill);
        $this->set('allowCreditOptions', $allowCreditOptions);
        $this->set('wallet', $wallet);

        $this->checkResponseType();

        $this->_updatePostDataToView('', ['Bill' => $currentBill]);
    }


    /**
     * Lấy phí giao hàng
     * @param int|null $district_id
     * @param int|null $province_id
     * @return void
     * @access public
     */
    public function shipfee($district_id = null, $province_id = null)
    {



        $money = 0;
        if(!empty($_GET['t'])){
            $money = (float)$_GET['t'];
        }

        if(!empty($_GET['partner_id']) && $_GET['partner_id'] != 'seller'){
            $shippingFee = $this->Session->read("shippingFee");

            $selected = null;
            if(!empty($shippingFee)){
                foreach($shippingFee as $vendor){

                    if($vendor['code'] == $_GET['partner_id']){
                        $selected = $vendor;
                        break;
                    }
                }
            }

            $selected = null; /// TODO

            if(empty($selected)){
                $this->loadComponent('ExtShippingVendor');
                $selected = $this->ExtShippingVendor->getShipFeeOfVendorById($_GET['partner_id']);
            }

            if(!empty($selected)){

                foreach($selected['ship_fees'] as $shopId => $shipfee){
                    $_order = [
                        'shop' => $this->_getRawInfo($shopId, 'shops', ['location.district_id'])
                    ];

                    $this->ExtCheckout->_checkFreeShip($selected['ship_fees'][$shopId], $money, $_order);
                }

                $this->_responseJson(['code' => 'ok',
                    'info' => [
                        'ship_fees' => $selected['ship_fees'],
                        'code'  => 'ok',
                        'same_ship' => 0,
                        'msg' => __('Phí giao hàng sẽ được cửa hàng thông báo sau khi lên đơn.')
                    ],
                    'msg' => __('Phí giao hàng sẽ được cửa hàng thông báo sau khi lên đơn.')]);
            }
        }

        $this->loadComponent("ExtShoppingCart");
        $cart = $this->ExtShoppingCart->_getCurrentCart();
        if(empty($cart)){
            $this->_responseJson(['code' => 'error', 'msg' => 'Không tìm thấy đơn hàng.']);
        }

        if(!empty($this->masterInfo) && $this->masterInfo['cate_type'] == 2){
            // nhu yếu phẩm thì phí ship sẽ tự thương lượng với người bán
            $this->_responseJson(['code' => 'ok',
            'info' => [
                'ship_fee' => 0,
                'code'  => 'ok',
                'same_ship' => 0,
                'msg' => __('Phí giao hàng sẽ được cửa hàng thông báo sau khi lên đơn.')
            ],
            'msg' => __('Phí giao hàng sẽ được cửa hàng thông báo sau khi lên đơn.')]);
        }


        $date = null;
        if(!empty($_GET['to_date'])){
            $date = $this->Common->date_convert($_GET['to_date']);
            $date = xtostrtotime($date);
        }

        $shops = [];
        if(!empty($_GET['shops'])){
            $shops = explode(',', $_GET['shops']);
        }

        $ship_fee = $this->ExtCheckout->_getShipFee($cart, $district_id, $province_id, $date, $money, 1, $shops);

        $this->_responseJson(['code' => $ship_fee['code'], 'msg' => $ship_fee['msg'], 'info' => $ship_fee]);
    }

    /**
     * Xác thực thanh toán qừ Shopee Pay
     * @return void
     * @access public
     */
    public function shopeePayNTS()
    {

        $data = file_get_contents("php://input");

        if (strpos($data, 'form-data') !== false) {
            $this->_getFileData($data);
        } else {
            $this->_getDataJson($data);
        }

        $this->loadComponent("ExtShopeePay");

        if (!empty($_POST)) {
            $data = $_POST;
            $ret = $this->ExtShopeePay->notifyTransactionStatus($data);
        } else {
            if (!empty($_SERVER['HTTP_REFERER'])) {
                $referer = $_SERVER['HTTP_REFERER'];
                // https://pay.uat.airpay.vn/h5pay/pay?type=start&app_id=11000119&key=Nawf1633a73bd030584f&order_id=BLU00006&return_url=aHR0cHM6Ly94aW5odHVvaS5vbmxpbmUvY2hlY2tvdXQvc2hvcGVlUGF5TlRT&source=web
                $result = null;
                parse_str($referer, $result);

                if (!empty($result['order_id'])) {
                    $billstate = $this->Common->createBillStateLink($result['order_id']);

                    $billstate = $billstate . '?payment=1';

                    // chuyển đến trang tình trạng đơn hàng
                    $this->redirect($billstate);
                }
            }

            $ret = ['code' => 0, 'msg' => "Order have not exists"];
        }

        $this->_responseJson($ret);
    }

    /**
     * Kiem tra tinh trang don hang thanh toan qua ShopeePay
     * @return void
     * @access public
     */
    public function shopeePayVerify()
    {
        $this->_requiredAjax();

        if (empty($_POST)) {
            $this->_responseJson(['code' => 'error', 'msg' => __('Dữ liệu không hợp lệ (1)')]);
        }

        $this->loadModel("Bill");

        $bill = $this->Bill->getBillInfo($_POST['data']['bill_id'], [], ['summary.grand_total', 'code']);

        if (!empty($bill)) {
            $billstate = $this->Common->createBillStateLink($bill['Bill']['_id']);

            $ret = [
                'code' => 'ok',
                'url' => $billstate
            ];
        } else {
            $ret = ['code' => 'error', 'msg' => __('Dữ liệu không hợp lệ (2)')];
        }

        $this->_responseJson($ret);
    }

    /**
     * Khách chọn thanh toán qua shopeePay
     * @param int $billId
     * @return void
     * @access public
     */
    public function shopeePay($billId)
    {
        $this->loadModel("Bill");


        $bill = $this->Bill->getBillInfo($billId, [], ['summary.grand_total', 'payment', '_id', 'code']);

        // $paymentMoney = $this->Session->read('paymentMoney');

        $this->loadComponent("ExtCheckout");

        $this->ExtCheckout->_getBillGroupSummary($bill);

        $this->loadComponent("ExtShopeePay");
        $data = [
            'bill_id' => $bill['Bill']['_id'],
            'code' => $bill['Bill']['code'],
            'amount' => $bill['Bill']['summary']['grand_total'],
        ];

        if ($this->isMobile) {
            $data['platform'] = 'mweb';
            $ret = $this->ExtShopeePay->createOrder($data);

            if (!empty($ret['redirect_url_http'])) {

                // chuyển đến app ShopeePay để thanh toán
                $this->redirect($ret['redirect_url_http']);
            }
        } else {
            $data['platform'] = 'pc';
            $ret = $this->ExtShopeePay->createQRCode($data);
        }


        // $ret = [
        //     'qr_url' => 'https://api.uat.airpay.vn/merchanttools/qrcode?code=MDAwMjAxMDEwMjEyMjY2MjAwMTN2bi5haXJwYXkud3d3MDE0MTAxMjEwODAyMTYyMDA2MDk0Njc5NTJJSUVNYndOMXlmNGJ4bkNxRWFtNTIwNDU4MTI1MzAzNzA0NTQwNjMwMDAwMDU4MDJWTjU5MDdYVE9fd2ViNjMwNDREMTk='
        // ];

        $billstate = $this->Common->createBillStateLink($bill['Bill']['_id']);

        $bill['Bill']['billstate'] = $billstate . '?payment=1';

        if (!empty($ret['qr_url'])) {
            $this->Common->setCountryInfo();
            $this->set('ret', $ret);
            $this->set('data', $data);
            $this->set('bill', $bill['Bill']);
            // $this->set("hideWhyXTO", true);

            $this->loadComponent('ExtGoogleFirestore');
            $ref_id = $this->ExtGoogleFirestore->createShopeePayNewRecord($bill['Bill']['_id']);


            $update = ['_id' => _getID($bill['Bill']['_id']), 'payment.google_refId' => $ref_id];
            $this->Bill->saveWithKeys($update);

            $this->set("refId", $ref_id);
        } else {

            $this->redirect($billstate);
        }
    }

    /**
     * Truy van thong tin da thanh toan
     * @param int|null $billID
     * @return void
     * @access public
     */
    function chkqrpayment($billID = null)
    {
        $this->_requiredAjax();

        $bill = $this->Bill->find('first', ['conditions' => ['_id' => _getID($billID), 'create_info.status' => ['$in' => [4, 5]]], 'fields' => ['_id', 'create_info', 'payment']])->first();

        if (!empty($bill)) {
            $this->_responseJson(['code' => 'ok', 'payment' => $bill['Bill']['payment']]);
        } else {
            $this->_responseJson(['code' => 'error', 'msg' => __('Đơn hàng chưa thanh toán')]);
        }
    }

    /**
     * Kiểm tra đơn hàng đầu tiên
     * @return void
     * @access public
     */
    public function chkFirstOrder()
    {

        $this->_requiredAjax();
        $data = $this->_getPostData();
        if (!VKT_ENABLED) {
            $this->_responseJson(['code' => 'fail', 'msg' => '']);
        }
        if (empty($data)) {
            $this->_responseJson(['code' => 'error', 'msg' => '']);
        }

        $data = &$data;
        $user = $this->User->find("first", [
            'conditions' => [
                '$or' => [
                    ['info.phone_number' => $data['User']['info']['phone_number']],
                    ['info.email' => $data['User']['info']['email']],
                ]
            ]
        ])->first();

        // kiểm tra đơn hàng đầu tiên theo số điện thoại
        $firstOrder = false;
        $verified = false;

        $ret = ['code' => 'fail'];



        if (!empty($user)) {

            $this->loadComponent("ExtShoppingCart");
            $cart = $this->ExtShoppingCart->_getCurrentCart();

            // $cart = $this->Session->read('cart');

            // kiểm tra đơn hàng
            $firstOrder = $this->ExtBill->isFirstOrder($user['User']['_id'], $cart['Bill']['shop_id']);

            $verified  = $user['User']['info']['phone_verify'];

            if (!$firstOrder) {
                $ret = ['code' => 'fail-1'];
            } else {
                $ret = ['code' => 'ok', 'verified' => $verified, 'p' => 'WCVCH', 'user_id' => (string)$user['User']['_id']];
            }
        } else {

            // tạo tài khoản của người này
            $this->Common->get_common_info($data['User']);
            $user = $this->ExtUser->saveAdmin($data['User']);

            // không tạo được người dùng
            if (empty($user)) {
                $firstOrder = false;
                $ret = ['code' => 'fail-2'];
            } else {
                $firstOrder = true;
                $ret = ['code' => 'ok', 'verified' => $verified, 'p' => 'WCVCH', 'user_id' => (string)$user['_id']];
            }
        }

        // $firstOrder = true;
        // $ret = ['code' => 'ok', 'verified' => true, 'p' => 'WCVCH', 'user_id' => 123];

        if ($firstOrder) {
            $this->Session->write('chkFirstOrder', true);
            $this->_responseJson($ret);
        } else {
            $this->Session->delete('chkFirstOrder');
            $this->_responseJson($ret);
        }
    }

    /**
     * Reset giỏ hàng
     * @return void
     * @access public
     */
    public function reset()
    {
        $this->Session->delete('cart');
        $this->redirect('/');
    }

    /**
     * Lưu thông tin đơn hàng thành công
     * @return void
     * @access public
     */
    public function success()
    {
    }

    /**
     * Kiểm tra mã giảm giá
     * @return void
     * @access public
     */
    public function chkDiscount()
    {
        $this->_requiredAjax();

        $data = $this->_getPostData();

        if (!empty($data)) {

            $data = &$data;

            $wrong_counter = $this->Session->read('discount.wrong_counter');

            if (empty($wrong_counter)) {
                $wrong_counter = ['num' => 0, 'time' => time()];
            }

            if ($wrong_counter['num'] > 3) {
                if (time() - $wrong_counter['time'] > 30 * 60) {
                    $this->Session->delete('discount.wrong_counter');
                }


                echo __('Có lỗi nghiêm trọng xảy ra. Xin báo vui lòng báo lỗi này cho Ban Quản Trị để kịp thời khắc phục!');
                exit;
            }

            $data['code'] = trim((string)$data['code']);

            $shop_id = @trim((string)$data['shop_id']);
            if (empty($shop_id)) {
                $shop_id = $this->Auth->user('shop_id');
            }


            // trường hợp người dùng thực hiện đơn hàng
            if (empty($data['subtotal'])) {
                $this->loadComponent("ExtShoppingCart");
                $orders = $this->ExtShoppingCart->_getCurrentCart();

                // $orders = $this->Session->read('cart');
                $orders = array_values($orders);
                $cate_ids = $orders[0]['cate_ids'];
                $types = [$orders[0]['design']];

                $channels = ['web', 'app'];


                $subtotal = 0;
                foreach ($orders as $ord) {
                    $subtotal += ($ord['price'] * abs($ord['num']));
                }
            } else {
                // trường hợp admin
                $subtotal = $data['subtotal'];
                $cate_ids = [];
                $types = [];
                $channels = ['web', 'app'];
            }


            $discount = $this->ExtCheckout->chkDiscount($shop_id, $data['code'], $cate_ids, $types, $channels, $subtotal);

            if (empty($discount)) {

                // count số lần sai
                $wrong_counter['num'] = (int)$wrong_counter['num'] + 1;
                $this->Session->write('discount.wrong_counter', $wrong_counter);

                $this->_responseJson(['code' => 'error', 'msg' => __('Mã giảm giá không hợp lệ (hoặc đã hết số lượng giảm giá trong ngày). Xin vui lòng kiểm tra lại.')]);
            } else {
                // nếu đúng thì bỏ giảm giá mặc định của hệ thống đi
                if (!empty($_GET['save'])) {
                    $this->Session->write("checkout.cart.Bill.summary.discount.promote_code", $discount['code']);
                    $this->Session->write("checkout.cart.Bill.summary.discount.source", $discount['provider']);
                }
            }

            $discount['type'] = $discount['money']  > 0 ? 1 : 2;
            $this->Session->delete('discount.wrong_counter');
            $this->_responseJson(['code' => 'ok', 'discount' => $discount]);
        }

        $this->_responseJson(['code' => 'error', 'msg' => __('Dữ liệu không hợp lệ.')]);
    }

    /**
     * gửi code xác thực điện thoại
     */
    public function codeVerifyPhone()
    {
        $this->_requiredAjax();

        if (!empty($_POST['phone'])) {
            $phone = trim((string)$_POST['phone']);

            $ret = $this->ExtUser->sendCodeVerifySMS($phone);
            $this->_responseJson(['code' => 'ok', 'msg' => __('Đã gửi mã code xác nhận. Xin vui lòng kiểm tra tin nhắn điện thoại!')]);
        }

        $this->_responseJson(['code' => 'error', 'msg' => __('Dữ liệu không hợp lệ.')]);
    }

    /**
     * check code
     * @return void
     * @access public
     */
    public function checkCodePhone()
    {
        $this->_requiredAjax();

        if (!empty($_POST)) {
            $data = &$_POST;

            $ret = $this->ExtUser->verifySMSCode($data['code'], $data['phone']);
            if (!empty($ret)) {

                //tìm xem có user này k, để cập nhật
                $user = $this->User->getUserByPhone($data['phone'], ['_id']);

                if (!empty($user)) {
                    $update = ['_id' => _getID($user['User']['_id']), 'info.phone_verify' => true];
                    $this->User->saveWithKeys($update);

                    //cập nhật session
                    $uLogin = $this->Auth->user('_id');
                    if (!empty($uLogin)) {
                        $this->Session->write('Auth.User.info.phone_verify', true);
                    }
                }
                $this->_responseJson(['code' => 'ok']);
            } else {
                $this->_responseJson(['code' => 'error', 'msg' => __('Mã code xác nhận không hợp lệ. Xin thử lại!')]);
            }
        }

        $this->_responseJson(['code' => 'error', 'msg' => __('Dữ liệu không hợp lệ.')]);
    }
}
