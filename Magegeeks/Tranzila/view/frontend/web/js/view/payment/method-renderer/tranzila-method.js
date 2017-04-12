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
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (Component, $) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Onealfa_Tranzila/payment/tranzila-form'
            },

            getCode: function() {
                return 'onealfa_tranzila';
            },

            isActive: function() {
                return true;
            },

            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
