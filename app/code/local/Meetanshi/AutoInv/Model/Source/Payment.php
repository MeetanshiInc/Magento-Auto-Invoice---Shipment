<?php

class Meetanshi_AutoInv_Model_Source_Payment
{
    public function toOptionArray()
    {
        $payments = Mage::getSingleton('payment/config')->getActiveMethods();
        $methods = array();

        foreach ($payments as $paymentCode => $paymentModel) {
            $paymentTitle = Mage::getStoreConfig('payment/' . $paymentCode . '/title');
            $methods[] = array(
                'label' => $paymentTitle,
                'value' => $paymentCode,
            );
        }

        return $methods;
    }
}