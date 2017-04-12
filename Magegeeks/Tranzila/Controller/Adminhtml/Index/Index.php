<?php
namespace Magegeeks\Tranzila\Controller\Adminhtml\Index;
use Magento\Framework\Controller\ResultFactory;

class Index extends \Magento\Backend\App\Action
{
    protected $_resultPageFactory;
    protected $_scopeConfig;
    protected $context;
    protected $_responseFactory;
    protected $_encryptor;
    protected $_resultRedirect;

    public function __construct(
        \Magento\Backend\App\Action\Context $context, 
        \Magento\Framework\View\Result\PageFactory $resultPageFactory, 
        \Magento\Framework\App\ResponseFactory $responseFactory, 
        \Magento\Framework\Encryption\EncryptorInterface $encryptor, 
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->_resultPageFactory = $resultPageFactory;
        $this->_scopeConfig       = $scopeConfig;
        $this->context            = $context;
        $this->_responseFactory   = $responseFactory;
        $this->_encryptor         = $encryptor;

        return parent::__construct($context);
    }
    
    public function execute()
    {
        $orderId  = $this->getRequest()->getParam('order_id');
        $orderObj = $this->_objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
        
        $terminalName     = $this->_scopeConfig->getValue('payment/magegeeks_tranzila/terminal_name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $apiUrl           = $this->_scopeConfig->getValue('payment/magegeeks_tranzila/api_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $terminalPassword = $this->_scopeConfig->getValue('payment/magegeeks_tranzila/terminal_password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $redirectUrl      = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setUrl($this->_redirect->getRefererUrl());
        
        if ($orderObj->getStatus() != 'processing' || $orderObj->getPayment()->getMethodInstance()->getCode() != 'magegeeks_tranzila' || !$orderObj->getTranzilaToken()) {
            $this->messageManager->addError(__('Invalid Payment Request.'));
            return $redirectUrl;
        }
        
        $grandTotal = $orderObj->getGrandTotal();
        try {
            $query_parameters['sum'] = number_format($grandTotal, 2);
            $query_parameters['pdesc'] = '';
            $query_parameters['contact'] = $orderObj->getBillingAddress()->getFirstname().$orderObj->getBillingAddress()->getLastname();
            $query_parameters['company'] = "Personal";
            $query_parameters['email'] = $orderObj->getBillingAddress()->getEmail();
            $query_parameters['phone'] = $orderObj->getBillingAddress()->getTelephone();
            $query_parameters['fax'] = $orderObj->getBillingAddress()->getFax();
            $query_parameters['address'] = $orderObj->getBillingAddress()->getStreet()[0];
            $query_parameters['city'] = $orderObj->getBillingAddress()->getCity();
            $query_parameters['remarks'] = '';
            $query_parameters['currency'] = '1';
            $query_parameters['myid'] = '';
            $query_parameters['TranzilaToken'] = $this->_encryptor->decrypt($orderObj->getTranzilaToken());
            $query_parameters['supplier'] = $terminalName;
            $query_parameters['TranzilaPW'] = $this->_encryptor->decrypt($terminalPassword);
            $query_parameters['expdate'] = sprintf('%02d', $orderObj->getPayment()->getCcExpMonth()).substr($orderObj->getPayment()->getCcExpYear(), 2, 2);
            $query_parameters['orderid'] = $orderObj->getIncrementId();
            $query_parameters['cred_type'] = '1';
            // Prepare query string
            $query_string  =  '' ;
            foreach  ($query_parameters as $name => $value)  {
                if(!$value) {
                    continue;
                }
                $query_string .= $name.'='.$value.'&';
            }
            $query_string = substr($query_string, 0, -1); // Remove trailing '&'
            // Initiate CURL
            $cr = curl_init();
            curl_setopt($cr, CURLOPT_URL, $apiUrl);
            curl_setopt($cr, CURLOPT_POST, 1);
            curl_setopt($cr, CURLOPT_FAILONERROR, true);
            curl_setopt($cr, CURLOPT_POSTFIELDS, $query_string);
            curl_setopt($cr, CURLOPT_RETURNTRANSFER, 1);
            
            $result = curl_exec($cr);
            $error  = curl_error($cr);
            
            if (!empty($error)) {
                $this->messageManager->addError(__('Error in Processing request.'));
                return $redirectUrl;
            }
            curl_close($cr);
            
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
                $this->messageManager->addError(__('Error in Processing request.'));
                return $redirectUrl;
            } elseif ($response_assoc['Response'] !== '000') {
                $message = $this->errorMessage($response_assoc['Response']);
                $this->messageManager->addError(__($message));
                return $redirectUrl;
            } else {
                $orderObj->setState(\Magento\Sales\Model\Order::STATE_COMPLETE, true);
                $orderObj->setStatus(\Magento\Sales\Model\Order::STATE_COMPLETE);
                $orderObj->addStatusToHistory($order->getStatus(), 'Order processed successfully with Tansection ID : ' . $response_assoc['TransID']);
                
                /*$orderObj->getPayment()
                ->setTransactionId($response_assoc['TransID'])
                ->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $message]
                )
                ->setIsTransactionClosed(0);*/
                $orderObj->save();
                $this->messageManager->addSuccess(__('The payment has been accepted.'));
                return $redirectUrl;
            }
        }
        catch (\Exception $e) {
            $this->messageManager->addError(__($e->getMessage()));
            return $redirectUrl;
        }
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
