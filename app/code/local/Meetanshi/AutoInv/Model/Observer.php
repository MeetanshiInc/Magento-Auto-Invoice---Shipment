<?php

class Meetanshi_AutoInv_Model_Observer
{
    const IS_ENABLE = 'autoinv/general/enabled';
    const ENABLE_INVOICE = 'autoinv/general/invoice';
    const ENABLE_SHIPMENT = 'autoinv/general/shipment';
    const ACTIVE_PAYMENT = 'autoinv/general/payment_method';

    public function orderPlaceAfter($observer)
    {
        $isEnable = Mage::getStoreConfig(self::IS_ENABLE);
        $enableInvoice = Mage::getStoreConfig(self::ENABLE_INVOICE);
        $enableShipment = Mage::getStoreConfig(self::ENABLE_SHIPMENT);
        $paymentMethod = explode(",", Mage::getStoreConfig(self::ACTIVE_PAYMENT));

        $order = $observer->getEvent()->getOrder();
        $payment_method_code = $order->getPayment()->getMethodInstance()->getCode();

        if ($isEnable && in_array($payment_method_code, $paymentMethod)) {

            $orders = Mage::getModel('sales/order_invoice')->getCollection()->addAttributeToFilter('order_id', array('eq' => $order->getId()));
            $orders->getSelect()->limit(1);

            if ((int)$orders->count() !== 0) {
                return $this;
            }

            if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) {
                try {
                    if (!$order->canInvoice()) {
                        $order->addStatusHistoryComment('AutoInvoice: Order cannot be invoiced.', false);
                        $order->save();
                    } else {

                        if ($enableInvoice) {
                            //START Handle Invoice
                            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

                            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                            $invoice->register();

                            $transactionSave = Mage::getModel('core/resource_transaction')
                                ->addObject($invoice)
                                ->addObject($invoice->getOrder());
                            $transactionSave->save();

                            $invoice->getOrder()->setIsInProcess(true);
                            $order->addStatusHistoryComment('Auto Invoice generated.',
                                Mage_Sales_Model_Order::STATE_PROCESSING)->setIsCustomerNotified(true);
                            $invoice->sendEmail(true, '');
                            $order->save();
                            //END Handle Invoice
                        }

                        if ($enableShipment) {
                            //START Handle Shipment
                            $shipment = $order->prepareShipment();
                            $shipment->register();

                            $order->setIsInProcess(true);
                            $order->addStatusHistoryComment('Automatically SHIPPED by My_Invoicer.', false);

                            $transactionSave = Mage::getModel('core/resource_transaction')
                                ->addObject($shipment)
                                ->addObject($shipment->getOrder());

                            $transactionSave->save();
                            //END Handle Shipment
                        }
                    }

                } catch (Exception $e) {
                    $order->addStatusHistoryComment('AutoInvoice: Exception occurred during autoInvoice action. Exception message: ' . $e->getMessage(), false);
                    $order->save();
                }
            } else if ($order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING) {
                if ($enableShipment) {
                    //START Handle Shipment
                    $shipment = $order->prepareShipment();
                    $shipment->register();

                    $order->setIsInProcess(true);
                    $order->addStatusHistoryComment('Automatically SHIPPED by My_Invoicer.', false);

                    $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($shipment)
                        ->addObject($shipment->getOrder());

                    $transactionSave->save();
                    //END Handle Shipment
                }
            }
        }
        return $this;
    }
}