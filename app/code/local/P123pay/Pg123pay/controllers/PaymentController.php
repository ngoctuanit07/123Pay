<?php

/*
  Pg123pay Payment Controller
  By: Junaid Bhura
  www.junaidbhura.com
 */

class P123pay_Pg123pay_PaymentController extends Mage_Core_Controller_Front_Action {

    // The redirect action is triggered when someone places an order
    public function redirectAction() {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'pg123pay', array('template' => 'pg123pay/redirect.phtml'));
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    // The response action is triggered when your gateway sends back a response after processing the customer's payment
    public function responseAction() {
        ob_start();
        include 'rest.client.class.php';
        include 'common.class.php';



        $transactionID = $_GET['transactionID'];
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $readresult = $write->query("select * from order_123pay where merchant_transactionID='" . $transactionID . "'");
        $orderId = 0;
        $statusOrder = -1;
        while ($row = $readresult->fetch()) {

            $orderId = $row['order_id'];
            $statusOrder = $row['status'];
        }


        $validated = true;
        $pg123pay = new P123pay_Pg123pay_Model_Standard();
        //sandbox
        $merchantCode = 'MICODE';
        $secretKey = 'MIKEY';
        $passCode = 'MIPASSCODE';
        $queryOrderUrl = 'https://sandbox.123pay.vn/miservice/queryOrder1';
        //production
        if ($pg123pay->getMode() == 1) {
            $merchantCode = trim($pg123pay->getMerchantCode());
            $passCode = trim($pg123pay->getPasscode());
            $secretKey = trim($pg123pay->getSecretKey());
            $queryOrderUrl = trim($pg123pay->getQueryOrderUrl());
        }
        $aConfig = array
            (
            'merchantCode' => $merchantCode,
            'url' => $queryOrderUrl,
            'key' => $secretKey,
            'passcode' => $passCode
        );


        $flag = false;
        $transactionID = $_GET['transactionID'];
        $time = $_GET['time'];
        $status = $_GET['status'];
        $ticket = $_GET['ticket'];

        $recalChecksum = md5($status . $time . $transactionID . $aConfig['key']);
        if ($recalChecksum != $ticket) {

            header('Location: ' . Mage::getBaseUrl());
            exit();
        }

        try {
            $entify_id = 0;
            if ($orderId > 0) {
                $order = Mage::getModel('sales/order');
                $order->loadByIncrementId($orderId);
                $entify_id = $order->getId();
            }
            $cartURL = Mage::getBaseUrl(); //Mage::getUrl('sales/order/view/order_id', array('order_id' => $entify_id, '_secure' => true));

            $IP = $_SERVER['REMOTE_ADDR'];
            if ($IP == '::1')
                $IP = '127.0.0.1';

            $aData = array
                (
                'mTransactionID' => $transactionID,
                'merchantCode' => $merchantCode,
                'clientIP' => $IP,
                'passcode' => $passCode,
                'checksum' => '',
            );

            $data = Common::callRest($aConfig, $aData);
            $msg = '';
            $result = $data->return;

            if ($result['httpcode'] == 200) {



                if ($result[0] == '1') {
                    if ($result[2] == '1') {//success

                        //Do success call service
                        $msg = "Quý khách đã thanh toán thành công.";
                        if ($orderId > 0) {

                            if ($statusOrder != $result[2] || 1==1) {
                                $write->query("update `order_123pay` set`123PayTransactionID`= '" . $result[1] . "',`status`=" . $result[2] . " where `order_id`= " . (int) $orderId);

                                // Payment was successful, so update the order's state, send order email and move to the success page
                                $order = Mage::getModel('sales/order');
                                $order->loadByIncrementId($orderId);
                                //get state by status
                                $status_config = Mage::getStoreConfig('payment/pg123pay/order_status_succeed'); //value																		
                                $item = Mage::getResourceModel('sales/order_status_collection')
                                        ->joinStates()
                                        ->addFieldToFilter('main_table.status', $status_config)
                                        ->getFirstItem();
                                //$state_custom = $item->getState();
                                //$order->setState($state_custom, $status_config, 'Thanh toán qua 123Pay thành công');
			        $order->setData('state', $status_config);
			        $order->setStatus($status_config);  
				$history = $order->addStatusHistoryComment('Thanh toán qua 123Pay thành công', false);
        			$history->setIsCustomerNotified(false);
                                $order->save();
                                try {
                                    $order->sendNewOrderEmail();
                                    $order->setEmailSent(true);
                                } catch (Exception $_e) {
                                    
                                }


                                echo ("<SCRIPT LANGUAGE='JavaScript'>window.location.href='".Mage::getUrl('checkout/onepage/success', array('_secure' => true))."';</SCRIPT>");	
                                exit();
                                //Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure' => true));
                            } elseif ($statusOrder == 1){
                                echo ("<SCRIPT LANGUAGE='JavaScript'>window.location.href='".Mage::getUrl('checkout/onepage/success', array('_secure' => true))."';</SCRIPT>");	
                                exit();
                            }
                                //Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure' => true));
                        }
                    }else {

                        $msg = "Thanh toán qua 123Pay không thành công, quý khách nhấn <a href='" . $cartURL . "'>vào đây</a> để tiếp tục mua hàng.";
                        if ($statusOrder != $result[2] && $orderId > 0) {
                            $order = Mage::getModel('sales/order');
                            $order->loadByIncrementId($orderId);
                            $order->setState('new', 'canceled', 'Thanh toán qua 123Pay không thành công');
                            $order->save();
                        }
                    }
                } else {
                    //echo 'Call service queryOrder fail: Order is processing. Please waiting some munite and check your order history list';
                    $msg = "Thanh toán qua 123Pay không thành công, quý khách nhấn <a href='" . $cartURL . "'>vào đây</a> để tiếp tục mua hàng.";
                    if ($statusOrder != $result[2] && $orderId > 0) {
                        $order = Mage::getModel('sales/order');
                        $order->loadByIncrementId($orderId);
                        $order->setState('new', 'canceled', 'Thanh toán qua 123Pay không thành công');
                        $order->save();
                    }
                }
            } else {
                $msg = "Thanh toán qua 123Pay không thành công, quý khách nhấn <a href='" . $cartURL . "'>vào đây</a> để tiếp tục mua hàng.";
                if ($statusOrder != $result[2] && $orderId > 0) {
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($orderId);
                    $order->setState('new', 'canceled', 'Thanh toán qua 123Pay không thành công');
                    $order->save();
                }
                //do error call service.
                //echo 'Call service queryOrder fail: Order is processing. Please waiting some munite and check your order history list';
            }
        } catch (Exception $e) {

            $msg = "Thanh toán qua 123Pay không thành công, quý khách nhấn <a href='" . $cartURL . "'>vào đây</a> để tiếp tục mua hàng.";
            if ($statusOrder != $result[2] && $orderId > 0) {
                $order = Mage::getModel('sales/order');
                $order->loadByIncrementId($orderId);
                $order->setState('new', 'canceled', 'Thanh toán qua 123Pay không thành công');
                $order->save();
            }
        }



        $this->loadLayout();
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'pg123pay', array('template' => 'pg123pay/response.phtml'));
        $this->getLayout()->getBlock('content')->append($block);
        $block->assign('msg', $msg);
        $this->renderLayout();
        ob_end_flush();
    }

    // The cancel action is triggered when an order is to be cancelled
    public function cancelAction() {
        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
            if ($order->getId()) {

                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
            }
        }
    }

    public function notifyAction() {
        include 'common.class.php';

        $model = Mage::getModel('pg123pay/standard');

        $mTransactionID = $_REQUEST['mTransactionID'];
        $bankCode = $_REQUEST['bankCode'];
        $transactionStatus = $_REQUEST['transactionStatus'];
        $description = $_REQUEST['description'];
        $ts = $_REQUEST['ts'];
        $checksum = $_REQUEST['checksum'];


        $sMySecretkey = trim($model->getSecretKey()); //key use to hash checksum that will be provided by 123Pay
        $sRawMyCheckSum = $mTransactionID . $bankCode . $transactionStatus . $ts . $sMySecretkey;
        $sMyCheckSum = sha1($sRawMyCheckSum);

        if ($sMyCheckSum != $checksum) {
            $this->response($mTransactionID, '-1', $sMySecretkey);
        }
        $iCurrentTS = time();
        $iTotalSecond = $iCurrentTS - $ts;

        $iLimitSecond = 300; //5 min = 5*60 = 300                
        $processResult = $this->process($mTransactionID, $bankCode, $transactionStatus);
        $this->response($mTransactionID, $processResult, $sMySecretkey);


        /* ===============================Function region======================================= */
    }

    public function process($mTransactionID, $bankCode, $transactionStatus) {
        try {
            $write = Mage::getSingleton('core/resource')->getConnection('core_write');
            $readresult = $write->query("select * from order_123pay where merchant_transactionID='" . $mTransactionID . "'");
            $orderId = 0;
            $statusOrder = -1;
            while ($row = $readresult->fetch()) {

                $orderId = $row['order_id'];
                $statusOrder = $row['status'];
            }
            if ($statusOrder == 1)
                return 2;
            $write->query("update `order_123pay` set `status`=" . $transactionStatus . " where `order_id`= " . (int) $orderId);

            // Payment was successful, so update the order's state, send order email and move to the success page
            if ($transactionStatus == 1) {
                $order = Mage::getModel('sales/order');
                $order->loadByIncrementId($orderId);
                //get state by status
                $status_config = Mage::getStoreConfig('payment/pg123pay/order_status_succeed'); //value																		
                $item = Mage::getResourceModel('sales/order_status_collection')
                        ->joinStates()
                        ->addFieldToFilter('main_table.status', $status_config)
                        ->getFirstItem();
                $state_custom = $item->getState();

                $order->setState($state_custom, $status_config, 'Thanh toán qua 123Pay thành công');
                $order->save();
                try {
                    $order->sendNewOrderEmail();
                    $order->setEmailSent(true);
                } catch (Exception $_e) {
                    return 1;
                }
            } else {
                $order = Mage::getModel('sales/order');
                $order->loadByIncrementId($orderId);
                $order->setState('new', 'canceled', 'Thanh toán qua 123Pay không thành công');
                $order->save();
            }


            return 1; //if process successfully
        } catch (Exception $_e) {
            return -3;
        }
    }

    public function response($mTransactionID, $returnCode, $key) {
        $ts = time();
        $sRawMyCheckSum = $mTransactionID . $returnCode . $ts . $key;
        $checksum = sha1($sRawMyCheckSum);
        $aData = array(
            'mTransactionID' => $mTransactionID,
            'returnCode' => $returnCode,
            'ts' => time(),
            'checksum' => $checksum
        );
        echo json_encode($aData);
        exit;
    }

}

