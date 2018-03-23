<?php
class P123pay_Pg123pay_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'pg123pay';
	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;
	
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('pg123pay/payment/redirect', array('_secure' => true));
	}
        
        public function getTitle()
        {
            return $this->getConfigData('title');
        }
        public function getMerchantCode()
        {
            return $this->getConfigData('merchant_code');
        }
        public function getMode()
        {
            return $this->getConfigData('mode');
        }
		public function getOrderPrefix()
        {
            return $this->getConfigData('order_prefix');
        }
        public function getPasscode()
        {
            return $this->getConfigData('passcode');
        }
        public function getSecretKey()
        {
            return $this->getConfigData('secret_key');
        }
        public function getCreateOrderUrl()
        {
            return $this->getConfigData('create_order_url');
        }
        public function getQueryOrderUrl()
        {
            return $this->getConfigData('query_order_url');
        }
        public function getCartUrl()
        {
            return $this->getConfigData('cart_url');
        }
		public function getOrderStatusSucceed()
        {
            return $this->getConfigData('order_status_succeed');
        }
        function remove_sign($str = '')
        {
                $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/", 'a', $str);
                $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/", 'e', $str);
                $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/", 'i', $str);
                $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/", 'o', $str);
                $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/", 'u', $str);
                $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/", 'y', $str);
                $str = preg_replace("/(đ)/", 'd', $str);
                $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/", 'A', $str);
                $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/", 'E', $str);
                $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/", 'I', $str);
                $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/", 'O', $str);
                $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/", 'U', $str);
                $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/", 'Y', $str);
                $str = preg_replace("/(Đ)/", 'D', $str);
                return $str;
        }
        public function createOder123PayATM()
        {
           
			if(file_exists(dirname(__file__).'/common.class.php'))
			{
				include dirname(__file__).'/rest.client.class.php';
				include dirname(__file__).'/common.class.php';
			}
			else
			{
				include 'P123pay_Pg123pay_Model_rest.client.class.php';
				include 'P123pay_Pg123pay_Model_common.class.php';
			}
            $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();            
            $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);            
            $price = $order->getTotalDue();
            $write = Mage::getSingleton('core/resource')->getConnection('core_write');
            $table=" 
                    CREATE TABLE IF NOT EXISTS `order_123pay` (
                            `123pay_order_id` int(11) NOT NULL AUTO_INCREMENT,
                            `order_id` int(11) NOT NULL,
                            `status` int(11) NULL DEFAULT '-1',
                            `123PayTransactionID` varchar(255) NOT NULL,
                            `merchant_transactionID` varchar(255) NOT NULL,
                            `created` DATETIME NOT NULL,
                            `modified` DATETIME NOT NULL,	
                            `total` DECIMAL( 10, 2 ) NOT NULL,
                            PRIMARY KEY (`123pay_order_id`)
                    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
            $write->query($table);            
            
            $IP=$_SERVER['REMOTE_ADDR'];
            if($IP=='::1')
                    $IP='127.0.0.1';
            
            $redirectURL=Mage::getUrl('pg123pay/payment/response', array('_secure' => true));   
            
            $mTransactionID = '';
            $orderIdPrefix = $this->getOrderPrefix();;
            $mTransactionID=$orderIdPrefix.time();
			//sandbox
			$merchantCode='MICODE';
			$secretKey='MIKEY';
			$passCode='MIPASSCODE';
			$createOderURL='https://sandbox.123pay.vn/miservice/createOrder1';
			if($this->getMode()==1){
				$merchantCode = trim($this->getMerchantCode());
				$createOderURL = trim($this->getCreateOrderUrl());
				$secretKey = trim($this->getSecretKey());
				$passCode = trim($this->getPasscode());
			}
            $aData = array
            (
                    'mTransactionID' 	=> $mTransactionID,
                    'merchantCode' 		=> $merchantCode,
                    'bankCode' 			=> '123PAY',
                    'totalAmount' 		=> $price,
                    'clientIP' 			=> $IP,
                    'custName' 			=> substr($this->remove_sign($order->_data['customer_lastname']) .' '.$this->remove_sign($order->_data['customer_firstname']), 0, 63),
                    'custAddress'		=> substr($order->getBillingAddress()->getStreet(-1).' '.$order->getBillingAddress()->getCity(), 0, 255),
                    'custGender' 		=> 'U',
                    'custDOB' 			=> '',
                    'custPhone' 		=> preg_replace("/[^0-9]/", "",$order->getBillingAddress()->getTelephone()),
                    'custMail' 			=> $order->_data['customer_email'],
                    'description' 		=> 'Thanh toan don hang '.$order_id,
                    'cancelURL' 		=> $redirectURL,
                    'redirectURL' 		=> $redirectURL,
                    'errorURL' 			=> $redirectURL,
                    'passcode' 			=> $passCode,
                    'checksum' 			=>'',
                    'addInfo' 			=>''
            );

            $aConfig = array
            (
                    'url'		  => $createOderURL,
                    'key'		  => $secretKey,
                    'passcode'	  => $passCode,
                    'cancelURL'   => 'merchantCancelURL', 
                    'redirectURL' => 'merchantRedirectURL', 
                    'errorURL' 	  => 'merchantErrorURL', 
            );

            try
            {
               
                    $data = Common::callRest($aConfig, $aData);//call 123Pay service
                    $result = $data->return;


                    if($result['httpcode'] ==  200)
                    {
                            //call service success do success flow
                            if($result[0]=='1')//service return success
                            {
                                    //re-create checksum
                                    $rawReturnValue = '1'.$result[1].$result[2];
                                    $reCalChecksumValue = sha1($rawReturnValue.$aConfig['key']);
                                    
                                    if($reCalChecksumValue == $result[3])//check checksum
                                    {
                                            $write->query("INSERT INTO `order_123pay` (`order_id`, `123PayTransactionID`, `merchant_transactionID`, `created`, `modified`, `total`) "
                                            . "  VALUES (".$order_id.", '', '".$mTransactionID."', '".date('Y-m-d H:i:s')."', '', ".$price.")"); 
                                            $order = Mage::getModel('sales/order');
                                            $order->loadByIncrementId($order_id);
                                            $order->setState('new', 'pending');
                                            $order->save();
                                            echo ("<SCRIPT LANGUAGE='JavaScript'>window.location.href='".$result[2]."';</SCRIPT>");	
                                            exit();
                                            //call php header to redirect to input card page
                                            $resultMessage .= '<a style="color:red;font-weight:bold;" href="'.$result[2].'" target="_parent">Click here to go to payment process</a><br>';
                                    }else
                                    { 
                                            echo "Thanh toán qua 123Pay không thành công.";
                                            exit();
                                            //Call 123Pay service create order fail, return checksum is invalid
                                            //$resultMessage .=  'Return data is invalid<br>';
                                    }
                            }else{
                                    echo "Thanh toán qua 123Pay không thành công.";
                                    exit();
                                   
                            }
                    }else{
                            echo "Thanh toán qua 123Pay không thành công.";
                            exit();
                            //call service fail, do error flow
                            //$resultMessage .=  'Call 123Pay service fail. Please recheck your network connection<br>';
                    }
            }catch(Exception $e)
            {
                    echo "Thanh toán qua 123Pay không thành công.";
                    exit();
                    //$resultMessage .=  '<pre>';
                    //$resultMessage .= $e->getMessage();
            }
            
		
        }
}
?>
