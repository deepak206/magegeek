<?php
/**
* Onealfa_Tranzila module dependency
*
* @category    Onealfa
* @package     Onealfa_Tranzila
 */

namespace Onealfa\Tranzila\Model\Source;

class Cctype extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @return array
     */
    public function getAllowedTypes()
    {
        return array('VI', 'MC', 'AE', 'DI', 'JCB', 'OT');
    }
}
