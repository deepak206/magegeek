<?php
namespace Onealfa\Tranzila\Plugin;

class PluginBefore
{
    public function beforePushButtons(
        \Magento\Backend\Block\Widget\Button\Toolbar\Interceptor $subject, 
        \Magento\Framework\View\Element\AbstractBlock $context, 
        \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
    )
    {
        
        if (!$context instanceof \Magento\Sales\Block\Adminhtml\Order\View) {
            return [$context, $buttonList];
        }
        
        $message = 'Are you sure you want to do this?';
        $this->_request = $context->getRequest();
        
        if ($this->_request->getFullActionName() == 'sales_order_view' && $context->getOrder()->getStatus() == \Magento\Sales\Model\Order::STATE_PROCESSING && $context->getOrder()->getPayment()->getMethodInstance()->getCode() == 'onealfa_tranzila' && !!$context->getOrder()->getTranzilaToken()) {
            $buttonList->add(
                    'tranzila_button',
                    ['label' => __('Approve Payment'), 'onclick' => "confirmSetLocation('{$message}', '".$context->getUrl('tranzila/index/index')."')", 'class' => 'reset'], 
                    -1
                );
        }
    }
}
