<?php
/**
 * Onealfa_Tranzila module dependency
 *
 * @category Onealfa
 * @package Onealfa_Tranzila
 */

namespace Onealfa\Tranzila\Model;

class Payment extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'onealfa_tranzila';
    
    protected $_code = 'onealfa_tranzila';
    
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    
    /* protected $_stripeApi = false;*/
    
    protected $_countryFactory;
    
    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_orderStatus = null;
    
    protected $_terminalName = null;
    protected $_apiUrl = null;
    protected $_merchantId = null;
    protected $_apiKey = null;
    protected $_currency = null;
    protected $_tranzilaToken = null;
    protected $_terminalPassword = null;
    protected $_encryptor;
    
    protected $_supportedCurrencyCodes = array();
    
    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];
    
    public function __construct(
        \Magento\Framework\Model\Context $context, 
        \Magento\Framework\Registry $registry, 
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory, 
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory, 
        \Magento\Payment\Helper\Data $paymentData, 
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, 
        \Magento\Payment\Model\Method\Logger $logger, 
        \Magento\Framework\Module\ModuleListInterface $moduleList, 
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate, 
        \Magento\Directory\Model\CountryFactory $countryFactory, 
        \Magento\Framework\Encryption\EncryptorInterface $encryptor, 
        array $data = array()
    )
    {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $moduleList, $localeDate, null, null, $data);
        
        $this->_countryFactory = $countryFactory;
        
        $this->_minAmount        = $this->getConfigData('active');
        $this->_minAmount        = $this->getConfigData('min_order_total');
        $this->_maxAmount        = $this->getConfigData('max_order_total');
        $this->_orderStatus      = $this->getConfigData('order_status');
        $this->_terminalName     = $this->getConfigData('terminal_name');
        $this->_apiUrl           = $this->getConfigData('api_url');
        $this->_terminalPassword = $this->getConfigData('terminal_password');
        $this->_tranzilaToken    = $this->getConfigData('tranzila_token');
        $this->_encryptor        = $encryptor;
        array_push($this->_supportedCurrencyCodes, $this->getConfigData('currency'));
    }
    
    /**
     * Payment authorize
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        if (!$order) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Order does not exists.'));
        }
        
        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();

        if ($this->_tranzilaToken) {
            try {
                /*$query_parameters['supplier']   = $this->_terminalName;
                $query_parameters['TranzilaPW'] = $this->_encryptor->decrypt($this->_terminalPassword);
                $query_parameters['ccno']       = $payment->getCcNumber();
                $query_parameters['TranzilaTK'] = 1;*/

                $query_parameters['supplier'] = $this->_terminalName;
                $query_parameters['TranzilaPW'] = $this->_encryptor->decrypt($this->_terminalPassword);
                $query_parameters['ccno'] = $payment->getCcNumber();
                $query_parameters['TranzilaTK'] = 1;
                $query_parameters['mycvv'] = $payment->getCcCid();
                $query_parameters['expdate'] = sprintf('%02d', $payment->getCcExpMonth()).substr($payment->getCcExpYear(), 2, 2);
                $query_parameters['sum'] = $amount;
                $query_parameters['currency'] = '1';
                $query_parameters['tranmode'] = 'VK';

                $query_string                   = '';
                foreach ($query_parameters as $name => $value) {
                    $query_string .= $name . '=' . $value . '&';
                }
                
                $query_string = substr($query_string, 0, -1); // Remove trailing '&'
                // Initiate CURL
                $cr           = curl_init();
                curl_setopt($cr, CURLOPT_URL, $this->_apiUrl);
                curl_setopt($cr, CURLOPT_POST, 1);
                curl_setopt($cr, CURLOPT_FAILONERROR, true);
                curl_setopt($cr, CURLOPT_POSTFIELDS, $query_string);
                curl_setopt($cr, CURLOPT_RETURNTRANSFER, 1);
                
                $result = curl_exec($cr);
                $error  = curl_error($cr);
                
                if (!empty($error)) {
                    throw new \Magento\Framework\Exception\LocalizedException(__('Error in Processing request.'));
                }
                curl_close($cr);
                // Preparing associative array with response data
                $response_array = explode('&', $result);
                $response_assoc = array();
                if (count($response_array) > 1) {
                    foreach ($response_array as $value) {
                        $tmp = explode('=', $value);
                        if (count($tmp) > 1) {
                            $response_assoc[$tmp[0]] = $tmp[1];
                        }
                    }
                }
                // Analyze the result string
                if (!isset($response_assoc['TranzilaTK'])) {
                    throw new \Magento\Framework\Exception\LocalizedException(__(json_encode($response_assoc)));
                }
                /*elseif($response_assoc['Response'] !== '000') {
                $message = $this->errorMessage($response_assoc['Response']);
                throw new \Magento\Framework\Exception\LocalizedException(__($message));
                }*/
                else {
                    $order->setTranzilaToken($this->_encryptor->encrypt($response_assoc['TranzilaTK']));
                }
            }
            catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
            }            
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('Configuration is disabled for authorize payment.'));
        }
        return $this;
    }

    /**
     * Payment capture
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        if (!$order) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Order does not exists.'));
        }
        
        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();
        try {
            $query_parameters['supplier']  = $this->_terminalName;
            $query_parameters['sum']       = $amount;
            $Query_parameters['currency']  = strtolower($order->getBaseCurrencyCode()); // ILS
            $query_parameters['ccno']      = $payment->getCcNumber(); // Test card number
            $query_parameters['expdate']   = sprintf('%02d', $payment->getCcExpMonth()) . substr($payment->getCcExpYear(), 2, 2);
            $query_parameters['tranmode']  = 'V';
            $query_parameters['cred_type'] = '1';
            $query_parameters['mycvv']     = $payment->getCcCid();

            
            // Prepare query string
            $query_string = '';
            foreach ($query_parameters as $name => $value) {
                $query_string .= $name . '=' . $value . '&';
            }
            
            $query_string = substr($query_string, 0, -1); // Remove trailing '&'
            // Initiate CURL
            $cr           = curl_init();
            curl_setopt($cr, CURLOPT_URL, $this->_apiUrl);
            curl_setopt($cr, CURLOPT_POST, 1);
            curl_setopt($cr, CURLOPT_FAILONERROR, true);
            curl_setopt($cr, CURLOPT_POSTFIELDS, $query_string);
            curl_setopt($cr, CURLOPT_RETURNTRANSFER, 1);
            
            $result = curl_exec($cr);
            $error  = curl_error($cr);
            
            if (!empty($error)) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Error in Processing request.'));
            }
            curl_close($cr);
            
            // Preparing associative array with response data
            $response_array = explode('&', $result);
            $response_assoc = array();
            if (count($response_array) > 1) {
                foreach ($response_array as $value) {
                    $tmp = explode('=', $value);
                    if (count($tmp) > 1) {
                        $response_assoc[$tmp[0]] = $tmp[1];
                    }
                }
            }
            // Analyze the result string
            if (!isset($response_assoc['Response'])) {
                throw new \Magento\Framework\Exception\LocalizedException(__(json_encode($response_assoc)));
            } elseif ($response_assoc['Response'] !== '000') {
                $message = $this->errorMessage($response_assoc['Response']);
                throw new \Magento\Framework\Exception\LocalizedException(__($message));
            } else {
                $order->getPayment()
                ->setTransactionId($response_assoc['TransID'])
                ->setAdditionalInformation(
                        [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) 'Order processed successfully with Tansection ID : '.$response_assoc['TransID']
                        ]
                    )
                ->setIsTransactionClosed(0);
            }
        }
        catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }          
        return $this;
    }

   /**
     * Payment refund
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /*$transactionId = $payment->getParentTransactionId();
        
        try {
        \Stripe\Charge::retrieve($transactionId)->refund(['amount' => $amount]);
        } catch (\Exception $e) {
        $this->debugData(['transaction_id' => $transactionId, 'exception' => $e->getMessage()]);
        $this->_logger->error(__('Payment refunding error.'));
        throw new \Magento\Framework\Validator\Exception(__('Payment refunding error.'));
        }
        
        $payment
        ->setTransactionId($transactionId . '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND)
        ->setParentTransactionId($transactionId)
        ->setIsTransactionClosed(1)
        ->setShouldCloseParentTransaction(1);
        
        return $this;*/
    }
    
    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote && ($quote->getBaseGrandTotal() < $this->_minAmount || ($this->_maxAmount && $quote->getBaseGrandTotal() > $this->_maxAmount))) {
            return false;
        }
        
        if (!$this->getConfigData('active') OR !$this->getConfigData('terminal_name') OR !$this->getConfigData('api_url')) {
            return false;
        }
        if ($this->getConfigData('tranzila_token')) {
            if (!$this->getConfigData('terminal_password')) {
                return false;
            }
        }
        return parent::isAvailable($quote);
    }
    
    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }
    
    function errorMessage($error_code)
    {
        $code = array(
            'ccerror004' => __('Credit Card was declined'),
            'ccerror006' => __('CVV Number is invalid'),
            'ccerror033' => __('Credit Card Number is invalid'),
            'ccerror036' => __('Credit Card expired'),
            'ccerror057' => __('Please specify credit card number'),
            'ccerror062' => __('Transaction type is invalid'),
            'ccerror063' => __('Transaction Code is invalid'),
            'ccerror064' => __('Credit card type is not supported'),
            'ccerror065' => __('Currency type is invalid')
        );
        
        switch ($error_code) {
            case '004':
                return $code['ccerror004'];
                break;
            case '006':
            case '017':
            case '058':
            case '059':
                return $code['ccerror006'];
                break;
            case '033':
                return $code['ccerror033'];
                break;
            case '036':
                return $code['ccerror036'];
                break;
            case '057':
            case '059':
                return $code['ccerror057'];
                break;
            case '062':
                return $code['ccerror062'];
                break;
            case '063':
                return $code['ccerror063'];
                break;
            case '064':
                return $code['ccerror064'];
                break;
            case '065':
                return $code['ccerror065'];
                break;
            case '000':
                return true;
                break;
            default:
                return $code['ccerror004'];
                break;
        }
    }
}
