/**
* Onealfa_Tranzila module dependency
*
* @category    Onealfa
* @package     Onealfa_Tranzila
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'onealfa_tranzila',
                component: 'Onealfa_Tranzila/js/view/payment/method-renderer/tranzila-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);